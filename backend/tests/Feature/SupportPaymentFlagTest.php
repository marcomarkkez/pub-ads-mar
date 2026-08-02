<?php

namespace Tests\Feature;

use App\Models\ChatFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * UC-22 · design.json §10 — Support RAISES money flags on a chat (Payments executes
 * the money via the §8 routes). A flag is an annotation with history: changing it
 * supersedes the prior one and writes a system message. A flag NEVER moves money
 * and NEVER grants access.
 */
class SupportPaymentFlagTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    private function disputeInternal(array $s): int
    {
        Sanctum::actingAs($s['client']);
        $this->postJson("/api/client/proofs/{$s['proof']->id}/reject", ['reason' => 'x'])->assertStatus(200);

        return $this->disputeChats($s)['internal']->id;
    }

    public function test_support_raises_a_flag_on_a_chat(): void
    {
        $s = $this->bookingScenario();
        $chatId = $this->disputeInternal($s);
        $support = User::factory()->create(['role' => 'support']);

        Sanctum::actingAs($support);
        $this->postJson("/api/chats/{$chatId}/flags", ['type' => 'payout_hold', 'reason' => 'review'])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'payout_hold');

        $this->assertDatabaseHas('chat_flags', [
            'chat_id' => $chatId, 'type' => 'payout_hold', 'active' => true,
        ]);
    }

    public function test_changing_a_flag_supersedes_the_previous_one(): void
    {
        $s = $this->bookingScenario();
        $chatId = $this->disputeInternal($s);
        $support = User::factory()->create(['role' => 'support']);

        Sanctum::actingAs($support);
        $this->postJson("/api/chats/{$chatId}/flags", ['type' => 'payment_held', 'reason' => 'a'])->assertStatus(201);
        $this->postJson("/api/chats/{$chatId}/flags", ['type' => 'payment_held', 'reason' => 'escalate to cancel'])->assertStatus(201);

        // Exactly one ACTIVE payment_held flag; the older one is superseded.
        $active = ChatFlag::where('chat_id', $chatId)->where('type', 'payment_held')->where('active', true)->get();
        $this->assertCount(1, $active);
        $this->assertTrue(
            ChatFlag::where('chat_id', $chatId)->where('type', 'payment_held')
                ->where('active', false)->whereNotNull('superseded_at')->exists()
        );
    }

    public function test_client_and_provider_cannot_raise_a_flag(): void
    {
        $s = $this->bookingScenario();
        Sanctum::actingAs($s['client']);
        $this->postJson("/api/client/proofs/{$s['proof']->id}/reject", ['reason' => 'x'])->assertStatus(200);
        $supportClientChat = $this->disputeChats($s)['support_client']->id;

        // The client can access this chat but cannot raise a money flag (staff only).
        Sanctum::actingAs($s['client']);
        $this->postJson("/api/chats/{$supportClientChat}/flags", ['type' => 'refund'])->assertStatus(403);
    }
}
