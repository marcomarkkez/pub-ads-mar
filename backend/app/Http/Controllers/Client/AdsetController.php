<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\AuthorizesOwnershipChain;
use App\Http\Controllers\Controller;
use App\Models\Adset;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdsetController extends Controller
{
    use AuthorizesOwnershipChain;

    public function index(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        return response()->json($campaign->adsets()->with('ads')->get());
    }

    public function store(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_name' => 'nullable|string|max:255',
            'radius_km' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,paused',
        ]);

        $adset = $campaign->adsets()->create($validated);

        return response()->json($adset, 201);
    }

    public function show(Request $request, Campaign $campaign, Adset $adset): JsonResponse
    {
        $this->authorizeAdset($request, $campaign, $adset);

        return response()->json($adset->load('ads'));
    }

    public function update(Request $request, Campaign $campaign, Adset $adset): JsonResponse
    {
        $this->authorizeAdset($request, $campaign, $adset);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'location_name' => 'nullable|string|max:255',
            'radius_km' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,paused',
        ]);

        $adset->update($validated);

        return response()->json($adset);
    }

    /**
     * design.json §21 — an adset is only a GROUPING label, so deleting it must NOT
     * destroy its ads. The client says where the ads go:
     *
     *   - `move_ads_to` = another adset of the SAME campaign -> the ads move there;
     *   - omitted                                            -> the ads are left
     *     orphaned in the campaign backlog (`adset_id` null, `campaign_id` kept).
     *
     * Orphaned is not stopped: anything already paid keeps running, an orphan is
     * just harder to find for further actions.
     */
    public function destroy(Request $request, Campaign $campaign, Adset $adset): JsonResponse
    {
        $this->authorizeAdset($request, $campaign, $adset);

        $validated = $request->validate([
            'move_ads_to' => 'nullable|integer',
        ]);

        $target = null;

        if (! empty($validated['move_ads_to'])) {
            // A move target outside this campaign is a cross-chain move: 404, never 403.
            $target = $campaign->adsets()->whereKey($validated['move_ads_to'])->first();
            abort_if($target === null, 404);
            abort_if($target->id === $adset->id, 422, 'Cannot move ads into the adset being deleted.');
        }

        DB::transaction(function () use ($adset, $campaign, $target) {
            // The ads survive either way; `ads.adset_id` is nullOnDelete, but we set
            // the destination explicitly so bookings stay in step in the same commit.
            $adset->ads()->update([
                'adset_id' => $target?->id,
                'campaign_id' => $campaign->id,
            ]);

            $adset->bookings()->update(['adset_id' => $target?->id]);

            $adset->delete();
        });

        return response()->json([
            'message' => $target
                ? 'Adset deleted; its ads were moved.'
                : 'Adset deleted; its ads were left in the campaign backlog.',
            'ads_moved_to' => $target?->id,
        ]);
    }
}
