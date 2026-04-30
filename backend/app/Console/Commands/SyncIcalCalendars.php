<?php

namespace App\Console\Commands;

use App\Models\Space;
use App\Services\IcalSyncService;
use Illuminate\Console\Command;

class SyncIcalCalendars extends Command
{
    protected $signature = 'ical:sync
                            {--space= : Sync only a specific space ID}';

    protected $description = 'Sync iCal calendars for all spaces that have an iCal URL configured';

    public function handle(IcalSyncService $service): int
    {
        $spaceId = $this->option('space');

        $query = Space::whereNotNull('ical_url')->where('ical_url', '!=', '');

        if ($spaceId) {
            $query->where('id', $spaceId);
        }

        $spaces = $query->get();

        if ($spaces->isEmpty()) {
            $this->info('No spaces with iCal URLs found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$spaces->count()} space(s) with iCal URLs.");
        $this->newLine();

        $totalSynced = 0;
        $totalErrors = 0;

        foreach ($spaces as $space) {
            $this->info("Syncing space #{$space->id}: {$space->name}");
            $this->info("  URL: {$space->ical_url}");

            $result = $service->syncFromUrl($space);

            $totalSynced += $result['synced'];

            if ($result['synced'] > 0) {
                $this->info("  Synced {$result['synced']} event(s) as blocked availabilities.");
            }

            if (!empty($result['errors'])) {
                $totalErrors += count($result['errors']);
                foreach ($result['errors'] as $error) {
                    $this->error("  Error: {$error}");
                }
            }

            $this->newLine();
        }

        $this->info("Done. Total synced: {$totalSynced}, Total errors: {$totalErrors}");

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
