<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Ad;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Chat;
use App\Models\ChatFlag;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Proof;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AD-delguard-09 · design.json §3/§12 · UC-37 — one side cannot delete a two-sided record.
 *
 * Owner 2026-08-04: "Si el dueño de la cuenta es el proveedor, borrarse a sí mismo
 * destruye la evidencia de disputas que quizá afecten a clientes que ya pagaron. Aquí
 * volvemos a los guardrails tipo 'programar para destruir'."
 *
 * The load-bearing test is `test_an_open_dispute_refuses_even_with_the_confirmation`.
 * Before this, `confirm_proof_loss=true` deleted the provider's proofs unconditionally —
 * so a provider losing a dispute could destroy the evidence a paying client was arguing
 * from, by clicking "yes, I understand I won't be paid". That confirmation is the owner's
 * consent to THEIR OWN loss and it was being spent on somebody else's record.
 */
class AccountDisputeDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $provider;

    private User $client;

    private Space $space;

    private Ad $ad;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->provider = User::factory()->create(['role' => 'provider']);
        $this->client = User::factory()->create(['role' => 'client']);

        $this->space = Space::create([
            'user_id' => $this->provider->id,
            'name' => 'Barda Centro',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
            'price_per_day' => 100.00,
        ]);

        $this->ad = Ad::create([
            'space_id' => $this->space->id,
            'provider_user_id' => $this->provider->id,
            'name' => 'Ad',
            'media_type' => 'image',
            'status' => 'active',
        ]);

        $this->booking = Booking::create([
            'client_user_id' => $this->client->id,
            'space_id' => $this->space->id,
            'ad_id' => $this->ad->id,
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->subDays(1)->toDateString(),
            'total_price' => 500.00,
            'status' => 'completed',
        ]);
    }

    private function proofBy(User $uploader, string $status = Proof::STATUS_UPLOADED): Proof
    {
        return Proof::create([
            'ad_id' => $this->ad->id,
            'booking_id' => $this->booking->id,
            'uploaded_by_user_id' => $uploader->id,
            'media_type' => 'image',
            'file_path' => 'proofs/display.jpg',
            'file_name' => 'display.jpg',
            'status' => $status,
        ]);
    }

    /** The dispute as ProofFlagController builds it: a chat + an active payment_held flag. */
    private function disputeChat(): Chat
    {
        $chat = Chat::create([
            'opened_by_user_id' => $this->client->id,
            'client_user_id' => $this->client->id,
            'provider_user_id' => $this->provider->id,
            'status' => Chat::STATUS_OPEN,
        ]);

        $chat->flags()->create([
            'type' => ChatFlag::TYPE_PAYMENT_HELD,
            'reason' => 'La lona no se instaló en la fecha acordada.',
            'active' => true,
            'created_by_user_id' => $this->client->id,
        ]);

        return $chat;
    }

    // ── 1. A dispute refuses, and no confirmation can buy its way past ────────

    public function test_an_open_dispute_refuses_even_with_the_confirmation(): void
    {
        $this->disputeChat();
        $this->proofBy($this->provider);

        Sanctum::actingAs($this->provider);

        $this->deleteJson('/api/account', ['confirm_proof_loss' => true])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'OBJECT_IN_USE')
            ->assertJsonPath('blockers.0.kind', 'open_dispute')
            ->assertJsonPath('blockers.0.count', 1);
    }

    public function test_the_refusal_names_the_dispute_it_is_protecting(): void
    {
        $this->disputeChat();

        Sanctum::actingAs($this->provider);

        $response = $this->deleteJson('/api/account', ['confirm_proof_loss' => true])->assertStatus(409);

        $this->assertStringContainsString('open dispute', $response->json('blockers.0.message'));
        // The refusal says out loud that the flag is not the missing ingredient.
        $this->assertStringContainsString('confirm_proof_loss', $response->json('message'));
    }

    public function test_the_evidence_all_survives_the_refused_deletion(): void
    {
        $chat = $this->disputeChat();
        $proof = $this->proofBy($this->provider);
        $message = $chat->messages()->create([
            'sender_user_id' => $this->provider->id,
            'body' => 'La instalación se hizo el día 3, adjunto foto.',
            'kind' => 'user',
        ]);

        Sanctum::actingAs($this->provider);
        $this->deleteJson('/api/account', ['confirm_proof_loss' => true])->assertStatus(409);

        // Every party's half of the record is intact.
        $this->assertNotNull(User::find($this->provider->id));
        $this->assertNotNull(Account::find($this->provider->account_id));
        $this->assertNotNull(Proof::find($proof->id));
        $this->assertNotNull(Message::find($message->id));
        $this->assertNotNull(Chat::find($chat->id));

        // A refusal is not an event: nothing was unpublished, nothing was programmed,
        // and no acknowledgement was banked for a purge to spend later.
        $account = Account::find($this->provider->account_id);
        $this->assertSame(Account::PUBLICATION_PUBLISHED, $account->publication_status);
        $this->assertNull($account->delete_scheduled_at);
        $this->assertNull($account->proof_loss_confirmed_at);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_the_client_side_of_the_same_dispute_is_refused_too(): void
    {
        // The guardrail is about the RECORD, not about who looks guilty. The client
        // cannot delete their way out of it either.
        $this->disputeChat();

        Sanctum::actingAs($this->client);
        $this->deleteJson('/api/account', ['confirm_proof_loss' => true])
            ->assertStatus(409)
            ->assertJsonPath('blockers.0.kind', 'open_dispute');
    }

    public function test_held_money_is_a_dispute_on_its_own(): void
    {
        Payment::create(['booking_id' => $this->booking->id, 'amount' => 500.00, 'status' => Payment::STATUS_HELD]);

        Sanctum::actingAs($this->provider);
        $this->deleteJson('/api/account', ['confirm_proof_loss' => true])
            ->assertStatus(409)
            ->assertJsonPath('blockers.0.kind', 'held_payments');
    }

    public function test_a_rejected_proof_is_a_dispute_on_its_own(): void
    {
        $this->proofBy($this->provider, Proof::STATUS_CLIENT_REJECTED);

        Sanctum::actingAs($this->provider);
        $this->deleteJson('/api/account', ['confirm_proof_loss' => true])
            ->assertStatus(409)
            ->assertJsonPath('blockers.0.kind', 'rejected_proofs');
    }

    public function test_a_superseded_flag_is_not_an_open_dispute(): void
    {
        // Flags supersede in place (§10/§15). A closed dispute must not lock an account
        // out of deletion for ever — otherwise the guardrail is a trap, not a rule.
        $chat = $this->disputeChat();
        $chat->flags()->update(['active' => false, 'superseded_at' => now()]);

        Sanctum::actingAs($this->provider);

        // Not 409-for-dispute: the provider still owns a listing, so this is the
        // programmed-deletion path instead.
        $this->deleteJson('/api/account')
            ->assertOk()
            ->assertJsonPath('blockers.0.kind', 'spaces');
    }

    // ── 2. In use but not disputed: unpublish + program ───────────────────────

    public function test_live_bookings_get_the_programmed_deletion_path(): void
    {
        $this->booking->update(['status' => 'confirmed']);

        Sanctum::actingAs($this->provider);

        $response = $this->deleteJson('/api/account')->assertOk();
        $this->assertContains('live_bookings', array_column($response->json('blockers'), 'kind'));

        $account = Account::find($this->provider->account_id);
        $this->assertSame(Account::PUBLICATION_UNPUBLISHED, $account->publication_status);
        $this->assertNotNull($account->delete_scheduled_at);

        // NOTHING was destroyed. That is the whole feature.
        $this->assertNotNull(User::find($this->provider->id));
        $this->assertDatabaseHas('bookings', ['id' => $this->booking->id]);
        $this->assertDatabaseHas('spaces', ['id' => $this->space->id]);

        $entry = AuditLog::where('action', 'schedule_deletion')->where('target_type', 'accounts')->sole();
        $this->assertSame($this->provider->id, $entry->actor_id);
        $this->assertSame(Account::PUBLICATION_UNPUBLISHED, $entry->after['publication_status']);
    }

    /** The catalog is the point of "despublicarse": the listings have to actually go. */
    public function test_a_programmed_account_takes_its_listings_out_of_the_catalog(): void
    {
        $this->assertTrue(Space::bookable()->where('id', $this->space->id)->exists());

        Sanctum::actingAs($this->provider);
        $this->deleteJson('/api/account')->assertOk();

        $this->assertFalse(Space::bookable()->where('id', $this->space->id)->exists());
    }

    public function test_programming_twice_is_refused(): void
    {
        Sanctum::actingAs($this->provider);
        $this->deleteJson('/api/account')->assertOk();

        $this->deleteJson('/api/account')
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'ALREADY_EXISTS');
    }

    public function test_cancelling_returns_the_account_to_the_catalog(): void
    {
        Sanctum::actingAs($this->provider);
        $this->deleteJson('/api/account')->assertOk();

        $this->postJson('/api/account/cancel-deletion')->assertOk();

        $account = Account::find($this->provider->account_id);
        $this->assertSame(Account::PUBLICATION_PUBLISHED, $account->publication_status);
        $this->assertNull($account->delete_scheduled_at);
        $this->assertTrue(Space::bookable()->where('id', $this->space->id)->exists());
        $this->assertSame(1, AuditLog::where('action', 'cancel_deletion')->count());

        // Nothing to cancel twice — 409, the state refuses.
        $this->postJson('/api/account/cancel-deletion')
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');
    }

    public function test_the_proof_confirmation_is_persisted_when_a_deletion_is_programmed(): void
    {
        $this->proofBy($this->provider);

        Sanctum::actingAs($this->provider);

        // Still asked for, even on the programmed path — the purge will spend it.
        $this->deleteJson('/api/account')
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFIRMATION_REQUIRED');

        $this->deleteJson('/api/account', ['confirm_proof_loss' => true])->assertOk();

        $account = Account::find($this->provider->account_id);
        $this->assertNotNull($account->proof_loss_confirmed_at);
        $this->assertSame(1, AuditLog::where('action', 'confirm_proof_loss')->count());

        // Cancelling spends the acknowledgement too: a stale "yes" is not a yes.
        $this->postJson('/api/account/cancel-deletion')->assertOk();
        $this->assertNull(Account::find($this->provider->account_id)->proof_loss_confirmed_at);
    }

    // ── 3. The purge re-asks everything ───────────────────────────────────────

    /** Turn the scheduled account into one that is genuinely due and genuinely clean. */
    private function programAndRipen(): Account
    {
        Sanctum::actingAs($this->provider);
        $this->deleteJson('/api/account')->assertOk();

        // Weeks pass, and `spaces:purge` has since taken the listings. What is left is
        // an account with nothing attached and a date in the past.
        Proof::where('booking_id', $this->booking->id)->delete();
        Payment::where('booking_id', $this->booking->id)->delete();
        $this->booking->delete();
        $this->ad->delete();
        $this->space->delete();

        $account = Account::find($this->provider->account_id);
        // forceFill on purpose: `delete_scheduled_at` is NOT fillable, so no payload can
        // move a purge date. The test bypasses it exactly the way the controller does.
        $account->forceFill(['delete_scheduled_at' => now()->subDay()])->save();

        return $account;
    }

    public function test_the_purge_is_a_dry_run_until_it_is_confirmed(): void
    {
        $account = $this->programAndRipen();

        $this->artisan('accounts:purge')->assertSuccessful();
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        $this->assertDatabaseHas('users', ['id' => $this->provider->id]);
        $this->assertSame(0, AuditLog::where('action', 'purge')->count());
    }

    public function test_a_clean_account_purges_under_confirm_and_is_audited(): void
    {
        $account = $this->programAndRipen();

        $this->artisan('accounts:purge', ['--confirm' => true])->assertSuccessful();

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('users', ['id' => $this->provider->id]);

        $entry = AuditLog::where('action', 'purge')->where('target_type', 'accounts')->sole();
        $this->assertSame($account->id, $entry->target_id);
        $this->assertSame('provider', $entry->actor_role);
        $this->assertStringContainsString('accounts:purge', $entry->context);
    }

    /**
     * The one the whole command exists for. Weeks sit between programming and purging,
     * and a dispute that opens in that window is exactly the case the owner raised.
     */
    public function test_the_purge_skips_an_account_that_acquired_a_dispute_while_it_waited(): void
    {
        $this->booking->update(['status' => 'confirmed']);

        Sanctum::actingAs($this->provider);
        $this->deleteJson('/api/account')->assertOk();

        $account = Account::find($this->provider->account_id);
        $account->forceFill(['delete_scheduled_at' => now()->subDay()])->save();

        // …and only now does the client reject the display.
        $this->disputeChat();
        $proof = $this->proofBy($this->provider, Proof::STATUS_CLIENT_REJECTED);

        $this->artisan('accounts:purge', ['--confirm' => true])->assertSuccessful();

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        $this->assertDatabaseHas('users', ['id' => $this->provider->id]);
        $this->assertNotNull(Proof::find($proof->id));
        $this->assertSame(0, AuditLog::where('action', 'purge')->count());
    }

    public function test_the_purge_skips_a_proof_loss_that_was_never_confirmed(): void
    {
        $account = $this->programAndRipen();

        // A proof that appeared after the schedule — nobody ever confirmed losing it.
        $otherProvider = User::factory()->create(['role' => 'provider']);
        $otherSpace = Space::create([
            'user_id' => $otherProvider->id,
            'name' => 'Otra barda',
            'latitude' => 25.7,
            'longitude' => -100.3,
        ]);
        $otherAd = Ad::create([
            'space_id' => $otherSpace->id,
            'provider_user_id' => $otherProvider->id,
            'name' => 'Otro ad',
            'media_type' => 'image',
            'status' => 'active',
        ]);
        $booking = Booking::create([
            'client_user_id' => $this->client->id,
            'space_id' => $otherSpace->id,
            'ad_id' => $otherAd->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
            'total_price' => 100.00,
            'status' => 'completed',
        ]);
        Proof::create([
            'ad_id' => $otherAd->id,
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $this->provider->id,
            'media_type' => 'image',
            'file_path' => 'proofs/late.jpg',
            'file_name' => 'late.jpg',
            'status' => Proof::STATUS_UPLOADED,
        ]);

        $this->artisan('accounts:purge', ['--confirm' => true])->assertSuccessful();

        $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        $this->assertSame(1, Proof::where('uploaded_by_user_id', $this->provider->id)->count());
    }

    public function test_an_account_not_yet_due_is_left_alone(): void
    {
        Sanctum::actingAs($this->provider);
        $this->deleteJson('/api/account')->assertOk();

        $this->artisan('accounts:purge', ['--confirm' => true])->assertSuccessful();
        $this->assertDatabaseHas('accounts', ['id' => $this->provider->account_id]);
    }
}
