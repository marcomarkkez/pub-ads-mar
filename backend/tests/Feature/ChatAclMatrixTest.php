<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Chat;
use App\Models\ChatFlag;
use App\Models\ChatParticipant;
use App\Models\Collaborator;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * design.json §10/§16 + chat-acl-security-review R1-R9 — the ACL invariants that the
 * unified chat model must hold: Admin never posts (R1); installators are DENIED
 * (R4); closed chats are not user-reopenable + history hidden (R3); nature is derived;
 * a flag NEVER grants access.
 */
class ChatAclMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function clientChat(User $client, ?User $provider = null): Chat
    {
        return Chat::create([
            'opened_by_user_id' => $client->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider?->id,
            'status' => Chat::STATUS_OPEN,
        ]);
    }

    /** R1 — Admin is read-only/incognito: reads via oversight, NEVER posts. */
    public function test_admin_cannot_post_but_can_read_via_oversight(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);
        $admin = User::factory()->create(['role' => 'admin']);
        $chat = $this->clientChat($client, $provider);
        $chat->messages()->create(['sender_user_id' => $client->id, 'body' => 'hi', 'kind' => 'user']);

        Sanctum::actingAs($admin);
        // No chats.create for admin → the shared post path is refused.
        $this->postJson("/api/chats/{$chat->id}/messages", ['body' => 'ghost'])->assertStatus(403);
        // Reads the full chat silently via oversight.
        $this->getJson("/api/admin/oversight/chats/{$chat->id}")->assertStatus(200);
    }

    /** R4 — provider/client collaborator subroles gate chat access; installator DENIED. */
    public function test_installator_collaborator_is_denied_while_manager_is_allowed(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $installer = User::factory()->create(['role' => 'client']);
        $manager = User::factory()->create(['role' => 'client']);

        $campaign = Campaign::create(['user_id' => $owner->id, 'name' => 'C', 'status' => 'active']);
        Collaborator::create(['campaign_id' => $campaign->id, 'invited_by_user_id' => $owner->id, 'user_id' => $installer->id, 'email' => 'i@x.test', 'role' => 'installator', 'status' => 'accepted']);
        Collaborator::create(['campaign_id' => $campaign->id, 'invited_by_user_id' => $owner->id, 'user_id' => $manager->id, 'email' => 'm@x.test', 'role' => 'manager', 'status' => 'accepted']);

        $chat = $this->clientChat($owner); // support_client chat on the owner's account

        Sanctum::actingAs($manager);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(200);

        Sanctum::actingAs($installer);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(403);
    }

    /** R3 — a closed chat is NOT user-reopenable; only Admin reopens. */
    public function test_closed_chat_is_admin_reopenable_only(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $admin = User::factory()->create(['role' => 'admin']);
        $chat = $this->clientChat($client);
        $chat->update(['status' => Chat::STATUS_CLOSED, 'closed_at' => now(), 'closed_by_user_id' => $client->id]);

        // The opener (client) has no reopen route; the admin route is role-sealed.
        Sanctum::actingAs($client);
        $this->postJson("/api/admin/chats/{$chat->id}/reopen")->assertStatus(403);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/chats/{$chat->id}/reopen")->assertStatus(200);
        $this->assertSame(Chat::STATUS_OPEN, $chat->fresh()->status);

        // §2 marks reopen 📝. It is the ONLY write an admin has on a chat, which is precisely
        // why it must leave a trace: an unaudited admin write is indistinguishable from none.
        $entry = AuditLog::where('action', 'chat_reopen')->sole();
        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(Chat::STATUS_CLOSED, $entry->before['status']);
        $this->assertSame(Chat::STATUS_OPEN, $entry->after['status']);
    }

    /** R3 — a closed chat's history is hidden from users (visible to Admin via oversight). */
    public function test_closed_chat_history_is_hidden_from_users(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $admin = User::factory()->create(['role' => 'admin']);
        $chat = $this->clientChat($client);
        $chat->messages()->create(['sender_user_id' => $client->id, 'body' => 'secret history', 'kind' => 'user']);
        $chat->update(['status' => Chat::STATUS_CLOSED, 'closed_at' => now(), 'closed_by_user_id' => $client->id]);

        Sanctum::actingAs($client);
        $this->getJson("/api/chats/{$chat->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.closed', true)
            ->assertJsonPath('data.messages', []);

        // Admin still sees the full history via oversight.
        Sanctum::actingAs($admin);
        $this->getJson("/api/admin/oversight/chats/{$chat->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['body' => 'secret history']);
    }

    /** design.json §10/§16 — nature is DERIVED from the side anchors, never a column. */
    public function test_nature_is_derived_from_anchors(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);

        $this->assertSame(Chat::NATURE_CLIENT_PROVIDER, $this->clientChat($client, $provider)->deriveNature());
        $this->assertSame(Chat::NATURE_SUPPORT_CLIENT, $this->clientChat($client)->deriveNature());
        $this->assertSame(Chat::NATURE_SUPPORT_PROVIDER, Chat::create(['opened_by_user_id' => $provider->id, 'provider_user_id' => $provider->id])->deriveNature());
        $this->assertSame(Chat::NATURE_INTERNAL, Chat::create(['opened_by_user_id' => $provider->id])->deriveNature());
    }

    /** design.json §16 — a flag NEVER grants access, and flipping it changes no ACL cell. */
    public function test_a_flag_never_grants_access(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);

        // A support_provider chat: the client is NOT a participant of any side.
        $chat = Chat::create(['opened_by_user_id' => $provider->id, 'provider_user_id' => $provider->id, 'status' => Chat::STATUS_OPEN]);
        $chat->participants()->create(['user_id' => $provider->id, 'side' => ChatParticipant::SIDE_PROVIDER]);

        Sanctum::actingAs($client);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(403);

        // Raising a flag on the chat does not change the client's access.
        $chat->flags()->create(['type' => ChatFlag::TYPE_PAYMENT_HELD, 'active' => true, 'created_by_user_id' => $provider->id]);
        $this->getJson("/api/chats/{$chat->id}")->assertStatus(403);
    }
}
