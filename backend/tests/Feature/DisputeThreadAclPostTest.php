<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * design.md §7/§10 [F07/F08]: the Support-anchored dispute threads accept POSTs
 * only from the right counterparty (+ Support/Admin), and PII is NOT masked on
 * these staff-run threads. The normal direct thread still masks for the client.
 */
class DisputeThreadAclPostTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    /** Reject the proof (as the client) to auto-open the three dispute threads. */
    private function openDispute(array $s): array
    {
        Sanctum::actingAs($s['client']);
        $this->postJson("/api/client/proofs/{$s['proof']->id}/reject", ['reason' => 'x'])
            ->assertStatus(200);

        $find = fn (string $type) => Conversation::where('space_id', $s['space']->id)
            ->where('client_user_id', $s['client']->id)
            ->where('type', $type)->firstOrFail();

        return [
            'client_thread' => $find(Conversation::TYPE_SUPPORT_CLIENT),
            'provider_thread' => $find(Conversation::TYPE_SUPPORT_PROVIDER),
            'internal_thread' => $find(Conversation::TYPE_INTERNAL),
        ];
    }

    public function test_only_the_right_counterparty_can_post(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'provider' => $provider] = $s;
        $threads = $this->openDispute($s);

        $post = fn (int $id) => $this->postJson("/api/conversations/{$id}/messages", ['body' => 'hi']);

        // Client: can post to support_client, blocked elsewhere.
        Sanctum::actingAs($client);
        $post($threads['client_thread']->id)->assertStatus(201);
        $post($threads['provider_thread']->id)->assertStatus(403);
        $post($threads['internal_thread']->id)->assertStatus(403);

        // Provider: can post to support_provider, blocked elsewhere.
        Sanctum::actingAs($provider);
        $post($threads['provider_thread']->id)->assertStatus(201);
        $post($threads['client_thread']->id)->assertStatus(403);
        $post($threads['internal_thread']->id)->assertStatus(403);

        // Support: can post to all three.
        $support = User::factory()->create(['role' => 'support']);
        Sanctum::actingAs($support);
        $post($threads['client_thread']->id)->assertStatus(201);
        $post($threads['provider_thread']->id)->assertStatus(201);
        $post($threads['internal_thread']->id)->assertStatus(201);
    }

    public function test_pii_is_not_masked_on_a_dispute_thread(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client] = $s;
        $threads = $this->openDispute($s);

        Sanctum::actingAs($client);
        $this->postJson("/api/conversations/{$threads['client_thread']->id}/messages", [
            'body' => 'Reach me at 555-123-4567',
        ])->assertStatus(201);

        // The client re-reads the thread and sees the RAW phone (staff-run thread).
        $this->getJson("/api/conversations/{$threads['client_thread']->id}/messages")
            ->assertStatus(200)
            ->assertJsonFragment(['body' => 'Reach me at 555-123-4567']);
    }

    public function test_direct_thread_still_masks_pii_for_the_client(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'provider' => $provider, 'space' => $space] = $s;

        $direct = Conversation::create([
            'space_id' => $space->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'type' => Conversation::TYPE_DIRECT,
        ]);
        $direct->messages()->create([
            'sender_user_id' => $provider->id,
            'body' => 'Call me at 555-123-4567',
        ]);

        Sanctum::actingAs($client);
        $this->getJson("/api/conversations/{$direct->id}/messages")
            ->assertStatus(200)
            ->assertJsonPath('data.messages.0.body', 'Call me at [phone removed]');
    }
}
