<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Ad;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Collaborator;
use App\Models\Message;
use App\Models\Proof;
use App\Models\Space;
use App\Models\User;
use App\Models\WalletEntry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * design.json §3/§12/§16 — what survives a user's deletion, and what refuses it.
 *
 * The rules under test, both from owner 2026-08-03:
 *  · property (campaigns, spaces, bookings, the wallet ledger) makes the DELETE
 *    impossible — the DATABASE refuses it, and the API says 409;
 *  · authored content (messages, proofs) outlives its author, EXCEPT when the
 *    author is the account owner, who must first confirm they know that without
 *    proofs no payment will be released to them.
 */
class AccountDeletionGuardrailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** A provider that owns a space, so other people can have bookings and proofs. */
    private function hostProvider(): array
    {
        $provider = User::factory()->create(['role' => 'provider']);

        $space = Space::create([
            'user_id' => $provider->id,
            'name' => 'Host Space',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
        ]);

        $ad = Ad::create([
            'space_id' => $space->id,
            'provider_user_id' => $provider->id,
            'name' => 'Ad',
            'media_type' => 'image',
            'status' => 'active',
        ]);

        $client = User::factory()->create(['role' => 'client']);

        $booking = Booking::create([
            'client_user_id' => $client->id,
            'space_id' => $space->id,
            'ad_id' => $ad->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'total_price' => 500,
            'status' => 'waiting_proof',
        ]);

        return compact('provider', 'space', 'ad', 'client', 'booking');
    }

    // ── In use: the deletion becomes a DATE, and nothing is destroyed ─────────
    //
    // AD-delguard-09 (owner 2026-08-04). These two used to assert a flat 409 — the
    // engine's FK refusal surfaced as "no". A dead end is not an answer, and §12/UC-37
    // already had the better one for a listing: unpublish it and program the deletion.
    // The account now does the same, so the assertion moved from "refused" to
    // "programmed, and everything still there".

    public function test_an_owner_with_live_campaigns_is_programmed_not_deleted(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $campaign = Campaign::create(['user_id' => $client->id, 'name' => 'Verano', 'status' => 'active']);

        Sanctum::actingAs($client);

        $this->deleteJson('/api/account')
            ->assertOk()
            ->assertJsonPath('blockers.0.kind', 'campaigns');

        // Nothing was destroyed — the account merely left circulation with a date on it.
        $this->assertNotNull(User::find($client->id));
        $this->assertNotNull(Campaign::find($campaign->id));

        $account = Account::find($client->account_id);
        $this->assertNotNull($account);
        $this->assertSame(Account::PUBLICATION_UNPUBLISHED, $account->publication_status);
        $this->assertNotNull($account->delete_scheduled_at);
    }

    public function test_a_provider_with_spaces_and_a_client_with_a_ledger_are_programmed_not_deleted(): void
    {
        $provider = User::factory()->create(['role' => 'provider']);
        Space::create([
            'user_id' => $provider->id,
            'name' => 'S',
            'latitude' => 25.6,
            'longitude' => -100.4,
        ]);

        Sanctum::actingAs($provider);
        $this->deleteJson('/api/account')
            ->assertOk()
            ->assertJsonPath('blockers.0.kind', 'spaces');
        $this->assertNotNull(User::find($provider->id));

        // A wallet ledger that can be deleted is not a ledger (§8).
        $client = User::factory()->create(['role' => 'client']);
        WalletEntry::create([
            'user_id' => $client->id,
            'amount' => 100,
            'type' => 'refund',
            'idempotency_key' => 'test:1',
        ]);

        Sanctum::actingAs($client);
        $this->deleteJson('/api/account')
            ->assertOk()
            ->assertJsonPath('blockers.0.kind', 'wallet_entries');

        $this->assertSame(1, WalletEntry::count());
        $this->assertNotNull(User::find($client->id));
    }


    public function test_the_database_itself_refuses_before_any_application_check(): void
    {
        // The guardrail is not an `if` in a controller — a psql session gets the
        // same answer. Deleting the row directly must raise, not cascade.
        $client = User::factory()->create(['role' => 'client']);
        Campaign::create(['user_id' => $client->id, 'name' => 'Verano', 'status' => 'active']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $client->delete();
    }

    // ── Content survives its author when the author is a collaborator ─────────

    public function test_a_collaborators_messages_and_proofs_survive_their_deletion(): void
    {
        $host = $this->hostProvider();

        // An installator crew member: a user with a GRANT on the provider's
        // account, who owns nothing of their own on the platform.
        $installer = User::factory()->create(['role' => 'provider']);
        Collaborator::create([
            'account_id' => $host['provider']->account_id,
            'invited_by_user_id' => $host['provider']->id,
            'user_id' => $installer->id,
            'email' => $installer->email,
            'role' => 'installator',
            'status' => 'accepted',
        ]);

        $proof = Proof::create([
            'ad_id' => $host['ad']->id,
            'booking_id' => $host['booking']->id,
            'uploaded_by_user_id' => $installer->id,
            'media_type' => 'image',
            'file_path' => 'proofs/installed.jpg',
            'file_name' => 'installed.jpg',
            'status' => Proof::STATUS_UPLOADED,
        ]);

        $chat = Chat::create([
            'opened_by_user_id' => $host['client']->id,
            'client_user_id' => $host['client']->id,
            'provider_user_id' => $host['provider']->id,
            'status' => Chat::STATUS_OPEN,
        ]);
        $chat->participants()->create([
            'user_id' => $installer->id,
            'side' => ChatParticipant::SIDE_PROVIDER,
            'joined_at' => now(),
        ]);
        $message = $chat->messages()->create([
            'sender_user_id' => $installer->id,
            'body' => 'Instalado y fotografiado 08:40.',
            'kind' => 'user',
        ]);

        $installer->delete();

        // The evidence the provider gets PAID on does not leave with the crew.
        $this->assertNull(User::find($installer->id));

        $proof = $proof->fresh();
        $this->assertNotNull($proof, 'a collaborator leaving must not delete the proof of display');
        $this->assertNull($proof->uploaded_by_user_id);
        $this->assertSame('proofs/installed.jpg', $proof->file_path);

        $message = $message->fresh();
        $this->assertNotNull($message, 'a collaborator leaving must not delete what they wrote');
        $this->assertNull($message->sender_user_id);
        $this->assertSame('Instalado y fotografiado 08:40.', $message->body);

        // …and the conversation itself is intact for the counterparty.
        $this->assertNotNull(Chat::find($chat->id));
    }

    public function test_a_chat_survives_the_deletion_of_whoever_opened_it(): void
    {
        $host = $this->hostProvider();

        // The opener owns nothing, so only the chat FK is under test here.
        $opener = User::factory()->create(['role' => 'client']);

        $chat = Chat::create([
            'opened_by_user_id' => $opener->id,
            'client_user_id' => $host['client']->id,
            'provider_user_id' => $host['provider']->id,
            'status' => Chat::STATUS_OPEN,
        ]);
        $message = $chat->messages()->create([
            'sender_user_id' => $host['provider']->id,
            'body' => 'Confirmado.',
            'kind' => 'user',
        ]);

        $opener->delete();

        $this->assertNotNull(Chat::find($chat->id));
        $this->assertNull(Chat::find($chat->id)->opened_by_user_id);
        $this->assertSame($host['provider']->id, Message::find($message->id)->sender_user_id);
    }

    // ── The account owner's own proofs need an explicit confirmation ─────────

    /** An account owner whose ONLY tie to the platform is a proof they uploaded. */
    private function ownerWithProofOnly(): array
    {
        $host = $this->hostProvider();

        $owner = User::factory()->create(['role' => 'provider']);

        $proof = Proof::create([
            'ad_id' => $host['ad']->id,
            'booking_id' => $host['booking']->id,
            'uploaded_by_user_id' => $owner->id,
            'media_type' => 'image',
            'file_path' => 'proofs/owner.jpg',
            'file_name' => 'owner.jpg',
            'status' => Proof::STATUS_UPLOADED,
        ]);

        return [$owner, $proof];
    }

    public function test_an_owner_with_proofs_is_refused_without_the_confirmation_flag(): void
    {
        [$owner, $proof] = $this->ownerWithProofOnly();

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/account')
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFIRMATION_REQUIRED')
            ->assertJsonPath('proofs', 1)
            ->assertJsonPath('confirmation_field', 'confirm_proof_loss');

        // A refusal changes nothing at all.
        $this->assertNotNull(User::find($owner->id));
        $this->assertNotNull(Proof::find($proof->id));
        $this->assertSame(0, AuditLog::count());

        // A falsey flag is not a confirmation.
        $this->deleteJson('/api/account', ['confirm_proof_loss' => false])->assertStatus(409);
        $this->assertNotNull(User::find($owner->id));
    }

    public function test_a_confirmed_owner_deletion_destroys_the_proofs_and_is_audited(): void
    {
        [$owner, $proof] = $this->ownerWithProofOnly();
        $accountId = $owner->account_id;

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/account', ['confirm_proof_loss' => true])->assertStatus(200);

        $this->assertNull(User::find($owner->id));
        $this->assertNull(Account::find($accountId));

        // The confirmation is about a REAL loss — otherwise it confirms nothing.
        $this->assertNull(Proof::find($proof->id));

        // §12 — the acknowledgement outlives the person who gave it. actor_id is
        // nulled by the FK when the user goes; the role and label are the record.
        $ack = AuditLog::where('action', 'confirm_proof_loss')->firstOrFail();
        $this->assertSame('proofs', $ack->target_type);
        $this->assertSame(1, $ack->before['count']);
        $this->assertStringContainsString('no payment can be released', $ack->context);

        $deletion = AuditLog::where('action', 'delete')->where('target_type', 'accounts')->firstOrFail();
        $this->assertSame($accountId, $deletion->target_id);
        $this->assertSame('provider', $deletion->actor_role);
        $this->assertStringContainsString('confirm_proof_loss=true', $deletion->context);
    }

    public function test_an_owner_with_nothing_attached_deletes_cleanly(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $accountId = $owner->account_id;

        Sanctum::actingAs($owner);

        // No proofs, so no confirmation is demanded — the flag exists for a loss,
        // not as a ritual.
        $this->deleteJson('/api/account')->assertStatus(200);

        $this->assertNull(User::find($owner->id));
        $this->assertNull(Account::find($accountId));
        $this->assertSame(1, AuditLog::where('action', 'delete')->where('target_type', 'accounts')->count());
    }

    public function test_staff_have_no_account_delete_route(): void
    {
        // Owner 2026-08-03: only the account owner deletes an account. There is no
        // admin path to it — not a guarded one, none.
        foreach (['admin', 'support', 'payments'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->deleteJson('/api/account')->assertStatus(403);
        }

        $user = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson("/api/admin/users/{$user->id}")->assertStatus(405);
    }
}
