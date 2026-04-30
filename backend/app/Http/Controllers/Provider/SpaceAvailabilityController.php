<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\SpaceAvailability;
use App\Services\IcalSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaceAvailabilityController extends Controller
{
    public function index(Request $request, Space $space): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($space->availabilities()->orderBy('start_date')->get());
    }

    public function store(Request $request, Space $space): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|in:available,booked,blocked',
        ]);

        $availability = $space->availabilities()->create($validated);

        return response()->json($availability, 201);
    }

    public function destroy(Request $request, Space $space, SpaceAvailability $availability): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $availability->delete();

        return response()->json(['message' => 'Availability deleted.']);
    }

    /**
     * Trigger a manual iCal sync for a space from its configured ical_url.
     */
    public function syncIcal(Request $request, Space $space, IcalSyncService $service): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (empty($space->ical_url)) {
            return response()->json([
                'message' => 'This space does not have an iCal URL configured.',
            ], 422);
        }

        $result = $service->syncFromUrl($space);

        $availabilities = $space->availabilities()
            ->where('source', 'ical')
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'message' => "Synced {$result['synced']} event(s) from iCal calendar.",
            'synced' => $result['synced'],
            'errors' => $result['errors'],
            'availabilities' => $availabilities,
        ]);
    }

    /**
     * Import a .ics file upload and parse it into availabilities.
     */
    public function importIcal(Request $request, Space $space, IcalSyncService $service): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:2048', // max 2MB
        ]);

        $file = $request->file('file');

        // Validate file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'ics') {
            return response()->json([
                'message' => 'The uploaded file must be an .ics file.',
            ], 422);
        }

        $icsContent = file_get_contents($file->getRealPath());

        if (empty($icsContent)) {
            return response()->json([
                'message' => 'The uploaded file is empty.',
            ], 422);
        }

        $result = $service->syncFromContent($space, $icsContent);

        $availabilities = $space->availabilities()
            ->where('source', 'ical')
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'message' => "Imported {$result['synced']} event(s) from .ics file.",
            'synced' => $result['synced'],
            'errors' => $result['errors'],
            'availabilities' => $availabilities,
        ]);
    }
}
