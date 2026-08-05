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

    // ── §3 (owner 2026-08-04): a collaboration cannot be left by the collaborator ──

    public function test_a_collaborator_cannot_unlink_themselves(): void
    {
        // "no hay formas de desvincular la cuenta de colaboración si alguien la agrega
        // excepto que hablen con soporte" — anti-fraud: the person who did the acting
        // must not be able to delete the record that says they were acting.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client', 'email' => 'ana@x.test']);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/client/collaborators', ['email' => 'ana@x.test', 'role' => 'manager'])
            ->assertStatus(201)
            ->json('id');

        Sanctum::actingAs($helper);

        // 409 and NOT 404: §21 rule 2 sends a broken ownership chain to 404 because a
        // 403 would confirm the row exists — but this caller IS the row's subject and
        // already knows it exists. The refusal is about state, so it says so.
        $response = $this->deleteJson("/api/client/collaborators/{$id}")->assertStatus(409);

        $response->assertJsonPath('error_code', 'CONFLICTING_STATE');
        // Still PENDING, so the truthful answer is not "talk to Support" — it is "you can
        // decline this one". BR-15 only starts once the invitation has been accepted.
        $this->assertStringContainsString('decline', $response->json('message'));

        // And nothing moved.
        $this->assertSame('pending', Collaborator::find($id)->status);
    }

    public function test_a_collaborator_who_has_accepted_still_cannot_unlink_themselves(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $grant = Collaborator::create([
            'account_id' => $owner->account_id,
            'invited_by_user_id' => $owner->id,
            'user_id' => $helper->id,
            'email' => $helper->email,
            'role' => 'manager',
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($helper);
        $response = $this->deleteJson("/api/client/collaborators/{$grant->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        // From `accepted` on, Support is the only truthful answer — and the message says
        // that the capability does not exist yet instead of pointing at a route.
        $this->assertStringContainsString('Support', $response->json('message'));
        $this->assertSame('accepted', $grant->fresh()->status);
    }

    public function test_an_invitee_who_registered_later_is_matched_by_email(): void
    {
        // `collaborators.user_id` is NULL while the invitee has no account yet, so in
        // that window the email is the only link between the row and the human. Without
        // the email arm this person would get a 404 for a row that is plainly theirs.
        $owner = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/client/collaborators', ['email' => 'later@x.test', 'role' => 'publicist'])
            ->assertStatus(201)
            ->json('id');

        $this->assertNull(Collaborator::find($id)->user_id);

        $this->postJson('/api/register', [
            'name' => 'Later',
            'email' => 'Later@x.test', // typed with different case on purpose
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'client',
        ])->assertStatus(201);

        $invitee = User::where('email', 'Later@x.test')->firstOrFail();

        // Registration back-links the waiting invitation (User::booted →
        // Collaborator::linkNewUser) but does NOT accept it: signing up is not the same
        // act as agreeing to operate somebody else's account.
        $this->assertSame($invitee->id, Collaborator::find($id)->user_id);
        $this->assertSame('pending', Collaborator::find($id)->status);

        Sanctum::actingAs($invitee);
        $this->deleteJson("/api/client/collaborators/{$id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');
    }

    public function test_a_stranger_still_gets_404_not_the_conflict_message(): void
    {
        // The 409 is only for the row's own subject. Everyone else stays on §21 rule 2,
        // where 404 is what keeps the existence of the row private.
        $owner = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/client/collaborators', ['email' => 'ana@x.test', 'role' => 'manager'])
            ->json('id');

        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/client/collaborators/{$id}")
            ->assertStatus(404)
            ->assertJsonMissingPath('error_code');
    }

    public function test_the_owner_can_still_revoke_a_collaborator_they_invited_by_their_own_email(): void
    {
        // The guard must not catch the owner. A row on MY account is resolved by the
        // account scope and never reaches it — including this silly self-invite.
        $owner = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/client/collaborators', ['email' => $owner->email, 'role' => 'manager'])
            ->assertStatus(201)
            ->json('id');

        $this->deleteJson("/api/client/collaborators/{$id}")->assertStatus(200);
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
        // 404, not 403 — §21 rule 2 (BR-3): a supervisor of ONE provider account must not
        // be able to walk chat ids and learn which of them belong to a rival account.
        $this->getJson("/api/chats/{$chatIds['other']}")->assertStatus(404);
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
