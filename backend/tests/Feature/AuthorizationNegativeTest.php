<?php

namespace Tests\Feature;

use App\Models\Proof;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * Authorization boundaries around the proof/dispute/money surface (F07-F10):
 * a client only acts on their OWN proofs, and role prefixes are sealed.
 */
class AuthorizationNegativeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    public function test_client_cannot_reject_another_clients_proof(): void
    {
        ['proof' => $proof] = $this->bookingScenario();
        $stranger = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($stranger);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'nope'])
            ->assertStatus(403);

        // And the proof is untouched.
        $this->assertSame('pending_review', $proof->fresh()->status);
    }

    public function test_provider_cannot_use_the_client_proof_review_routes(): void
    {
        ['provider' => $provider, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($provider);
        $this->postJson("/api/client/proofs/{$proof->id}/accept")->assertStatus(403);
        $this->postJson("/api/client/proofs/{$proof->id}/reject")->assertStatus(403);
    }

    public function test_client_cannot_reach_payments_or_support_prefixes(): void
    {
        ['client' => $client, 'payment' => $payment] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->getJson('/api/payments/payments')->assertStatus(403);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/hold")->assertStatus(403);
        $this->getJson('/api/support/tickets')->assertStatus(403);
        $this->postJson("/api/support/payments/{$payment->id}/flag-refund")->assertStatus(403);
    }
}
