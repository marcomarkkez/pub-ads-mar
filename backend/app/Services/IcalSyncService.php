<?php

namespace App\Services;

use App\Models\Space;
use App\Models\SpaceAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class IcalSyncService
{
    /**
     * Sync availabilities from a space's iCal URL.
     *
     * @return array{synced: int, errors: string[]}
     */
    public function syncFromUrl(Space $space): array
    {
        if (empty($space->ical_url)) {
            return ['synced' => 0, 'errors' => ['Space has no iCal URL configured.']];
        }

        $icsContent = $this->fetchIcalContent($space->ical_url);

        if ($icsContent === null) {
            return ['synced' => 0, 'errors' => ['Failed to fetch iCal URL: ' . $space->ical_url]];
        }

        return $this->syncFromContent($space, $icsContent);
    }

    /**
     * Sync availabilities from raw .ics file content.
     *
     * @return array{synced: int, errors: string[]}
     */
    public function syncFromContent(Space $space, string $icsContent): array
    {
        $events = $this->parseIcalEvents($icsContent);
        $errors = [];

        // Offline-safe: a zero-event parse (transient empty/garbage fetch)
        // must NOT wipe the last-known ical availabilities. Bail out early
        // and preserve whatever is currently stored.
        if (empty($events)) {
            return ['synced' => 0, 'errors' => ['No VEVENT entries found in iCal data.']];
        }

        // Delete all previous ical-sourced availabilities for this space
        $space->availabilities()->where('source', 'ical')->delete();

        $synced = 0;

        $keyword = $space->calendar_keyword ? trim($space->calendar_keyword) : null;

        foreach ($events as $event) {
            try {
                // If a calendar_keyword is set, only events whose SUMMARY
                // contains that keyword are treated as occupied. Otherwise
                // ALL events are treated as occupied.
                if ($keyword !== null && $keyword !== '') {
                    $summary = $event['summary'] ?? '';
                    if (stripos($summary, $keyword) === false) {
                        continue; // event doesn't match keyword — skip (day stays available)
                    }
                }

                $startDate = $this->parseIcalDate($event['dtstart']);
                $endDate = $this->parseIcalDate($event['dtend']);

                if ($startDate === null || $endDate === null) {
                    $errors[] = 'Could not parse dates for event: DTSTART=' . $event['dtstart'] . ', DTEND=' . $event['dtend'];
                    continue;
                }

                // If end date has no time component (DATE-only, not DATE-TIME),
                // iCal spec says DTEND is exclusive, so subtract one day.
                if ($this->isDateOnly($event['dtend']) && $endDate->gt($startDate)) {
                    $endDate = $endDate->subDay();
                }

                // Ensure end_date is not before start_date
                if ($endDate->lt($startDate)) {
                    $endDate = $startDate->copy();
                }

                SpaceAvailability::create([
                    'space_id' => $space->id,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'status' => 'blocked',
                    'source' => 'ical',
                ]);

                $synced++;
            } catch (\Throwable $e) {
                $errors[] = 'Error processing event: ' . $e->getMessage();
                Log::warning('IcalSync: Error processing event for space #' . $space->id, [
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Successful parse (>=1 VEVENT) — stamp the staleness marker so the
        // UI can flag calendars that haven't refreshed recently.
        $space->calendar_synced_at = now();
        $space->save();

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Fetch iCal content from a URL using file_get_contents.
     */
    protected function fetchIcalContent(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'PubAdsMar/1.0 iCal Sync',
                'follow_location' => true,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $content = @file_get_contents($url, false, $context);

            if ($content === false) {
                Log::warning('IcalSync: Failed to fetch URL', ['url' => $url]);
                return null;
            }

            return $content;
        } catch (\Throwable $e) {
            Log::warning('IcalSync: Exception fetching URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse VEVENT blocks from raw iCal content.
     *
     * @return array<int, array{dtstart: string, dtend: string, summary: string}>
     */
    protected function parseIcalEvents(string $icsContent): array
    {
        $events = [];

        // Unfold lines per RFC 5545: lines starting with a space or tab
        // are continuations of the previous line.
        $icsContent = preg_replace('/\r\n[ \t]/', '', $icsContent);
        $icsContent = preg_replace('/\r\n/', "\n", $icsContent);
        $icsContent = preg_replace('/\r/', "\n", $icsContent);

        // Extract all VEVENT blocks
        if (!preg_match_all('/BEGIN:VEVENT\s*\n(.*?)END:VEVENT/s', $icsContent, $matches)) {
            return [];
        }

        foreach ($matches[1] as $eventBlock) {
            $dtstart = '';
            $dtend = '';
            $summary = '';

            // Parse DTSTART — may have parameters like DTSTART;VALUE=DATE:20260301
            if (preg_match('/^DTSTART[^:]*:(.+)$/m', $eventBlock, $m)) {
                $dtstart = trim($m[1]);
            }

            // Parse DTEND
            if (preg_match('/^DTEND[^:]*:(.+)$/m', $eventBlock, $m)) {
                $dtend = trim($m[1]);
            }

            // Parse SUMMARY (optional, for logging)
            if (preg_match('/^SUMMARY[^:]*:(.+)$/m', $eventBlock, $m)) {
                $summary = trim($m[1]);
            }

            // Skip events without both start and end
            if (empty($dtstart) || empty($dtend)) {
                continue;
            }

            $events[] = [
                'dtstart' => $dtstart,
                'dtend' => $dtend,
                'summary' => $summary,
            ];
        }

        return $events;
    }

    /**
     * Parse an iCal date/datetime string into a Carbon instance.
     *
     * Supports formats:
     * - 20260301 (DATE only, 8 digits)
     * - 20260301T120000 (local datetime)
     * - 20260301T120000Z (UTC datetime)
     */
    protected function parseIcalDate(string $value): ?Carbon
    {
        $value = trim($value);

        // DATE only: 8 digits, e.g. 20260301
        if (preg_match('/^\d{8}$/', $value)) {
            return Carbon::createFromFormat('Ymd', $value)->startOfDay();
        }

        // DATETIME with Z (UTC): 20260301T120000Z
        if (preg_match('/^\d{8}T\d{6}Z$/', $value)) {
            return Carbon::createFromFormat('Ymd\THis\Z', $value, 'UTC');
        }

        // DATETIME local: 20260301T120000
        if (preg_match('/^\d{8}T\d{6}$/', $value)) {
            return Carbon::createFromFormat('Ymd\THis', $value);
        }

        return null;
    }

    /**
     * Check if an iCal date string is date-only (no time component).
     */
    protected function isDateOnly(string $value): bool
    {
        return (bool) preg_match('/^\d{8}$/', trim($value));
    }
}
