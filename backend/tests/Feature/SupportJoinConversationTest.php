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
 * UC-21 · design.md §10 — when Support JOINS a client↔provider chat it is announced
 * (system message + announced participant) and PII masking RELAXES for the chat.
 * Support cannot read the chat until it joins.
 */
class SupportJoinConversationTest extends TestCase
{
    use RefreshDatabase;

    private function makeChat(): array
    {
        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);
        $support = User::factory()->create(['role' => 'support']);

        $chat = Chat::create([
            'opened_by_user_id' => $client->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'status' => Chat::STATUS_OPEN,
        ]);
        $chat->participants()->create(['user_id' => $client->id, 'side' => ChatParticipant::SIDE_CLIENT]);
        $chat->participants()->create(['user_id' => $provider->id, 'side' => ChatParticipant::SIDE_PROVIDER]);
        $chat->messages()->create(['sender_user_id' => $provider->id, 'body' => 'Call me at 555-123-4567', 'kind' => 'user']);

        return compact('client', 'provider', 'support', 'chat');
    }

    public function test_support_join_announces_and_relaxes_masking(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ['client' => $client, 'support' => $support, 'chat' => $chat] = $this->makeChat();

        Sanctum::actingAs($client);
        $this->getJson("/api/chats/{$chat->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.messages.0.body', 'Call me at [phone removed]');

        Sanctum::actingAs($support);
        $this->postJson("/api/chats/{$chat->id}/join")->assertStatus(200);

        $chat->refresh();
        $this->assertNotNull($chat->support_joined_at);
        $this->assertTrue($chat->messages()->where('body', 'Support has joined this conversation.')->exists());
        $this->assertTrue($chat->participants()->where('user_id', $support->id)->where('announced', true)->exists());

        Sanctum::actingAs($client);
        $this->getJson("/api/chats/{$chat->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.messages.0.body', 'Call me at 555-123-4567');
    }

    public function test_support_reads_chat_only_after_joining(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ['support' => $support, 'chat' => $chat] = $this->makeChat();

        Sanctum::actingAs($support);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(403);

        $this->postJson("/api/chats/{$chat->id}/join")->assertStatus(200);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(200);
    }

    public function test_support_join_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ['support' => $support, 'chat' => $chat] = $this->makeChat();

        Sanctum::actingAs($support);
        $this->postJson("/api/chats/{$chat->id}/join")->assertStatus(200);
        $this->postJson("/api/chats/{$chat->id}/join")->assertStatus(200);

        $this->assertEquals(1, $chat->messages()->where('body', 'Support has joined this conversation.')->count());
    }

    public function test_join_rejects_non_client_provider_chats(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $support = User::factory()->create(['role' => 'support']);
        $payments = User::factory()->create(['role' => 'payments']);

        // An internal chat (both anchors null) cannot be joined.
        $internal = Chat::create(['opened_by_user_id' => $payments->id, 'status' => Chat::STATUS_OPEN]);

        Sanctum::actingAs($support);
        $this->postJson("/api/chats/{$internal->id}/join")->assertStatus(422);
    }
}
