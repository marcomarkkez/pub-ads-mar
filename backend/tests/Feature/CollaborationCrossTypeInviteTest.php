<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * design.json §3 (UC-19/UC-20 · WALK-6 step 3) — inviting a person whose OWN account is
 * of the other type: a client-account owner hiring a `provider`-role user, and a
 * provider-account owner hiring a `client`-role user.
 *
 * WHY THIS FILE EXISTS. Marco walked WALK-6 by hand on 2026-09-05, invited
 * provider2@pubads.test from client1's account, signed in as provider2 and saw no 📨
 * Invitations entry and no trace of the invitation anywhere ("No veo invitations desde
 * provider2@, algo hice mal?"). The bug turned out to be in the SPA — CollaborationService
 * is `providedIn: 'root'` and its cache flag outlived the session, so the second person to
 * sign in in the same tab was never asked about — and every layer tested here was already
 * correct. That is exactly why it is now pinned:
 *
 *   • the whole cross-type path went through CollaborationInviteFlowTest and
 *     AccountScopeTest untouched. Both files invite same-role users, and the one
 *     provider-side invitee (test_a_provider_can_answer_an_invitation_too) is built with
 *     `Collaborator::create()` rather than through POST /collaborators — so no test asked
 *     whether the ENDPOINT accepts an invitee from the other ecosystem at all.
 *   • when a screen goes quiet, the first suspicion falls on the API. Half a day went into
 *     ruling the API out by hand; these tests are that half day, written down.
 *
 * THE RULE BEING PINNED, and it is one rule stated in two directions: the invitee's own
 * role decides NOTHING here. `Collaborator::rolesFor()` reads `accounts.type`, i.e. the
 * hiring company's, so a provider being hired as a client-side `publicist` is ordinary and
 * a provider being hired as an `installator` BY A CLIENT is refused — refused loudly with
 * a 422 on `role`, never quietly with a grant the chat ACLs would then ignore.
 */
class CollaborationCrossTypeInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    // ── The two directions, end to end ────────────────────────────────────────

    /**
     * The exact walk that was reported broken: a CLIENT account hires a person who is a
     * `provider` in their own right, and that person sees it and walks in.
     */
    public function test_a_client_owner_invites_a_provider_user_who_sees_and_accepts_it(): void
    {
        $owner = User::factory()->create(['role' => 'client', 'name' => 'Carlos Mendez']);
        $invitee = User::factory()->create(['role' => 'provider']);

        Sanctum::actingAs($owner);

        // publicist because the hiring account is a CLIENT one — the invitee being a
        // provider does not move the list a single name (see rolesFor()).
        $id = $this->postJson('/api/collaborators', ['email' => $invitee->email, 'role' => 'publicist'])
            ->assertStatus(201)
            ->json('id');

        // Already registered, so the row is born LINKED. A row carrying only the email
        // would still be answerable (addressedTo() has an email arm), but it would be
        // invisible to every user_id-keyed lookup in the app — `collaborating_on`, the
        // chat ACLs — which is the difference between "not yet accepted" and "silently
        // not there".
        $this->assertSame($invitee->id, Collaborator::find($id)->user_id);

        Sanctum::actingAs($invitee);

        $this->getJson('/api/collaborations')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $id)
            ->assertJsonPath('0.status', 'pending')
            ->assertJsonPath('0.role', 'publicist')
            ->assertJsonPath('0.account.type', 'client')
            ->assertJsonPath('0.invited_by.name', 'Carlos Mendez');

        $this->postJson("/api/collaborations/{$id}/accept")
            ->assertStatus(200)
            ->assertJsonPath('status', 'accepted');

        $this->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('collaborating_on', [$owner->account_id]);
    }

    /** The mirror, which the same rule has to produce with nothing special added. */
    public function test_a_provider_owner_invites_a_client_user_who_sees_and_accepts_it(): void
    {
        $owner = User::factory()->create(['role' => 'provider', 'name' => 'Fernanda Treviño']);
        $invitee = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/collaborators', ['email' => $invitee->email, 'role' => 'installator'])
            ->assertStatus(201)
            ->json('id');

        $this->assertSame($invitee->id, Collaborator::find($id)->user_id);

        Sanctum::actingAs($invitee);

        $this->getJson('/api/collaborations')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $id)
            ->assertJsonPath('0.role', 'installator')
            ->assertJsonPath('0.account.type', 'provider')
            ->assertJsonPath('0.invited_by.name', 'Fernanda Treviño');

        $this->postJson("/api/collaborations/{$id}/accept")
            ->assertStatus(200)
            ->assertJsonPath('status', 'accepted');

        $this->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('collaborating_on', [$owner->account_id]);
    }

    // ── Which list applies, and how a refusal must sound ──────────────────────

    /**
     * The subrole comes from the HIRING account and from nothing else.
     *
     * Both halves matter and they are opposite mistakes. Reading the list off the invitee
     * would let a client account file somebody as an `installator` — the 2026-07-17 bug,
     * a collaborator the chat ACL then refuses with nothing explaining why. Reading it off
     * the caller's habits would let a provider account mint a `publicist`, which
     * Chat::PROVIDER_CHAT_SUBROLES refuses just as silently.
     *
     * And the refusal has to REACH the inviter: 422 on `role`, which the Collaborators
     * screen shows as a toast. An invite that answers 201 and grants something unusable is
     * the failure mode this whole flow keeps producing when it goes wrong quietly.
     */
    public function test_the_subrole_list_follows_the_hiring_account_not_the_invitee(): void
    {
        $clientOwner = User::factory()->create(['role' => 'client']);
        $providerOwner = User::factory()->create(['role' => 'provider']);
        $providerInvitee = User::factory()->create(['role' => 'provider']);
        $clientInvitee = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($clientOwner);

        foreach (Collaborator::PROVIDER_ROLES as $role) {
            $this->postJson('/api/collaborators', ['email' => $providerInvitee->email, 'role' => $role])
                ->assertStatus(422)
                ->assertJsonValidationErrors('role');
        }

        Sanctum::actingAs($providerOwner);

        foreach (Collaborator::CLIENT_ROLES as $role) {
            $this->postJson('/api/collaborators', ['email' => $clientInvitee->email, 'role' => $role])
                ->assertStatus(422)
                ->assertJsonValidationErrors('role');
        }

        // Refused means refused: no half-written grant left behind for either pair.
        $this->assertSame(0, Collaborator::count());
    }

    // ── Owning and collaborating stack on one person ──────────────────────────

    /**
     * Accepting an invitation into somebody else's account does not cost you your own.
     *
     * This is the fact both sidebar entries are gated on, and they are gated on DIFFERENT
     * halves of it: 👥 Collaborators on `is_owner` (my company), 📨 Invitations on the list
     * being non-empty (somebody else's). A provider who accepts a client's invitation has
     * to end up with both, or the menu would take away the account they still own the
     * moment they agreed to help elsewhere.
     */
    public function test_accepting_elsewhere_leaves_the_invitees_own_account_intact(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $invitee = User::factory()->create(['role' => 'provider']);
        $ownAccountId = $invitee->account_id;

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/collaborators', ['email' => $invitee->email, 'role' => 'manager'])
            ->assertStatus(201)
            ->json('id');

        Sanctum::actingAs($invitee);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200);

        $this->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('account_id', $ownAccountId)
            ->assertJsonPath('is_owner', true)
            ->assertJsonPath('collaborating_on', [$owner->account_id]);

        // …and their own company still hires from the PROVIDER list, unaffected by the
        // client-side subrole they now hold somewhere else.
        $this->postJson('/api/collaborators', ['email' => 'crew@x.test', 'role' => 'installator'])
            ->assertStatus(201);
        $this->postJson('/api/collaborators', ['email' => 'oficina@x.test', 'role' => 'publicist'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    /**
     * An accepted collaboration is still listed. The 📨 entry exists while the list is
     * non-empty — not while something is pending — because "which accounts may I act
     * inside" is a standing fact and this is the only screen that states it. If accepting
     * emptied the list, the entry would vanish at the exact moment it became true.
     */
    public function test_an_accepted_cross_type_collaboration_stays_on_the_list(): void
    {
        $owner = User::factory()->create(['role' => 'provider']);
        $invitee = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/collaborators', ['email' => $invitee->email, 'role' => 'sales'])
            ->assertStatus(201)
            ->json('id');

        Sanctum::actingAs($invitee);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200);

        $this->getJson('/api/collaborations')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $id)
            ->assertJsonPath('0.status', 'accepted');
    }
}
