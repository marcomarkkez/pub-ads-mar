<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\AuthorizesOwnershipChain;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Adset;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BacklogController extends Controller
{
    use AuthorizesOwnershipChain;

    /**
     * C03: Attach selected spaces to a campaign's backlog as orphan ads
     * (adset_id = null). Each orphan carries campaign_id + space_id and a
     * provider snapshot, so it can later be bulk-moved into an adset.
     *
     * Every ad minted here takes its `space_id` from the space it came from, which
     * is how §21 rule 4's invariant is satisfied at the backlog origin.
     */
    public function addBacklog(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

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
        $this->authorizeCampaign($request, $campaign);

        $orphans = Ad::where('campaign_id', $campaign->id)
            ->whereNull('adset_id')
            ->with(['space.availabilities', 'space.photos'])
            ->latest()
            ->get();

        return response()->json($orphans);
    }

    /**
     * C03 + design.json §21 rule 3 (move matrix): bulk-move ads into an adset.
     *
     * Allowed WITHIN one account, across campaigns and adsets; forbidden across
     * accounts (404, never 403 — a 403 would confirm the object exists). The move
     * carries its dependents: a booked ad's `bookings.adset_id` is updated in the
     * SAME transaction, so a booking can never point at an adset its ad has left.
     *
     * `adset_id` omitted creates a new adset under this campaign (default flow).
     */
    public function moveToAdset(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign);

        $validated = $request->validate([
            'ad_ids' => 'required|array|min:1',
            'ad_ids.*' => 'integer|exists:ads,id',
            'adset_id' => 'nullable|integer|exists:adsets,id',
        ]);

        // Source ads must hang off THIS campaign — either as backlog orphans or as
        // members of one of its adsets. Anything else is outside the caller's chain.
        $ads = Ad::whereIn('id', $validated['ad_ids'])
            ->where(function ($query) use ($campaign) {
                $query->where(fn ($q) => $q->whereNull('adset_id')->where('campaign_id', $campaign->id))
                    ->orWhereIn('adset_id', $campaign->adsets()->select('id'));
            })
            ->with('space')
            ->get();

        // A partial match means at least one id was outside the chain — 404 rather
        // than a 422 that would tell the caller which ids exist.
        abort_if($ads->count() !== count(array_unique($validated['ad_ids'])), 404);

        if (! empty($validated['adset_id'])) {
            $adset = Adset::find($validated['adset_id']);
            abort_if($adset === null, 404);

            // The destination may live in ANOTHER campaign of the SAME owner (the
            // matrix allows that); it may never live in another account.
            abort_unless($this->ownsCampaign($request, $adset->campaign), 404);
        } else {
            $firstSpace = $ads->first()->space;
            $adset = $campaign->adsets()->create([
                'name' => 'Adset ' . ($campaign->adsets()->count() + 1),
                'latitude' => $firstSpace?->latitude,
                'longitude' => $firstSpace?->longitude,
                'location_name' => $firstSpace?->location_text,
                'status' => 'active',
            ]);
        }

        DB::transaction(function () use ($ads, $adset) {
            $adIds = $ads->pluck('id')->all();

            Ad::whereIn('id', $adIds)->update([
                'adset_id' => $adset->id,
                // Moving across campaigns re-parents the ad's campaign linkage too.
                'campaign_id' => $adset->campaign_id,
            ]);

            // §21 rule 3 — dependents move in the same commit. `bookings` has no
            // campaign_id column; the campaign is derived through the adset.
            Booking::whereIn('ad_id', $adIds)->update(['adset_id' => $adset->id]);
        });

        return response()->json($adset->load('ads'), 201);
    }
}
