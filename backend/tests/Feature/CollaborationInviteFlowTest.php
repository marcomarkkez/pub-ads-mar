<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Collaborator;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * design.json §3 (UC-19/UC-20 · WALK-6 step 3) — the receiving end of an invitation.
 *
 * THE HOLE THIS FILLS: `collaborators.status = 'accepted'` was read everywhere
 * (`User::accountContext()`, the chat ACLs) and written by nothing except the seeders.
 * An invited person therefore stayed `pending` forever, `collaborating_on` was empty for
 * every real user, and WALK-6 step 3 — "invitar a un colaborador y aceptar la invitación
 * desde la otra sesión" — could not be walked.
 */
class CollaborationInviteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** The owner invites $email and returns the new collaborator id. */
    private function invite(User $owner, string $email, string $role = 'manager'): int
    {
        Sanctum::actingAs($owner);

        return $this->postJson('/api/client/collaborators', ['email' => $email, 'role' => $role])
            ->assertStatus(201)
            ->json('id');
    }

    // ── The round trip ────────────────────────────────────────────────────────

    public function test_accepting_puts_the_account_into_collaborating_on(): void
    {
        // The whole point of the endpoint: a value only the seeder used to write is now
        // written by the person it describes, and /me sees it.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email);

        Sanctum::actingAs($helper);

        // Before: invited, but collaborating on nothing.
        $this->getJson('/api/me')->assertStatus(200)->assertJsonPath('collaborating_on', []);

        $this->postJson("/api/collaborations/{$id}/accept")
            ->assertStatus(200)
            ->assertJsonPath('status', 'accepted');

        $this->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('collaborating_on', [$owner->account_id]);

        // …and the same context arrives at login, without a reload.
        $this->postJson('/api/login', ['email' => $helper->email, 'password' => 'password'])
            ->assertStatus(200)
            ->assertJsonPath('collaborating_on', [$owner->account_id]);
    }

    public function test_the_invitation_list_shows_who_is_inviting_and_to_what(): void
    {
        $owner = User::factory()->create(['role' => 'client', 'name' => 'Dueño']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email, 'publicist');

        Sanctum::actingAs($helper);
        $list = $this->getJson('/api/collaborations')->assertStatus(200)->assertJsonCount(1);

        $list->assertJsonPath('0.id', $id);
        $list->assertJsonPath('0.role', 'publicist');
        $list->assertJsonPath('0.status', 'pending');
        $list->assertJsonPath('0.account.id', $owner->account_id);
        $list->assertJsonPath('0.invited_by.name', 'Dueño');

        // The inviter is id+name only — an invitation screen has no business handing out
        // the owner's email, phone or address.
        $this->assertSame(['id', 'name'], array_keys($list->json('0.invited_by')));
    }

    public function test_an_invitation_addressed_to_someone_else_is_invisible_and_404(): void
    {
        // §21 rule 2 — a stranger gets 404, never 403: the 403 would confirm the row.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);
        $stranger = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email);

        Sanctum::actingAs($stranger);
        $this->getJson('/api/collaborations')->assertStatus(200)->assertJsonCount(0);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(404);
        $this->postJson("/api/collaborations/{$id}/decline")->assertStatus(404);

        $this->assertSame('pending', Collaborator::find($id)->status);
    }

    public function test_the_owner_cannot_accept_on_the_invitees_behalf(): void
    {
        // The bound is "this invitation names me", not "this row is on my account" — so
        // owning the account grants nothing here. Consent is the invitee's to give.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email);

        Sanctum::actingAs($owner);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(404);

        $this->assertSame('pending', Collaborator::find($id)->status);
    }

    // ── Idempotency ───────────────────────────────────────────────────────────

    public function test_accepting_twice_changes_nothing_and_logs_once(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email);

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200)
            ->assertJsonPath('status', 'accepted');

        $this->assertSame(1, Collaborator::where('email', $helper->email)->count());
        $this->assertSame([$owner->account_id], $helper->fresh()->accountContext()['collaborating_on']->all());

        // The second call is a non-event, and an audit log that records non-events is one
        // nobody can read.
        $this->assertSame(1, AuditLog::where('action', 'accept')->where('target_id', $id)->count());
    }

    public function test_accepting_is_audited_as_the_moment_access_was_granted(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email);

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200);

        $entry = AuditLog::where('action', 'accept')->where('target_id', $id)->firstOrFail();

        $this->assertSame('collaborators', $entry->target_type);
        $this->assertSame($helper->id, $entry->actor_id);
        $this->assertSame('pending', $entry->before['status']);
        $this->assertSame('accepted', $entry->after['status']);
    }

    // ── Declining (and why it is not a BR-15 hole) ────────────────────────────

    public function test_a_pending_invitation_can_be_declined(): void
    {
        // Declining is never having entered. BR-15 blocks LEAVING an account you already
        // acted inside; a pending invitation records no acting at all.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email);

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$id}/decline")
            ->assertStatus(200)
            ->assertJsonPath('status', 'declined');

        $this->assertSame([], $helper->fresh()->accountContext()['collaborating_on']->all());
        $this->assertSame(1, AuditLog::where('action', 'decline')->where('target_id', $id)->count());

        // Idempotent, like accept.
        $this->postJson("/api/collaborations/{$id}/decline")->assertStatus(200);
        $this->assertSame(1, AuditLog::where('action', 'decline')->where('target_id', $id)->count());
    }

    public function test_declining_after_accepting_is_409_and_points_at_support(): void
    {
        // THE LINE BR-15 DRAWS: once accepted, `decline` would be the self-unlink the
        // owner ruling forbids, so it answers exactly like the owner's DELETE does.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email);

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200);

        $response = $this->postJson("/api/collaborations/{$id}/decline")->assertStatus(409);

        $response->assertJsonPath('error_code', 'CONFLICTING_STATE');
        $this->assertStringContainsString('Support', $response->json('message'));

        // 409 and not 404: the caller is the row's own subject, so nothing is protected
        // by pretending the row is not there.
        $this->assertSame('accepted', Collaborator::find($id)->status);
        $this->assertSame([$owner->account_id], $helper->fresh()->accountContext()['collaborating_on']->all());
    }

    public function test_a_declined_invitation_cannot_be_accepted_by_the_invitee(): void
    {
        // Re-opening a closed invitation is the OWNER's move. If the invitee could do it,
        // a decline would be a button the inviter cannot take away.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email);

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$id}/decline")->assertStatus(200);

        $this->postJson("/api/collaborations/{$id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        $this->assertSame('declined', Collaborator::find($id)->status);
    }

    public function test_a_revoked_grant_cannot_be_accepted_back(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email);

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/client/collaborators/{$id}")->assertStatus(200);

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        $this->assertSame([], $helper->fresh()->accountContext()['collaborating_on']->all());
    }

    // ── A decline is not a ban ────────────────────────────────────────────────

    public function test_the_owner_can_invite_again_after_a_decline(): void
    {
        // unique(account_id, email) means the row IS the person, so a re-invitation must
        // reuse it. Without that, one "no thanks" would lock the pair out forever: the
        // owner would hit ALREADY_EXISTS and the invitee cannot re-open their own row.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email, 'publicist');

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$id}/decline")->assertStatus(200);

        // Same row, new offer — the subrole is re-read from the new payload.
        $again = $this->invite($owner, $helper->email, 'manager');

        $this->assertSame($id, $again);
        $this->assertSame(1, Collaborator::where('email', $helper->email)->count());
        $this->assertSame('pending', Collaborator::find($id)->status);
        $this->assertSame('manager', Collaborator::find($id)->role);

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200);
    }

    public function test_a_live_invitation_still_refuses_a_second_one(): void
    {
        // The revive path must not swallow the duplicate-invite guard: pending and
        // accepted rows are occupied slots and still answer ALREADY_EXISTS.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, $helper->email);

        Sanctum::actingAs($owner);
        $this->postJson('/api/client/collaborators', ['email' => $helper->email, 'role' => 'manager'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'ALREADY_EXISTS');

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200);

        Sanctum::actingAs($owner);
        $this->postJson('/api/client/collaborators', ['email' => $helper->email, 'role' => 'publicist'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'ALREADY_EXISTS');
    }

    // ── The person who had no account when they were invited ──────────────────

    public function test_someone_invited_before_registering_can_accept_after_signing_up(): void
    {
        // The normal case, and the one the whole flow existed for: you are invited by
        // email, you sign up, and the invitation is waiting for you.
        $owner = User::factory()->create(['role' => 'client']);

        $id = $this->invite($owner, 'nueva@x.test');
        $this->assertNull(Collaborator::find($id)->user_id);

        $registered = $this->postJson('/api/register', [
            'name' => 'Nueva',
            'email' => 'Nueva@x.test', // different case on purpose
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role' => 'client',
        ])->assertStatus(201);

        // Signing up links the row but does NOT accept it — consent is a deliberate act.
        $invitee = User::where('email', 'Nueva@x.test')->firstOrFail();
        $this->assertSame($invitee->id, Collaborator::find($id)->user_id);
        $this->assertSame('pending', Collaborator::find($id)->status);
        $registered->assertJsonPath('collaborating_on', []);

        // Sanctum::actingAs() pins the guard for the rest of the test, so a bearer token
        // set afterwards is silently ignored — act as the invitee explicitly instead.
        Sanctum::actingAs($invitee);

        $this->getJson('/api/collaborations')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $id);

        $this->postJson("/api/collaborations/{$id}/accept")->assertStatus(200);

        $this->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('collaborating_on', [$owner->account_id]);
    }

    public function test_an_unlinked_invitation_is_matched_by_email_alone(): void
    {
        // Belt and braces: even if the back-link never ran (a row created outside the
        // User::created hook, e.g. by an import), the email arm of addressedTo() still
        // lets the right human answer — and only them.
        $owner = User::factory()->create(['role' => 'client']);
        $helper = User::factory()->create(['role' => 'client']);

        $invitation = Collaborator::create([
            'account_id' => $owner->account_id,
            'invited_by_user_id' => $owner->id,
            'user_id' => null,
            'email' => mb_strtoupper($helper->email),
            'role' => 'manager',
            'status' => Collaborator::STATUS_PENDING,
        ]);

        Sanctum::actingAs($helper);
        $this->postJson("/api/collaborations/{$invitation->id}/accept")->assertStatus(200);

        // Accepting writes the link the row was missing, or every user_id-keyed ACL in
        // the app would keep skipping this collaborator.
        $this->assertSame($helper->id, $invitation->fresh()->user_id);
    }

    // ── Reachability ──────────────────────────────────────────────────────────

    public function test_a_provider_can_answer_an_invitation_too(): void
    {
        // The `collaborators` permission belongs to the account OWNER (client only), so
        // gating these routes on it would have made a provider-side collaborator unable
        // to accept the invitation they were sent — a dead endpoint on arrival.
        $owner = User::factory()->create(['role' => 'provider']);
        $installer = User::factory()->create(['role' => 'provider']);

        $invitation = Collaborator::create([
            'account_id' => $owner->account_id,
            'invited_by_user_id' => $owner->id,
            'user_id' => $installer->id,
            'email' => $installer->email,
            'role' => 'installator',
            'status' => Collaborator::STATUS_PENDING,
        ]);

        Sanctum::actingAs($installer);
        $this->getJson('/api/collaborations')->assertStatus(200)->assertJsonCount(1);
        $this->postJson("/api/collaborations/{$invitation->id}/accept")->assertStatus(200);

        $this->getJson('/api/me')->assertStatus(200)->assertJsonPath('collaborating_on', [$owner->account_id]);
    }

    public function test_staff_have_no_invitations_to_answer(): void
    {
        // accounts.type is client|provider — a support agent inside somebody's account
        // would be a second, unaudited way in.
        foreach (['admin', 'support', 'payments'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/collaborations')->assertStatus(403);
        }
    }
}
