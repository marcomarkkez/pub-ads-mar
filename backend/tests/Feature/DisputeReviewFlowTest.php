<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Conversation;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\WalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * design.md §7/§8 [F07/F10]: the client proof review drives the money.
 *  - accept  -> proof client_accepted, payment held, payout releasable.
 *  - reject  -> payout held + flag ("Payment held - <reason>") + 3 Support threads.
 * Release is gated on the client having accepted.
 */
class DisputeReviewFlowTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

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

        // Before acceptance: release is refused.
        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/release")
            ->assertStatus(422);

        // Client accepts.
        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/accept")->assertStatus(200);

        // Now Payments can release -> provider credited via escrow_release.
        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/release")
            ->assertStatus(200);

        $this->assertSame('released', $payment->fresh()->status);
        $this->assertTrue(
            WalletEntry::where('user_id', $provider->id)
                ->where('type', 'escrow_release')
                ->where('idempotency_key', "escrow_release:payment:{$payment->id}")
                ->exists()
        );
    }

    public function test_flag_subject_is_exactly_payment_held_with_reason(): void
    {
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'not displayed'])
            ->assertStatus(200);

        $ticket = Ticket::where('ticketable_type', Payment::class)
            ->where('ticketable_id', $payment->id)->firstOrFail();
        $this->assertSame('Payment held — not displayed', $ticket->subject);
    }

    public function test_flag_subject_has_no_dash_when_reason_missing(): void
    {
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject")->assertStatus(200);

        $ticket = Ticket::where('ticketable_type', Payment::class)
            ->where('ticketable_id', $payment->id)->firstOrFail();
        $this->assertSame('Payment held', $ticket->subject);
    }

    public function test_reject_posts_one_system_opener_per_thread(): void
    {
        ['client' => $client, 'space' => $space, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'x'])->assertStatus(200);

        foreach ([
            Conversation::TYPE_INTERNAL,
            Conversation::TYPE_SUPPORT_CLIENT,
            Conversation::TYPE_SUPPORT_PROVIDER,
        ] as $type) {
            $convo = Conversation::where('space_id', $space->id)
                ->where('client_user_id', $client->id)
                ->where('type', $type)->firstOrFail();

            $this->assertSame(1, $convo->messages()->where('kind', 'system')->count());
        }
    }

    public function test_flag_mismatch_is_backcompat_alias_of_reject(): void
    {
        ['client' => $client, 'space' => $space, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/flag-mismatch", ['reason' => 'y'])
            ->assertStatus(200);

        // Same effect as reject: payout held + 3 threads.
        $this->assertSame('held', $payment->fresh()->status);
        $this->assertSame(3, Conversation::where('space_id', $space->id)
            ->where('client_user_id', $client->id)
            ->whereIn('type', [
                Conversation::TYPE_INTERNAL,
                Conversation::TYPE_SUPPORT_CLIENT,
                Conversation::TYPE_SUPPORT_PROVIDER,
            ])->count());
    }

    public function test_reject_without_payment_attaches_ticket_to_ad(): void
    {
        ['client' => $client, 'ad' => $ad, 'payment' => $payment, 'proof' => $proof] = $this->bookingScenario();

        // Remove the payment so the ticket falls back to the Ad.
        $payment->delete();

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'z'])->assertStatus(200);

        $this->assertTrue(
            Ticket::where('ticketable_type', Ad::class)
                ->where('ticketable_id', $ad->id)->exists()
        );
    }
}
