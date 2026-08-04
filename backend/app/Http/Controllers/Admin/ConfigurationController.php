<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\SystemConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConfigurationController extends Controller
{
    public function index(): JsonResponse
    {
        $configs = SystemConfiguration::orderBy('key')->get();

        return response()->json($configs);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'configs' => 'required|array|min:1',
            'configs.*.key' => ['required', 'string', Rule::in(SystemConfiguration::KNOWN_KEYS)],
            'configs.*.value' => 'nullable',
            'apply_scope' => 'required|in:new_only,all',
        ]);

        $pairs = [];
        foreach ($validated['configs'] as $row) {
            $pairs[$row['key']] = $row['value'] ?? null;
        }

        // The old values have to be read BEFORE setMany() overwrites them —
        // updateOrCreate leaves nothing behind to diff against afterwards.
        $previous = SystemConfiguration::whereIn('key', array_keys($pairs))
            ->pluck('value', 'key')
            ->all();

        DB::transaction(function () use ($request, $validated, $pairs, $previous) {
            SystemConfiguration::setMany($pairs, $request->user()->id);

            // apply_scope 'all' also patches in-flight bookings' snapshots so the
            // new proof deadline applies retroactively to live bookings.
            $patched = 0;

            if ($validated['apply_scope'] === 'all' && array_key_exists('proof_deadline_days', $pairs)) {
                $deadline = $pairs['proof_deadline_days'];

                $inFlight = ['pending', 'waiting_approval', 'confirmed', 'active', 'waiting_proof'];

                // Each row needs its OWN snapshot merged (the other keys must survive), so this
                // stays one UPDATE per booking — but chunked, not loaded whole. `get()->each()`
                // pulled every in-flight booking into memory at once, which is fine with the
                // seeder's handful and is not fine on a real season.
                Booking::whereIn('status', $inFlight)
                    ->chunkById(200, function ($bookings) use ($deadline, &$patched) {
                        foreach ($bookings as $booking) {
                            $snapshot = $booking->config_snapshot ?? [];
                            $snapshot['proof_deadline_days'] = $deadline;
                            $booking->update(['config_snapshot' => $snapshot]);
                            $patched++;
                        }
                    });
            }

            // §2 · System config is Admin's 🟢 and a staff write, so it is 📝-logged:
            // one entry per key that actually moved, keyed to the config row itself.
            $rows = SystemConfiguration::whereIn('key', array_keys($pairs))->get()->keyBy('key');

            foreach ($pairs as $key => $value) {
                $before = $previous[$key] ?? null;
                $after = $rows[$key]?->value;

                if ($before === $after) {
                    continue;
                }

                AuditLog::record(
                    $request->user(),
                    'update',
                    $rows[$key],
                    [$key => $before],
                    [$key => $after],
                    'admin config · scope=' . $validated['apply_scope']
                        . ($patched ? ' · ' . $patched . ' in-flight bookings re-snapshotted' : ''),
                );
            }
        });

        return response()->json(SystemConfiguration::orderBy('key')->get());
    }
}
