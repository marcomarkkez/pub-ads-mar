<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * UC-27 · design.json §8/§10 — Payments sees WHY money is held: the dispute chats (with
 * their active payment_held flag) ride along on the payment as chat_objects. Money ops
 * stay idempotent.
 */
class PaymentsVisibilityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    public function test_payments_index_and_show_load_the_dispute_chats(): void
    {
        $s = $this->bookingScenario();
        Sanctum::actingAs($s['client']);
        $this->postJson("/api/client/proofs/{$s['proof']->id}/reject", ['reason' => 'not shown'])->assertStatus(200);
        $payment = $s['payment'];

        $payments = User::factory()->create(['role' => 'payments']);
        Sanctum::actingAs($payments);

        $index = $this->getJson('/api/payments/payments')->assertStatus(200);
        $row = collect($index->json('data'))->firstWhere('id', $payment->id);
        $this->assertNotEmpty($row['chat_objects']);

        // At least one attached chat carries an active payment_held flag.
        $show = $this->getJson("/api/payments/payments/{$payment->id}")->assertStatus(200)->json();
        $flags = collect($show['chat_objects'])
            ->flatMap(fn ($o) => $o['chat']['active_flags'] ?? [])
            ->pluck('type');
        $this->assertTrue($flags->contains('payment_held'));
    }

    /**
     * The money stays idempotent — one refund, one ledger entry — but the SECOND
     * call is now answered instead of silently absorbed: owner 2026-08-03, "when
     * the API refuses because the object is in a conflicting STATE, the status is
     * 409". A payment that has finished moving is exactly that state
     * (PaymentController::refund, Payment::TERMINAL). This test asserted 200 for
     * the repeat, from when the idempotency key was the only guard.
     */
    public function test_refund_is_idempotent(): void
    {
        ['payment' => $payment, 'client' => $client] = $this->bookingScenario();

        $payments = User::factory()->create(['role' => 'payments']);
        Sanctum::actingAs($payments);

        $this->postJson("/api/payments/payments/{$payment->id}/refund")->assertStatus(200);
        $this->postJson("/api/payments/payments/{$payment->id}/refund")
            ->assertStatus(409)
            ->assertJsonPath('status', 'refunded');

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame(1, WalletEntry::where('idempotency_key', "refund:payment:{$payment->id}")->count());
        $this->assertSame(1, WalletEntry::where('user_id', $client->id)->where('type', 'refund')->count());
    }

    public function test_hold_payout_parks_status_held(): void
    {
        ['payment' => $payment] = $this->bookingScenario();

        $payments = User::factory()->create(['role' => 'payments']);
        Sanctum::actingAs($payments);

        $this->postJson("/api/payments/payments/{$payment->id}/payout/hold")->assertStatus(200);
        $this->assertSame('held', $payment->fresh()->status);
    }
}
