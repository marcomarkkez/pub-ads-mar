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
    /**
     * 404, never 403 — §21 rule 2 (BR-3): a 403 confirms the row exists, which is
     * enough to enumerate another account's ids. "Not yours" and "does not exist"
     * must be indistinguishable to a stranger.
     *
     * Resolved in ONE place for all five actions: this controller used to repeat the
     * check five times, which is exactly how a surface ends up answering 404 on one
     * verb and 403 on the next and leaking through whichever door was forgotten.
     */
    private function refuseForeignSpace(Request $request, Space $space): ?JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return null;
    }

    public function index(Request $request, Space $space): JsonResponse
    {
        if ($refusal = $this->refuseForeignSpace($request, $space)) {
            return $refusal;
        }

        return response()->json($space->availabilities()->orderBy('start_date')->get());
    }

    public function store(Request $request, Space $space): JsonResponse
    {
        if ($refusal = $this->refuseForeignSpace($request, $space)) {
            return $refusal;
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
        // The availability link is checked too, and for the same reason as the owner
        // check: this route is not ->scopeBindings(), so `{availability}` was resolved
        // independently of `{space}` and a provider could delete a FOREIGN provider's
        // availability row by pairing it with a listing of their own (§21 rule 1).
        if ($refusal = $this->refuseForeignSpace($request, $space)) {
            return $refusal;
        }
        if ($availability->space_id !== $space->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $availability->delete();

        return response()->json(['message' => 'Availability deleted.']);
    }

    /**
     * Trigger a manual iCal sync for a space from its configured ical_url.
     */
    public function syncIcal(Request $request, Space $space, IcalSyncService $service): JsonResponse
    {
        if ($refusal = $this->refuseForeignSpace($request, $space)) {
            return $refusal;
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
        if ($refusal = $this->refuseForeignSpace($request, $space)) {
            return $refusal;
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
