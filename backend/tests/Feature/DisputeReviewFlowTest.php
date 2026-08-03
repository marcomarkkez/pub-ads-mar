<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChatFlag;
use App\Models\Payment;
use App\Models\WalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * UC-6/UC-7 · design.json §7/§8/§10 — the client proof review drives the money.
 *  - accept  -> proof client_accepted, payment held, payout releasable.
 *  - reject  -> payout held + payment_held flag + 3 dispute chats.
 * Release is gated on the client having accepted.
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

        $entry = AuditLog::where('action', 'hold_on_accept')->sole();
        $this->assertSame($client->id, $entry->actor_id);
        $this->assertSame('client', $entry->actor_role);
        $this->assertSame('held', $entry->after['status']);

        ['client' => $client2, 'proof' => $proof2] = $this->bookingScenario();
        Sanctum::actingAs($client2);
        $this->postJson("/api/client/proofs/{$proof2->id}/reject", ['reason' => 'wrong wall'])->assertStatus(200);

        $this->assertSame('client', AuditLog::where('action', 'hold_on_reject')->sole()->actor_role);
    }

    public function test_accept_marks_proof_accepted_and_holds_payment(): void
    {
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/accept")->assertStatus(200);

        $this->assertSame('client_accepted', $proof->fresh()->status);
        $this->assertSame('held', $payment->fresh()->status);
    }

    public function test_release_payout_is_blocked_until_client_accepts(): void
    {
        ['client' => $client, 'provider' => $provider, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();

        $payments = \App\Models\User::factory()->create(['role' => 'payments']);

        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/release")->assertStatus(422);

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
