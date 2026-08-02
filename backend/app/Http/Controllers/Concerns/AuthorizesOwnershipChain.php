<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Ad;
use App\Models\Adset;
use App\Models\Campaign;
use Illuminate\Http\Request;

/**
 * design.json §21 (UC-43) — Object Ownership & Access Chain.
 *
 * Every screen and endpoint authorizes by the WHOLE chain
 * (`user -> account -> campaign -> adset -> ad`), never by the parent alone.
 * Verifying only "the campaign is mine" was the IDOR found on
 * `/client/campaigns/{c}/adsets/{a}` and `.../ads/{ad}`.
 *
 * Two rules are baked in here so no controller can forget them:
 *
 *  1. Each link is checked explicitly, on top of the route's `->scopeBindings()`.
 *  2. A miss is **404, never 403** — a 403 confirms the object exists and leaks
 *     its existence to a caller from another account.
 *
 * NOTE — the `accounts` layer does not exist yet (no `accounts` table, no
 * `account_id` on `campaigns`), so the ownership ROOT is currently
 * `campaigns.user_id`. That root is resolved in exactly one place,
 * {@see self::ownsCampaign()}, so the eventual switch to `account_id` is a
 * one-method change and every caller inherits it.
 */
trait AuthorizesOwnershipChain
{
    /**
     * The ownership root. Change this one method when the accounts layer lands.
     */
    protected function ownsCampaign(Request $request, Campaign $campaign): bool
    {
        return $campaign->user_id === $request->user()->id;
    }

    /**
     * Assert the caller owns the campaign — the first link of the chain.
     */
    protected function authorizeCampaign(Request $request, Campaign $campaign): void
    {
        abort_unless($this->ownsCampaign($request, $campaign), 404);
    }

    /**
     * Assert `user -> campaign -> adset`. The adset must hang off THIS campaign;
     * a caller passing their own campaign with a foreign adset id gets a 404.
     */
    protected function authorizeAdset(Request $request, Campaign $campaign, Adset $adset): void
    {
        $this->authorizeCampaign($request, $campaign);

        abort_unless($adset->campaign_id === $campaign->id, 404);
    }

    /**
     * Assert the full `user -> campaign -> adset -> ad` chain.
     */
    protected function authorizeAd(Request $request, Campaign $campaign, Adset $adset, Ad $ad): void
    {
        $this->authorizeAdset($request, $campaign, $adset);

        abort_unless($ad->adset_id === $adset->id, 404);
    }

    /**
     * Assert an ad belongs to the caller's campaign either as a member of one of
     * its adsets or as a backlog orphan (`adset_id` null + `campaign_id` set).
     * Used by the move/backlog paths, which address ads campaign-directly.
     */
    protected function authorizeCampaignAd(Request $request, Campaign $campaign, Ad $ad): void
    {
        $this->authorizeCampaign($request, $campaign);

        $belongs = $ad->adset_id === null
            ? $ad->campaign_id === $campaign->id
            : $campaign->adsets()->whereKey($ad->adset_id)->exists();

        abort_unless($belongs, 404);
    }
}
