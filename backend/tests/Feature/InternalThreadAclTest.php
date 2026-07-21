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
 * UC-27 · design.md §10/§16 — internal Support↔Payments chats (both anchors null)
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

        Sanctum::actingAs($client);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(403);
        $this->postJson("/api/chats/{$chat->id}/messages", ['body' => 'hi'])->assertStatus(403);
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
