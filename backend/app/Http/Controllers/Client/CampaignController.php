<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\AuthorizesOwnershipChain;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The campaign is the ROOT of the ownership chain (§21 · UC-43), so it authorizes
 * through the same trait as every link below it — {@see AuthorizesOwnershipChain}.
 *
 * It did not, until now: this controller answered 403 on a foreign campaign while
 * `/client/campaigns/{c}/adsets` answered 404 for the very same campaign. The leak
 * therefore depended on WHICH door the caller knocked at, and the test that guards
 * rule 2 (OwnershipChainTest::test_foreign_campaign_is_404_not_403) only knocked at
 * the nested one — so the root looked audited and was not.
 */
class CampaignController extends Controller
{
    use AuthorizesOwnershipChain;

    public function index(Request $request): JsonResponse
    {
        $campaigns = $request->user()->campaigns()->with('adsets')->latest()->get();

        return response()->json($campaigns);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:draft,active,paused,completed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
        ]);

        $campaign = $request->user()->campaigns()->create($validated);

        return response()->json($campaign, 201);
    }

    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        // 404, never 403 — §21 rule 2 (BR-3): a 403 confirms the row exists, which is
        // enough to enumerate another account's ids. "Not yours" and "does not exist"
        // must be indistinguishable to a stranger.
        $this->authorizeCampaign($request, $campaign);

        return response()->json($campaign->load('adsets.ads'));
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign); // 404, never 403 — §21 rule 2 (BR-3).

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:draft,active,paused,completed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
        ]);

        $campaign->update($validated);

        return response()->json($campaign);
    }

    public function destroy(Request $request, Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($request, $campaign); // 404, never 403 — §21 rule 2 (BR-3).

        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted.']);
    }
}
