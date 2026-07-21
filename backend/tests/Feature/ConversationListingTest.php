<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * UC-8/UC-9/UC-27 · design.md §10 — GET /chats lists each side's OWN chats (derived
 * membership), never the other side's dispute chat nor the internal Support↔Payments
 * chat. Support sees its derived queue; a client opens a chat, staff do not (via this route).
 */
class ConversationListingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    private function withDispute(array $s): void
    {
        // A plain client↔provider chat + the full dispute (3 support chats).
        $chat = Chat::create([
            'opened_by_user_id' => $s['client']->id,
            'client_user_id' => $s['client']->id,
            'provider_user_id' => $s['provider']->id,
            'status' => Chat::STATUS_OPEN,
        ]);
        $chat->participants()->create(['user_id' => $s['client']->id, 'side' => 'client']);
        $chat->participants()->create(['user_id' => $s['provider']->id, 'side' => 'provider']);

        Sanctum::actingAs($s['client']);
        $this->postJson("/api/client/proofs/{$s['proof']->id}/reject", ['reason' => 'x'])->assertStatus(200);
    }

    private function naturesFor(User $user): array
    {
        Sanctum::actingAs($user);

        return collect($this->getJson('/api/chats')->assertStatus(200)->json())
            ->map(fn ($chat) => $chat['nature'])
            ->sort()->values()->all();
    }

    public function test_client_sees_only_client_provider_and_support_client(): void
    {
        $s = $this->bookingScenario();
        $this->withDispute($s);

        $this->assertSame(['client_provider', 'support_client'], $this->naturesFor($s['client']));
    }

    public function test_provider_sees_only_client_provider_and_support_provider(): void
    {
        $s = $this->bookingScenario();
        $this->withDispute($s);

        $this->assertSame(['client_provider', 'support_provider'], $this->naturesFor($s['provider']));
    }

    public function test_support_sees_the_staff_facing_queue(): void
    {
        $s = $this->bookingScenario();
        $this->withDispute($s);

        $support = User::factory()->create(['role' => 'support']);
        // Support's queue = the 3 dispute chats (staff-facing), not the client↔provider chat.
        $this->assertSame(['internal', 'support_client', 'support_provider'], $this->naturesFor($support));
    }

    public function test_only_client_or_provider_can_open_a_chat(): void
    {
        $s = $this->bookingScenario();
        $support = User::factory()->create(['role' => 'support']);

        Sanctum::actingAs($support);
        $this->postJson('/api/chats', ['object_type' => 'space', 'object_id' => $s['space']->id])
            ->assertStatus(403);

        Sanctum::actingAs($s['client']);
        $this->postJson('/api/chats', ['object_type' => 'space', 'object_id' => $s['space']->id])
            ->assertStatus(201);
    }
}
