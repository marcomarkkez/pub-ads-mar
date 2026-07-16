<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Payment;
use App\Models\Proof;
use App\Models\Space;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * design.md §7/§10/§11: when the client rejects the proof, the payout is HELD,
 * Payments is flagged ("Payment held — <reason>"), and three Support-anchored
 * threads open automatically (internal / support_client / support_provider),
 * each visible only to Support + the one relevant counterparty.
 */
class DisputeThreadsTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);

        $space = Space::create([
            'user_id' => $provider->id,
            'name' => 'Test Space',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
        ]);

        $ad = Ad::create([
            'space_id' => $space->id,
            'provider_user_id' => $provider->id,
            'name' => 'Test Ad',
            'media_type' => 'image',
            'status' => 'active',
        ]);

        $booking = Booking::create([
            'client_user_id' => $client->id,
            'space_id' => $space->id,
            'ad_id' => $ad->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'total_price' => 500,
            'status' => 'waiting_proof',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 500,
            'status' => 'pending',
        ]);

        $proof = Proof::create([
            'ad_id' => $ad->id,
            'booking_id' => $booking->id,
            'uploaded_by_user_id' => $provider->id,
            'media_type' => 'image',
            'file_path' => 'proofs/x.jpg',
            'file_name' => 'x.jpg',
            'status' => 'pending_review',
        ]);

        return compact('client', 'provider', 'space', 'ad', 'booking', 'payment', 'proof');
    }

    public function test_client_reject_opens_three_threads_holds_payout_and_flags_payments(): void
    {
        ['client' => $client, 'space' => $space, 'payment' => $payment, 'proof' => $proof] = $this->scenario();

        Sanctum::actingAs($client);

        $this->postJson("/api/proofs/{$proof->id}/reject", ['reason' => 'Ad was never displayed'])
            ->assertStatus(200);

        // Payout held.
        $this->assertSame('held', $payment->fresh()->status);

        // Three Support-anchored threads, all keyed to the same space+client.
        foreach ([
            Conversation::TYPE_INTERNAL,
            Conversation::TYPE_SUPPORT_CLIENT,
            Conversation::TYPE_SUPPORT_PROVIDER,
        ] as $type) {
            $this->assertDatabaseHas('conversations', [
                'space_id' => $space->id,
                'client_user_id' => $client->id,
                'type' => $type,
            ]);
        }

        // Payments flagged with an informative subject.
        $ticket = Ticket::where('ticketable_type', Payment::class)
            ->where('ticketable_id', $payment->id)
            ->first();
        $this->assertNotNull($ticket);
        $this->assertStringStartsWith('Payment held', $ticket->subject);
        $this->assertStringContainsString('Ad was never displayed', $ticket->subject);
    }

    public function test_reject_is_idempotent_and_does_not_duplicate_threads(): void
    {
        ['client' => $client, 'space' => $space, 'proof' => $proof] = $this->scenario();

        Sanctum::actingAs($client);

        $this->postJson("/api/proofs/{$proof->id}/reject", ['reason' => 'first'])->assertStatus(200);
        $this->postJson("/api/proofs/{$proof->id}/reject", ['reason' => 'second'])->assertStatus(200);

        $count = Conversation::where('space_id', $space->id)
            ->where('client_user_id', $client->id)
            ->whereIn('type', [
                Conversation::TYPE_INTERNAL,
                Conversation::TYPE_SUPPORT_CLIENT,
                Conversation::TYPE_SUPPORT_PROVIDER,
            ])->count();

        $this->assertSame(3, $count);
    }

    public function test_dispute_threads_are_visible_only_to_the_right_counterparty(): void
    {
        ['client' => $client, 'provider' => $provider, 'space' => $space, 'proof' => $proof] = $this->scenario();

        Sanctum::actingAs($client);
        $this->postJson("/api/proofs/{$proof->id}/reject", ['reason' => 'x'])->assertStatus(200);

        $supportClient = Conversation::where('space_id', $space->id)
            ->where('client_user_id', $client->id)
            ->where('type', Conversation::TYPE_SUPPORT_CLIENT)->firstOrFail();
        $supportProvider = Conversation::where('space_id', $space->id)
            ->where('client_user_id', $client->id)
            ->where('type', Conversation::TYPE_SUPPORT_PROVIDER)->firstOrFail();
        $internal = Conversation::where('space_id', $space->id)
            ->where('client_user_id', $client->id)
            ->where('type', Conversation::TYPE_INTERNAL)->firstOrFail();

        // Client: sees support_client, blocked from support_provider and internal.
        Sanctum::actingAs($client);
        $this->getJson("/api/conversations/{$supportClient->id}/messages")->assertStatus(200);
        $this->getJson("/api/conversations/{$supportProvider->id}/messages")->assertStatus(403);
        $this->getJson("/api/conversations/{$internal->id}/messages")->assertStatus(403);

        // Provider: sees support_provider, blocked from support_client and internal.
        Sanctum::actingAs($provider);
        $this->getJson("/api/conversations/{$supportProvider->id}/messages")->assertStatus(200);
        $this->getJson("/api/conversations/{$supportClient->id}/messages")->assertStatus(403);
        $this->getJson("/api/conversations/{$internal->id}/messages")->assertStatus(403);

        // Support: sees all three.
        $support = User::factory()->create(['role' => 'support']);
        Sanctum::actingAs($support);
        $this->getJson("/api/conversations/{$supportClient->id}/messages")->assertStatus(200);
        $this->getJson("/api/conversations/{$supportProvider->id}/messages")->assertStatus(200);
        $this->getJson("/api/conversations/{$internal->id}/messages")->assertStatus(200);
    }
}
