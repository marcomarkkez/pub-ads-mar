<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * design.json §8 [F10]: settled payments are terminal — a released/refunded payment
 * is never dragged back to held; and a refund credits the client's wallet.
 */
class MoneyStateTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    public function test_hold_does_not_revert_a_released_payment(): void
    {
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();
        $payments = User::factory()->create(['role' => 'payments']);

        // Client accepts -> Payments releases.
        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/accept")->assertStatus(200);
        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/release")->assertStatus(200);

        // Holding it now is a no-op.
        $this->postJson("/api/payments/payments/{$payment->id}/payout/hold")->assertStatus(200);
        $this->assertSame('released', $payment->fresh()->status);
    }

    public function test_hold_does_not_revert_a_refunded_payment(): void
    {
        ['payment' => $payment] = $this->bookingScenario();
        $payments = User::factory()->create(['role' => 'payments']);

        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$payment->id}/refund")->assertStatus(200);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/hold")->assertStatus(200);

        $this->assertSame('refunded', $payment->fresh()->status);
    }

    public function test_refund_credits_the_client_wallet(): void
    {
        ['client' => $client, 'payment' => $payment] = $this->bookingScenario();
        $payments = User::factory()->create(['role' => 'payments']);

        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$payment->id}/refund")->assertStatus(200);

        // The booking client reads their wallet: balance == refunded amount.
        Sanctum::actingAs($client);
        $this->getJson('/api/client/wallet')
            ->assertStatus(200)
            ->assertJsonPath('balance', 500);
    }
}
