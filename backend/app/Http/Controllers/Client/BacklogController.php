<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Campaign;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BacklogController extends Controller
{
    /**
     * C03: Attach selected spaces to a campaign's backlog as orphan ads
     * (adset_id = null). Each orphan carries campaign_id + space_id and a
     * provider snapshot, so it can later be bulk-moved into an adset.
     */
    public function addBacklog(Request $request, Campaign $campaign): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'space_ids' => 'required|array|min:1',
            'space_ids.*' => 'integer|exists:spaces,id',
        ]);

        $spaces = Space::whereIn('id', $validated['space_ids'])->get();

        $created = [];
        foreach ($spaces as $space) {
            $ad = new Ad([
                'space_id' => $space->id,
                'provider_user_id' => $space->user_id,
                'name' => $space->name,
                'media_type' => 'image',
                'status' => 'draft',
            ]);
            $ad->adset_id = null;
            // campaign_id is not in $fillable, set explicitly.
            $ad->campaign_id = $campaign->id;
            $ad->save();

            $created[] = $ad->load('space');
        }

        return response()->json($created, 201);
    }

    /**
     * C03: List orphan ads — placed in the campaign backlog but not yet in any adset.
     */
    public function listOrphans(Request $request, Campaign $campaign): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $orphans = Ad::where('campaign_id', $campaign->id)
            ->whereNull('adset_id')
            ->with(['space.availabilities', 'space.photos'])
            ->latest()
            ->get();

        return response()->json($orphans);
    }

    /**
     * C03: Bulk-move orphan ads into a freshly-created adset under the campaign,
     * so they become bookable. Returns the new adset with its ads.
     */
    public function moveToAdset(Request $request, Campaign $campaign): JsonResponse
    {
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // design.md → Filters & backlog: orphans can be bulk-moved into an adset,
        // either a NEW adset (adset_id omitted) or an EXISTING one (adset_id given).
        $validated = $request->validate([
            'ad_ids' => 'required|array|min:1',
            'ad_ids.*' => 'integer|exists:ads,id',
            'adset_id' => 'nullable|integer|exists:adsets,id',
        ]);

        // Only move orphans that actually belong to this campaign.
        $ads = Ad::whereIn('id', $validated['ad_ids'])
            ->where('campaign_id', $campaign->id)
            ->whereNull('adset_id')
            ->with('space')
            ->get();

        if ($ads->isEmpty()) {
            return response()->json(['message' => 'No matching orphan ads to move.'], 422);
        }

        if (!empty($validated['adset_id'])) {
            // Move into an existing adset — must belong to this campaign.
            $adset = $campaign->adsets()->find($validated['adset_id']);
            if (!$adset) {
                return response()->json(['message' => 'Adset does not belong to this campaign.'], 422);
            }
        } else {
            // Create a new adset (default behavior).
            $firstSpace = $ads->first()->space;
            $adset = $campaign->adsets()->create([
                'name' => 'Adset ' . ($campaign->adsets()->count() + 1),
                'latitude' => $firstSpace?->latitude,
                'longitude' => $firstSpace?->longitude,
                'location_name' => $firstSpace?->location_text,
                'status' => 'active',
            ]);
        }

        foreach ($ads as $ad) {
            $ad->adset_id = $adset->id;
            $ad->save();
        }

        return response()->json($adset->load('ads'), 201);
    }
}
