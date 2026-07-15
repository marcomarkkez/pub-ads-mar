<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * design.md §11 [F09] + §10 [F08]: when Support JOINS a client↔provider chat it
 * is announced (system message) and PII masking RELAXES for the thread.
 */
class SupportJoinConversationTest extends TestCase
{
    use RefreshDatabase;

    private function makeThread(): array
    {
        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);
        $support = User::factory()->create(['role' => 'support']);

        $space = Space::create([
            'user_id' => $provider->id,
            'name' => 'Test Space',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
        ]);

        $convo = Conversation::create([
            'space_id' => $space->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
        ]);
        $convo->messages()->create([
            'sender_user_id' => $provider->id,
            'body' => 'Call me at 555-123-4567',
        ]);

        return compact('client', 'provider', 'support', 'convo');
    }

    public function test_support_join_announces_and_relaxes_masking(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ['client' => $client, 'support' => $support, 'convo' => $convo] = $this->makeThread();

        // Before join: the client sees the phone masked.
        Sanctum::actingAs($client);
        $this->getJson("/api/conversations/{$convo->id}/messages")
            ->assertStatus(200)
            ->assertJsonPath('data.messages.0.body', 'Call me at [phone removed]');

        // Support joins.
        Sanctum::actingAs($support);
        $this->postJson("/api/support/conversations/{$convo->id}/join")
            ->assertStatus(200);

        $convo->refresh();
        $this->assertNotNull($convo->support_joined_at);
        // Announcement system message posted.
        $this->assertTrue($convo->messages()->where('body', 'Support has joined this conversation.')->exists());

        // After join: masking relaxes — the client now sees the raw phone.
        Sanctum::actingAs($client);
        $this->getJson("/api/conversations/{$convo->id}/messages")
            ->assertStatus(200)
            ->assertJsonPath('data.messages.0.body', 'Call me at 555-123-4567');
    }

    public function test_support_reads_thread_only_after_joining(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ['support' => $support, 'convo' => $convo] = $this->makeThread();

        Sanctum::actingAs($support);

        // Before joining, Support cannot read the client↔provider thread.
        $this->getJson("/api/conversations/{$convo->id}/messages")->assertStatus(403);

        // Join, then it can read.
        $this->postJson("/api/support/conversations/{$convo->id}/join")->assertStatus(200);
        $this->getJson("/api/conversations/{$convo->id}/messages")->assertStatus(200);
    }

    public function test_support_join_is_idempotent(): void
    {
        $this->seed(RolePermissionSeeder::class);
        ['support' => $support, 'convo' => $convo] = $this->makeThread();

        Sanctum::actingAs($support);
        $this->postJson("/api/support/conversations/{$convo->id}/join")->assertStatus(200);
        $this->postJson("/api/support/conversations/{$convo->id}/join")->assertStatus(200);

        // Only ONE announcement despite two joins.
        $this->assertEquals(
            1,
            $convo->messages()->where('body', 'Support has joined this conversation.')->count()
        );
    }
}
