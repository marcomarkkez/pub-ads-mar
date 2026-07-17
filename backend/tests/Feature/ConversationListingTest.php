<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * design.md §7/§10 [F07/F08]: GET /conversations lists each party's OWN direct
 * thread plus their OWN Support-anchored dispute thread — never the other side's
 * dispute thread nor the internal Support↔Payments thread.
 */
class ConversationListingTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    /** A direct thread + a full dispute (opens the three support threads). */
    private function withDispute(array $s): void
    {
        Conversation::create([
            'space_id' => $s['space']->id,
            'client_user_id' => $s['client']->id,
            'provider_user_id' => $s['provider']->id,
            'type' => Conversation::TYPE_DIRECT,
        ]);

        Sanctum::actingAs($s['client']);
        $this->postJson("/api/client/proofs/{$s['proof']->id}/reject", ['reason' => 'x'])
            ->assertStatus(200);
    }

    public function test_client_sees_only_direct_and_support_client(): void
    {
        $s = $this->bookingScenario();
        $this->withDispute($s);

        Sanctum::actingAs($s['client']);
        $types = collect($this->getJson('/api/conversations')->assertStatus(200)->json())
            ->pluck('type')->sort()->values()->all();

        $this->assertSame(['direct', 'support_client'], $types);
    }

    public function test_provider_sees_only_direct_and_support_provider(): void
    {
        $s = $this->bookingScenario();
        $this->withDispute($s);

        Sanctum::actingAs($s['provider']);
        $types = collect($this->getJson('/api/conversations')->assertStatus(200)->json())
            ->pluck('type')->sort()->values()->all();

        $this->assertSame(['direct', 'support_provider'], $types);
    }

    public function test_only_a_client_can_start_a_conversation(): void
    {
        $s = $this->bookingScenario();
        $support = User::factory()->create(['role' => 'support']);

        // Valid payload so we hit the role check, not validation.
        Sanctum::actingAs($support);
        $this->postJson('/api/conversations', ['space_id' => $s['space']->id])
            ->assertStatus(403);

        Sanctum::actingAs($s['client']);
        $this->postJson('/api/conversations', ['space_id' => $s['space']->id])
            ->assertStatus(201);
    }
}
