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
 * design.md §10 [F08]: internal Support↔Payments threads use a membership-based
 * ACL — a client can NEVER fetch an internal-thread message. This asserts it.
 */
class InternalThreadAclTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_fetch_internal_thread_messages(): void
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

        // An internal thread — even though the client is on the client_user_id
        // column, the internal type must block them.
        $internal = Conversation::create([
            'space_id' => $space->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'type' => 'internal',
        ]);
        $internal->messages()->create([
            'sender_user_id' => $provider->id,
            'body' => 'internal staff-only note',
        ]);

        Sanctum::actingAs($client);

        $this->getJson("/api/conversations/{$internal->id}/messages")
            ->assertStatus(403);

        $this->postJson("/api/conversations/{$internal->id}/messages", ['body' => 'hi'])
            ->assertStatus(403);
    }

    public function test_payments_staff_can_fetch_internal_thread_messages(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);
        $payments = User::factory()->create(['role' => 'payments']);

        $space = Space::create([
            'user_id' => $provider->id,
            'name' => 'Test Space',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
        ]);

        $internal = Conversation::create([
            'space_id' => $space->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'type' => 'internal',
        ]);
        $internal->messages()->create([
            'sender_user_id' => $provider->id,
            'body' => 'internal staff-only note',
        ]);

        Sanctum::actingAs($payments);

        $this->getJson("/api/conversations/{$internal->id}/messages")
            ->assertStatus(200)
            ->assertJsonPath('data.messages.0.body', 'internal staff-only note');
    }
}
