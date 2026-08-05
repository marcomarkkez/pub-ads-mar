<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * UC-27 · design.json §10/§16 — internal Support↔Payments chats (both anchors null)
 * use a membership/role-derived ACL: a client can NEVER fetch or post; support and
 * payments can.
 */
class InternalThreadAclTest extends TestCase
{
    use RefreshDatabase;

    private function internalChat(User $opener): Chat
    {
        $chat = Chat::create([
            'opened_by_user_id' => $opener->id,
            'client_user_id' => null,
            'provider_user_id' => null,
            'status' => Chat::STATUS_OPEN,
        ]);
        $chat->participants()->create(['user_id' => $opener->id, 'side' => ChatParticipant::SIDE_PAYMENTS]);
        $chat->messages()->create(['sender_user_id' => $opener->id, 'body' => 'internal staff-only note', 'kind' => 'user']);

        return $chat;
    }

    public function test_client_cannot_fetch_or_post_internal_chat(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $client = User::factory()->create(['role' => 'client']);
        $payments = User::factory()->create(['role' => 'payments']);

        $chat = $this->internalChat($payments);

        // 404, not 403 — §21 rule 2 (BR-3). The internal Support↔Payments thread is the
        // sharpest case: a 403 would tell the client that staff have a private thread.
        Sanctum::actingAs($client);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(404);
        $this->postJson("/api/chats/{$chat->id}/messages", ['body' => 'hi'])->assertStatus(404);
    }

    public function test_payments_and_support_can_fetch_internal_chat(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $payments = User::factory()->create(['role' => 'payments']);
        $support = User::factory()->create(['role' => 'support']);

        $chat = $this->internalChat($payments);

        Sanctum::actingAs($payments);
        $this->getJson("/api/chats/{$chat->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.messages.0.body', 'internal staff-only note');

        Sanctum::actingAs($support);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(200);
    }
}
