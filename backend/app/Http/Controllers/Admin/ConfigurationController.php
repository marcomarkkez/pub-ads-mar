<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SystemConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'configs.*.key' => 'required|string|max:255',
            'configs.*.value' => 'nullable',
            'apply_scope' => 'required|in:new_only,all',
        ]);

        $pairs = [];
        foreach ($validated['configs'] as $row) {
            $pairs[$row['key']] = $row['value'] ?? null;
        }

        SystemConfiguration::setMany($pairs, $request->user()->id);

        // apply_scope 'all' also patches in-flight bookings' snapshots so the
        // new proof deadline applies retroactively to live bookings.
        if ($validated['apply_scope'] === 'all' && array_key_exists('proof_deadline_days', $pairs)) {
            $deadline = $pairs['proof_deadline_days'];

            $inFlight = ['pending', 'waiting_approval', 'confirmed', 'active', 'waiting_proof'];

            Booking::whereIn('status', $inFlight)->get()->each(function (Booking $booking) use ($deadline) {
                $snapshot = $booking->config_snapshot ?? [];
                $snapshot['proof_deadline_days'] = $deadline;
                $booking->update(['config_snapshot' => $snapshot]);
            });
        }

        return response()->json(SystemConfiguration::orderBy('key')->get());
    }
}
