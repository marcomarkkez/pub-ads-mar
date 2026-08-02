<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Adset;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * design.md §21 / UC-43 — Object Ownership & Access Chain.
 *
 * Every endpoint authorizes by the WHOLE chain (user -> campaign -> adset -> ad),
 * never by the parent alone. The IDOR these cover: a caller passing their OWN
 * campaign id together with a FOREIGN adset/ad id used to be allowed through,
 * because only the campaign was checked.
 *
 * Rule 2 is asserted throughout: a break in the chain is 404, never 403 — a 403
 * would confirm the object exists and leak its existence.
 */
class OwnershipChainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Builds one client with a full campaign -> adset -> ad chain.
     */
    private function chainFor(User $owner, string $label = 'A'): array
    {
        $provider = User::factory()->create(['role' => 'provider']);

        $space = Space::create([
            'user_id' => $provider->id,
            'name' => "Space {$label}",
            'latitude' => 25.6597,
            'longitude' => -100.4023,
        ]);

        $campaign = Campaign::create([
            'user_id' => $owner->id,
            'name' => "Campaign {$label}",
            'status' => 'active',
        ]);

        $adset = $campaign->adsets()->create([
            'name' => "Adset {$label}",
            'status' => 'active',
        ]);

        $ad = $adset->ads()->create([
            'space_id' => $space->id,
            'provider_user_id' => $provider->id,
            'name' => "Ad {$label}",
            'media_type' => 'image',
            'status' => 'draft',
        ]);
        $ad->campaign_id = $campaign->id;
        $ad->save();

        return compact('provider', 'space', 'campaign', 'adset', 'ad');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    // ── Rule 1 + 2: the chain is checked link by link, misses are 404 ──────────

    public function test_own_full_chain_is_reachable(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $c = $this->chainFor($client);

        Sanctum::actingAs($client);

        $this->getJson("/api/client/campaigns/{$c['campaign']->id}/adsets/{$c['adset']->id}")
            ->assertStatus(200);

        $this->getJson("/api/client/campaigns/{$c['campaign']->id}/adsets/{$c['adset']->id}/ads/{$c['ad']->id}")
            ->assertStatus(200);
    }

    /**
     * THE IDOR: own campaign + another account's adset.
     */
    public function test_foreign_adset_through_own_campaign_is_404(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);

        $mine = $this->chainFor($client, 'mine');
        $theirs = $this->chainFor($stranger, 'theirs');

        Sanctum::actingAs($client);

        $this->getJson("/api/client/campaigns/{$mine['campaign']->id}/adsets/{$theirs['adset']->id}")
            ->assertStatus(404);

        $this->putJson("/api/client/campaigns/{$mine['campaign']->id}/adsets/{$theirs['adset']->id}", ['name' => 'pwned'])
            ->assertStatus(404);

        $this->deleteJson("/api/client/campaigns/{$mine['campaign']->id}/adsets/{$theirs['adset']->id}")
            ->assertStatus(404);

        $this->assertSame('Adset theirs', $theirs['adset']->fresh()->name);
    }

    /**
     * THE IDOR, one link deeper: own campaign + own adset + another account's ad.
     */
    public function test_foreign_ad_through_own_chain_is_404(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);

        $mine = $this->chainFor($client, 'mine');
        $theirs = $this->chainFor($stranger, 'theirs');

        Sanctum::actingAs($client);

        $base = "/api/client/campaigns/{$mine['campaign']->id}/adsets/{$mine['adset']->id}/ads/{$theirs['ad']->id}";

        $this->getJson($base)->assertStatus(404);
        $this->putJson($base, ['name' => 'pwned'])->assertStatus(404);
        $this->deleteJson($base)->assertStatus(404);

        $this->assertSame('Ad theirs', $theirs['ad']->fresh()->name);
    }

    /**
     * A chain break INSIDE one account is still a break: campaign B + adset of campaign A.
     */
    public function test_own_adset_under_the_wrong_own_campaign_is_404(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $a = $this->chainFor($client, 'A');
        $b = $this->chainFor($client, 'B');

        Sanctum::actingAs($client);

        $this->getJson("/api/client/campaigns/{$b['campaign']->id}/adsets/{$a['adset']->id}")
            ->assertStatus(404);
    }

    public function test_foreign_campaign_is_404_not_403(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);
        $theirs = $this->chainFor($stranger, 'theirs');

        Sanctum::actingAs($client);

        // 403 would confirm the campaign exists.
        $this->getJson("/api/client/campaigns/{$theirs['campaign']->id}/adsets")
            ->assertStatus(404);
    }

    // ── Rule 4: the space_id invariant ────────────────────────────────────────

    public function test_ad_cannot_be_created_without_a_space(): void
    {
        Storage::fake('public');

        $client = User::factory()->create(['role' => 'client']);
        $c = $this->chainFor($client);

        Sanctum::actingAs($client);

        $this->postJson("/api/client/campaigns/{$c['campaign']->id}/adsets/{$c['adset']->id}/ads", [
            'name' => 'Space-less',
            'media_type' => 'image',
            'file' => UploadedFile::fake()->image('creative.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors('space_id');
    }

    public function test_ad_cannot_be_stripped_of_its_space_on_update(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $c = $this->chainFor($client);

        Sanctum::actingAs($client);

        $this->putJson(
            "/api/client/campaigns/{$c['campaign']->id}/adsets/{$c['adset']->id}/ads/{$c['ad']->id}",
            ['space_id' => null]
        )->assertStatus(422)->assertJsonValidationErrors('space_id');

        $this->assertNotNull($c['ad']->fresh()->space_id);
    }

    // ── Rule 3: move guardrails ───────────────────────────────────────────────

    public function test_move_refuses_ads_from_another_account(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);

        $mine = $this->chainFor($client, 'mine');
        $theirs = $this->chainFor($stranger, 'theirs');

        Sanctum::actingAs($client);

        $this->postJson("/api/client/campaigns/{$mine['campaign']->id}/adsets/move", [
            'ad_ids' => [$theirs['ad']->id],
            'adset_id' => $mine['adset']->id,
        ])->assertStatus(404);

        $this->assertSame($theirs['adset']->id, $theirs['ad']->fresh()->adset_id);
    }

    public function test_move_refuses_a_destination_in_another_account(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);

        $mine = $this->chainFor($client, 'mine');
        $theirs = $this->chainFor($stranger, 'theirs');

        Sanctum::actingAs($client);

        $this->postJson("/api/client/campaigns/{$mine['campaign']->id}/adsets/move", [
            'ad_ids' => [$mine['ad']->id],
            'adset_id' => $theirs['adset']->id,
        ])->assertStatus(404);

        $this->assertSame($mine['adset']->id, $mine['ad']->fresh()->adset_id);
    }

    /**
     * §21 rule 3 — a move carries its dependents in the SAME transaction, so a
     * booking can never point at an adset its ad has left.
     */
    public function test_move_carries_the_bookings_of_a_booked_ad(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $a = $this->chainFor($client, 'A');
        $b = $this->chainFor($client, 'B');

        $booking = Booking::create([
            'client_user_id' => $client->id,
            'space_id' => $a['space']->id,
            'ad_id' => $a['ad']->id,
            'adset_id' => $a['adset']->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'total_price' => 500,
            'status' => 'waiting_proof',
        ]);

        Sanctum::actingAs($client);

        // Same account, ACROSS campaigns — allowed by the move matrix.
        $this->postJson("/api/client/campaigns/{$a['campaign']->id}/adsets/move", [
            'ad_ids' => [$a['ad']->id],
            'adset_id' => $b['adset']->id,
        ])->assertStatus(201);

        $this->assertSame($b['adset']->id, $a['ad']->fresh()->adset_id);
        $this->assertSame($b['campaign']->id, $a['ad']->fresh()->campaign_id);
        $this->assertSame($b['adset']->id, $booking->fresh()->adset_id);
    }

    // ── Adset deletion: a grouping label, not an owner ────────────────────────

    public function test_deleting_an_adset_leaves_its_ads_orphaned_not_destroyed(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $c = $this->chainFor($client);

        Sanctum::actingAs($client);

        $this->deleteJson("/api/client/campaigns/{$c['campaign']->id}/adsets/{$c['adset']->id}")
            ->assertStatus(200);

        $ad = Ad::find($c['ad']->id);
        $this->assertNotNull($ad, 'deleting an adset must not destroy its ads');
        $this->assertNull($ad->adset_id);
        $this->assertSame($c['campaign']->id, $ad->campaign_id);
        $this->assertNull(Adset::find($c['adset']->id));
    }

    public function test_deleting_an_adset_can_move_its_ads_to_a_sibling(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $c = $this->chainFor($client);
        $sibling = $c['campaign']->adsets()->create(['name' => 'Sibling', 'status' => 'active']);

        Sanctum::actingAs($client);

        $this->deleteJson(
            "/api/client/campaigns/{$c['campaign']->id}/adsets/{$c['adset']->id}",
            ['move_ads_to' => $sibling->id]
        )->assertStatus(200);

        $this->assertSame($sibling->id, $c['ad']->fresh()->adset_id);
    }

    public function test_deleting_an_adset_cannot_dump_its_ads_into_another_account(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);

        $mine = $this->chainFor($client, 'mine');
        $theirs = $this->chainFor($stranger, 'theirs');

        Sanctum::actingAs($client);

        $this->deleteJson(
            "/api/client/campaigns/{$mine['campaign']->id}/adsets/{$mine['adset']->id}",
            ['move_ads_to' => $theirs['adset']->id]
        )->assertStatus(404);

        // The adset survives a refused delete.
        $this->assertNotNull(Adset::find($mine['adset']->id));
    }
}
