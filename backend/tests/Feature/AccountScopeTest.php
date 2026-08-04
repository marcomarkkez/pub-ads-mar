<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Collaborator;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * design.json §3 (UC-19, AC-accounts-01/02, AC-collab-03/04) — the Account object
 * and the account-scoped Collaborators screen.
 */
class AccountScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    // ── §3: 1 user = 1 account ────────────────────────────────────────────────

    public function test_every_client_and_provider_gets_exactly_one_account(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);

        $this->assertNotNull($client->account);
        $this->assertNotNull($provider->account);
        $this->assertSame(Account::TYPE_CLIENT, $client->account->type);
        $this->assertSame(Account::TYPE_PROVIDER, $provider->account->type);

        // 1:1 in BOTH directions: the account resolves back to exactly this user.
        $this->assertSame($client->id, $client->account->owner->id);
        $this->assertSame(1, $client->account->users()->count());

        $this->assertNotSame($client->account_id, $provider->account_id);
        $this->assertSame(2, Account::count());
    }

    public function test_staff_users_have_no_account(): void
    {
        // accounts.type is client|provider — staff own no campaigns and no spaces,
        // so an account for them would be a row that means nothing.
        foreach (['admin', 'support', 'payments'] as $role) {
            $this->assertNull(User::factory()->create(['role' => $role])->account);
        }

        $this->assertSame(0, Account::count());
    }

    public function test_the_database_refuses_a_second_owner_on_one_account(): void
    {
        // The MVP 1:1 is a UNIQUE INDEX, not a convention. Dropping that one index
        // is the whole of "several owner-users share an account" (owner 2026-08-01).
        $owner = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('users')->where('id', $other->id)->update(['account_id' => $owner->account_id]);
    }

    public function test_a_campaign_is_filed_into_its_owners_account(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($client);

        $id = $this->postJson('/api/client/campaigns', ['name' => 'Verano'])
            ->assertStatus(201)
            ->json('id');

        $campaign = Campaign::findOrFail($id);

        $this->assertSame($client->account_id, $campaign->account_id);
        $this->assertSame($client->id, $campaign->user_id);
    }

    // ── §3: collaborators are account-scoped ─────────────────────────────────

    public function test_the_same_email_cannot_be_invited_twice_to_one_account(): void
    {
        // THE BUG: under unique(campaign_id, email) this person became one grant per
        // campaign, and revoking "them" revoked exactly one of those rows.
        $owner = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);

        $this->postJson('/api/client/campaigns', ['name' => 'A'])->assertStatus(201);
        $this->postJson('/api/client/campaigns', ['name' => 'B'])->assertStatus(201);

        $this->postJson('/api/client/collaborators', ['email' => 'ana@x.test', 'role' => 'manager'])
            ->assertStatus(201);

        // 409, not 422: the payload is fine, the account's state is what refuses.
        $this->postJson('/api/client/collaborators', ['email' => 'ana@x.test', 'role' => 'publicist'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'ALREADY_EXISTS');

        $this->assertSame(1, Collaborator::where('email', 'ana@x.test')->count());
        $this->assertSame('manager', Collaborator::where('email', 'ana@x.test')->value('role'));
    }

    public function test_the_same_email_can_collaborate_on_two_different_accounts(): void
    {
        $first = User::factory()->create(['role' => 'client']);
        $second = User::factory()->create(['role' => 'client']);

        foreach ([$first, $second] as $owner) {
            Sanctum::actingAs($owner);
            $this->postJson('/api/client/collaborators', ['email' => 'ana@x.test', 'role' => 'manager'])
                ->assertStatus(201);
        }

        $this->assertSame(2, Collaborator::where('email', 'ana@x.test')->count());
    }

    public function test_a_collaborator_is_listed_for_its_account_only(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);
        $this->postJson('/api/client/collaborators', ['email' => 'ana@x.test', 'role' => 'manager'])
            ->assertStatus(201);

        $this->getJson('/api/client/collaborators')->assertStatus(200)->assertJsonCount(1);

        Sanctum::actingAs($stranger);
        $this->getJson('/api/client/collaborators')->assertStatus(200)->assertJsonCount(0);
    }

    public function test_another_accounts_collaborator_cannot_be_revoked(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/client/collaborators', ['email' => 'ana@x.test', 'role' => 'manager'])
            ->json('id');

        // §21 rule 2 — a break in the chain is 404, never 403: a 403 would confirm
        // the collaborator exists.
        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/client/collaborators/{$id}")->assertStatus(404);

        $this->assertSame('pending', Collaborator::find($id)->status);
    }

    public function test_the_owner_revokes_a_collaborator(): void
    {
        $owner = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/client/collaborators', ['email' => 'ana@x.test', 'role' => 'manager'])
            ->json('id');

        $this->deleteJson("/api/client/collaborators/{$id}")->assertStatus(200);

        // Revoked, not deleted — the grant stays as the record of who had access.
        $this->assertSame('revoked', Collaborator::find($id)->status);
    }

    public function test_installator_is_refused_on_the_client_side(): void
    {
        $owner = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);

        // §3 — installator is a PROVIDER-side subrole; a malformed payload is 422.
        $this->postJson('/api/client/collaborators', ['email' => 'i@x.test', 'role' => 'installator'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_a_provider_collaborator_does_not_reach_another_providers_chats(): void
    {
        // Before §3 gave collaborators an account_id, Chat::userOnProviderSide could
        // only ask "does this user hold a sales/supervisor grant ANYWHERE" — so one
        // grant on one provider opened every provider's conversations.
        $mine = User::factory()->create(['role' => 'provider']);
        $other = User::factory()->create(['role' => 'provider']);
        $supervisor = User::factory()->create(['role' => 'provider']);
        $client = User::factory()->create(['role' => 'client']);

        Collaborator::create([
            'account_id' => $mine->account_id,
            'invited_by_user_id' => $mine->id,
            'user_id' => $supervisor->id,
            'email' => $supervisor->email,
            'role' => 'supervisor',
            'status' => 'accepted',
        ]);

        $chatIds = [];

        foreach (['mine' => $mine, 'other' => $other] as $key => $provider) {
            $chatIds[$key] = \App\Models\Chat::create([
                'opened_by_user_id' => $client->id,
                'client_user_id' => $client->id,
                'provider_user_id' => $provider->id,
                'status' => \App\Models\Chat::STATUS_OPEN,
            ])->id;
        }

        Sanctum::actingAs($supervisor);

        $this->getJson("/api/chats/{$chatIds['mine']}")->assertStatus(200);
        $this->getJson("/api/chats/{$chatIds['other']}")->assertStatus(403);
    }

    public function test_the_campaign_nested_collaborator_routes_are_gone(): void
    {
        $owner = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);
        $campaignId = $this->postJson('/api/client/campaigns', ['name' => 'A'])->json('id');

        $this->getJson("/api/client/campaigns/{$campaignId}/collaborators")->assertStatus(404);
        $this->postJson("/api/client/campaigns/{$campaignId}/collaborators", [
            'email' => 'ana@x.test',
            'role' => 'manager',
        ])->assertStatus(404);
    }
}
