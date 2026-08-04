<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChatFlag;
use App\Models\Payment;
use App\Models\Proof;
use App\Models\WalletEntry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * UC-6/UC-7 · design.json §7/§8/§10 — the client proof review drives the money.
 *  - accept  -> proof client_accepted, payment free_payment (releasable).
 *  - reject  -> payout held + payment_held flag + 3 dispute chats.
 * Release is gated on the payment being `free_payment`, not on re-reading the proofs.
 */
class DisputeReviewFlowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    /**
     * Q46 · §2 — the client is not staff, but accept/reject is the one path where a non-staff
     * actor moves money. Both halves of the pair are audited: a gap on one side would read as
     * "the hold never happened" rather than "we chose not to record it".
     */
    public function test_client_accept_and_reject_both_audit_the_hold(): void
    {
        ['client' => $client, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/accept")->assertStatus(200);

        $entry = AuditLog::where('action', 'free_payment_on_accept')->sole();
        $this->assertSame($client->id, $entry->actor_id);
        $this->assertSame('client', $entry->actor_role);
        $this->assertSame('free_payment', $entry->after['status']);

        ['client' => $client2, 'proof' => $proof2] = $this->bookingScenario();
        Sanctum::actingAs($client2);
        $this->postJson("/api/client/proofs/{$proof2->id}/reject", ['reason' => 'wrong wall'])->assertStatus(200);

        $this->assertSame('client', AuditLog::where('action', 'hold_on_reject')->sole()->actor_role);
    }

    /**
     * Q42/Q53 — the enum is exactly three values. The legacy pair (`approved`/`rejected`)
     * is not merely unused, it is now ILLEGAL: leaving them legal is what let the schema
     * describe two vocabularies at once and nobody could tell which one was live.
     */
    public function test_proof_status_vocabulary_is_exactly_three_values(): void
    {
        ['proof' => $proof] = $this->bookingScenario();

        // A proof is born at the upload, and the default says so without being told.
        DB::table('proofs')->where('id', $proof->id)->update(['status' => DB::raw('DEFAULT')]);
        $this->assertSame(Proof::STATUS_UPLOADED, $proof->fresh()->status);

        foreach (['pending_review', 'approved', 'rejected'] as $retired) {
            try {
                DB::table('proofs')->where('id', $proof->id)->update(['status' => $retired]);
                $this->fail("proofs.status still accepts the retired value '{$retired}'.");
            } catch (QueryException) {
                // The CHECK constraint refused it, which is the point.
            }
        }
    }

    public function test_accept_marks_proof_accepted_and_holds_payment(): void
    {
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/accept")->assertStatus(200);

        $this->assertSame('client_accepted', $proof->fresh()->status);
        // §8 — accept frees the payout; only a REJECT freezes it.
        $this->assertSame('free_payment', $payment->fresh()->status);

        // §7 — the client is the reviewer of record. Both columns used to stay NULL forever.
        $this->assertSame($client->id, $proof->fresh()->reviewed_by_user_id);
        $this->assertNotNull($proof->fresh()->reviewed_at);
    }

    public function test_release_payout_is_blocked_until_client_accepts(): void
    {
        ['client' => $client, 'provider' => $provider, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();

        $payments = \App\Models\User::factory()->create(['role' => 'payments']);

        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/release")->assertStatus(409);

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/accept")->assertStatus(200);

        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/release")->assertStatus(200);

        $this->assertSame('released', $payment->fresh()->status);
        $this->assertTrue(
            WalletEntry::where('user_id', $provider->id)
                ->where('type', 'escrow_release')
                ->where('idempotency_key', "escrow_release:payment:{$payment->id}")
                ->exists()
        );
    }

    /**
     * §8 — the money bug this guard exists for. Before it, `settle()` had no state check at
     * all: a Payments user could take a payment frozen by a live dispute and approve it, and
     * the three dispute chats would go on saying "payout held" while the authoritative column
     * said `completed`. Nothing errored, so nothing was ever noticed.
     */
    public function test_a_disputed_payment_cannot_be_settled_from_under_the_dispute(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $s;

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'blank wall'])->assertStatus(200);
        $this->assertSame(Payment::STATUS_HELD, $payment->fresh()->status);

        $payments = \App\Models\User::factory()->create(['role' => 'payments']);
        Sanctum::actingAs($payments);

        // 409, not 422: the payload is fine, the payment's STATE forbids the move.
        $this->postJson("/api/payments/payments/{$payment->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('status', Payment::STATUS_HELD);
        $this->postJson("/api/payments/payments/{$payment->id}/reject")->assertStatus(409);

        // The dispute is untouched: the money did not move and no audit row claims it did.
        $this->assertSame(Payment::STATUS_HELD, $payment->fresh()->status);
        $this->assertSame(0, AuditLog::whereIn('action', ['approve', 'reject'])->count());
    }

    /** The same guard protects money that has already finished moving. */
    public function test_a_released_payment_cannot_be_settled_again(): void
    {
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/accept")->assertStatus(200);

        $payments = \App\Models\User::factory()->create(['role' => 'payments']);
        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/release")->assertStatus(200);

        $this->postJson("/api/payments/payments/{$payment->id}/approve")->assertStatus(409);
        $this->assertSame(Payment::STATUS_RELEASED, $payment->fresh()->status);
    }

    /**
     * EH-4 — the guard that does not depend on two columns agreeing. A payout stop writes
     * BOTH `payout_stopped_at` and `status = held` today, so the status check alone happens
     * to catch it; the day some path writes only one of the two, this is what still refuses.
     */
    public function test_a_stopped_payout_is_refused_even_if_its_status_says_releasable(): void
    {
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/accept")->assertStatus(200);
        $this->assertSame(Payment::STATUS_FREE_PAYMENT, $payment->fresh()->status);

        // Deliberately the DRIFTED state: stopped, but the status still reads releasable.
        $payment->forceFill(['payout_stopped_at' => now()])->save();

        $payments = \App\Models\User::factory()->create(['role' => 'payments']);
        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/release")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        $this->assertSame(Payment::STATUS_FREE_PAYMENT, $payment->fresh()->status);
        $this->assertSame(0, \App\Models\WalletEntry::where('type', 'escrow_release')->count());
    }

    public function test_reject_raises_payment_held_flag_with_reason(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'proof' => $proof] = $s;

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'not displayed'])
            ->assertStatus(200);

        $chats = $this->disputeChats($s);
        $flag = $chats['internal']->flags()->where('active', true)->firstOrFail();
        $this->assertSame(ChatFlag::TYPE_PAYMENT_HELD, $flag->type);
        $this->assertSame('not displayed', $flag->reason);
    }

    public function test_reject_posts_one_system_opener_per_chat(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'proof' => $proof] = $s;

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'x'])->assertStatus(200);

        foreach ($this->disputeChats($s) as $chat) {
            $this->assertSame(1, $chat->messages()
                ->where('kind', 'system')
                ->where('body', 'like', 'Dispute opened%')
                ->count());
        }
    }

    public function test_flag_mismatch_is_backcompat_alias_of_reject(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $s;

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/flag-mismatch", ['reason' => 'y'])
            ->assertStatus(200);

        $this->assertSame('held', $payment->fresh()->status);
        $this->assertSame(3, Payment::find($payment->id)->chatObjects()->count());
    }
}
