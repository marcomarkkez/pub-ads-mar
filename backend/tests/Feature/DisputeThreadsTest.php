<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\ChatFlag;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * UC-7 · design.md §7/§10 — when the client rejects the proof, the payout is HELD
 * and THREE dispute chats auto-open (Support↔Client / ↔Provider / ↔Payments), each
 * anchored to the held payment+booking with a payment_held flag, visible only to
 * Support + the one relevant counterparty.
 */
class DisputeThreadsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    public function test_client_reject_opens_three_chats_holds_payout_and_raises_flag(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $s;

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'Ad was never displayed'])
            ->assertStatus(200);

        $this->assertSame('held', $payment->fresh()->status);

        $chats = $this->disputeChats($s);
        $this->assertSame(Chat::NATURE_INTERNAL, $chats['internal']->deriveNature());
        $this->assertSame(Chat::NATURE_SUPPORT_CLIENT, $chats['support_client']->deriveNature());
        $this->assertSame(Chat::NATURE_SUPPORT_PROVIDER, $chats['support_provider']->deriveNature());

        // Each dispute chat carries an active payment_held flag with the reason.
        foreach ($chats as $chat) {
            $flag = $chat->flags()->where('active', true)->first();
            $this->assertNotNull($flag);
            $this->assertSame(ChatFlag::TYPE_PAYMENT_HELD, $flag->type);
        }

        // The payment is attached to all three (Payments sees WHY money is held).
        $this->assertSame(3, Payment::find($payment->id)->chatObjects()->count());
    }

    public function test_reject_is_idempotent_and_does_not_duplicate_chats(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'payment' => $payment, 'proof' => $proof] = $s;

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'first'])->assertStatus(200);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'second'])->assertStatus(200);

        // Still exactly three chats attached to the payment.
        $this->assertSame(3, Payment::find($payment->id)->chatObjects()->count());
    }

    public function test_dispute_chats_are_visible_only_to_the_right_counterparty(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'provider' => $provider, 'proof' => $proof] = $s;

        Sanctum::actingAs($client);
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'x'])->assertStatus(200);

        $chats = $this->disputeChats($s);

        // Client: sees support_client, blocked from support_provider and internal.
        Sanctum::actingAs($client);
        $this->getJson("/api/chats/{$chats['support_client']->id}")->assertStatus(200);
        $this->getJson("/api/chats/{$chats['support_provider']->id}")->assertStatus(403);
        $this->getJson("/api/chats/{$chats['internal']->id}")->assertStatus(403);

        // Provider: sees support_provider, blocked from support_client and internal.
        Sanctum::actingAs($provider);
        $this->getJson("/api/chats/{$chats['support_provider']->id}")->assertStatus(200);
        $this->getJson("/api/chats/{$chats['support_client']->id}")->assertStatus(403);
        $this->getJson("/api/chats/{$chats['internal']->id}")->assertStatus(403);

        // Support: sees all three (its derived queue).
        $support = User::factory()->create(['role' => 'support']);
        Sanctum::actingAs($support);
        $this->getJson("/api/chats/{$chats['support_client']->id}")->assertStatus(200);
        $this->getJson("/api/chats/{$chats['support_provider']->id}")->assertStatus(200);
        $this->getJson("/api/chats/{$chats['internal']->id}")->assertStatus(200);

        // Payments: sees only the internal Support↔Payments chat.
        $payments = User::factory()->create(['role' => 'payments']);
        Sanctum::actingAs($payments);
        $this->getJson("/api/chats/{$chats['internal']->id}")->assertStatus(200);
        $this->getJson("/api/chats/{$chats['support_client']->id}")->assertStatus(403);
        $this->getJson("/api/chats/{$chats['support_provider']->id}")->assertStatus(403);
    }
}
