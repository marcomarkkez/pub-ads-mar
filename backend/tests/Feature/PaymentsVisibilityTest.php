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
 * design.md §8/§11 [F10<-F09]: Payments sees WHY money is held — the Support flag
 * / dispute tickets ride along on the payment. Money ops stay idempotent.
 */
class PaymentsVisibilityTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    private function openDisputeAsClient(array $s): void
    {
        Sanctum::actingAs($s['client']);
        $this->postJson("/api/client/proofs/{$s['proof']->id}/reject", ['reason' => 'not shown'])
            ->assertStatus(200);
    }

    public function test_payments_index_and_show_load_the_flag_tickets(): void
    {
        $s = $this->bookingScenario();
        $this->openDisputeAsClient($s);
        $payment = $s['payment'];

        $payments = User::factory()->create(['role' => 'payments']);
        Sanctum::actingAs($payments);

        $index = $this->getJson('/api/payments/payments')->assertStatus(200);
        $tickets = collect($index->json('data'))
            ->firstWhere('id', $payment->id)['tickets'] ?? [];
        $this->assertNotEmpty($tickets);
        $this->assertStringStartsWith('Payment held', $tickets[0]['subject']);

        $this->getJson("/api/payments/payments/{$payment->id}")
            ->assertStatus(200)
            ->assertJsonPath('tickets.0.subject', 'Payment held — not shown');
    }

    public function test_refund_is_idempotent(): void
    {
        ['payment' => $payment, 'client' => $client] = $this->bookingScenario();

        $payments = User::factory()->create(['role' => 'payments']);
        Sanctum::actingAs($payments);

        $this->postJson("/api/payments/payments/{$payment->id}/refund")->assertStatus(200);
        $this->postJson("/api/payments/payments/{$payment->id}/refund")->assertStatus(200);

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame(
            1,
            WalletEntry::where('idempotency_key', "refund:payment:{$payment->id}")->count()
        );
        // Credited the booking's client exactly once.
        $this->assertSame(
            1,
            WalletEntry::where('user_id', $client->id)->where('type', 'refund')->count()
        );
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
