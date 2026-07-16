<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * design.md §11 [F09]: Support has NO money authority — it only FLAGS refunds /
 * payout-holds (recorded as payment tickets Payments watches). Payout-hold also
 * parks the money in `held`. Support navigates ticket -> the dispute threads.
 */
class SupportPaymentFlagTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    public function test_flag_refund_creates_a_payment_ticket_without_moving_money(): void
    {
        ['payment' => $payment] = $this->bookingScenario();
        $support = User::factory()->create(['role' => 'support']);

        Sanctum::actingAs($support);
        $this->postJson("/api/support/payments/{$payment->id}/flag-refund", ['reason' => 'dup charge'])
            ->assertStatus(201);

        $this->assertTrue(
            Ticket::where('ticketable_type', Payment::class)
                ->where('ticketable_id', $payment->id)
                ->where('subject', 'Refund flagged by Support')->exists()
        );
        // Support does not move money: status unchanged.
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_flag_payout_hold_parks_status_held_and_creates_ticket(): void
    {
        ['payment' => $payment] = $this->bookingScenario();
        $support = User::factory()->create(['role' => 'support']);

        Sanctum::actingAs($support);
        $this->postJson("/api/support/payments/{$payment->id}/flag-payout-hold", ['reason' => 'review'])
            ->assertStatus(201);

        $this->assertSame('held', $payment->fresh()->status);
        $this->assertTrue(
            Ticket::where('ticketable_type', Payment::class)
                ->where('ticketable_id', $payment->id)
                ->where('subject', 'Payout hold flagged by Support')->exists()
        );
    }

    public function test_ticket_show_exposes_related_and_dispute_conversations(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'provider' => $provider, 'space' => $space, 'payment' => $payment, 'proof' => $proof] = $s;

        // A pre-existing direct thread + the dispute (opens the 3 support threads).
        $direct = Conversation::create([
            'space_id' => $space->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'type' => Conversation::TYPE_DIRECT,
        ]);
        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'x'])->assertStatus(200);

        $ticket = Ticket::where('ticketable_type', Payment::class)
            ->where('ticketable_id', $payment->id)->firstOrFail();

        $support = User::factory()->create(['role' => 'support']);
        Sanctum::actingAs($support);
        $res = $this->getJson("/api/support/tickets/{$ticket->id}")->assertStatus(200);

        $res->assertJsonPath('related_conversation_id', $direct->id);
        $this->assertNotNull($res->json('dispute_conversations.internal'));
        $this->assertNotNull($res->json('dispute_conversations.support_client'));
        $this->assertNotNull($res->json('dispute_conversations.support_provider'));
    }

    public function test_join_rejects_non_direct_threads(): void
    {
        ['space' => $space, 'client' => $client, 'provider' => $provider] = $this->bookingScenario();

        $internal = Conversation::create([
            'space_id' => $space->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'type' => Conversation::TYPE_INTERNAL,
        ]);

        $support = User::factory()->create(['role' => 'support']);
        Sanctum::actingAs($support);
        $this->postJson("/api/support/conversations/{$internal->id}/join")->assertStatus(422);
    }
}
