<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * UC-7 · design.md §7/§10 — the dispute chats accept POSTs only from the right
 * counterparty (+ Support), and PII is NOT masked on these staff-run chats. The
 * client↔provider chat still masks for the client.
 */
class DisputeThreadAclPostTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    private function openDispute(array $s): array
    {
        Sanctum::actingAs($s['client']);
        $this->postJson("/api/client/proofs/{$s['proof']->id}/reject", ['reason' => 'x'])
            ->assertStatus(200);

        return $this->disputeChats($s);
    }

    public function test_only_the_right_counterparty_can_post(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'provider' => $provider] = $s;
        $chats = $this->openDispute($s);

        $post = fn (int $id) => $this->postJson("/api/chats/{$id}/messages", ['body' => 'hi']);

        Sanctum::actingAs($client);
        $post($chats['support_client']->id)->assertStatus(201);
        $post($chats['support_provider']->id)->assertStatus(403);
        $post($chats['internal']->id)->assertStatus(403);

        Sanctum::actingAs($provider);
        $post($chats['support_provider']->id)->assertStatus(201);
        $post($chats['support_client']->id)->assertStatus(403);
        $post($chats['internal']->id)->assertStatus(403);

        $support = User::factory()->create(['role' => 'support']);
        Sanctum::actingAs($support);
        $post($chats['support_client']->id)->assertStatus(201);
        $post($chats['support_provider']->id)->assertStatus(201);
        $post($chats['internal']->id)->assertStatus(201);
    }

    public function test_pii_is_not_masked_on_a_dispute_chat(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client] = $s;
        $chats = $this->openDispute($s);

        Sanctum::actingAs($client);
        $this->postJson("/api/chats/{$chats['support_client']->id}/messages", [
            'body' => 'Reach me at 555-123-4567',
        ])->assertStatus(201);

        $this->getJson("/api/chats/{$chats['support_client']->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['body' => 'Reach me at 555-123-4567']);
    }

    public function test_direct_chat_still_masks_pii_for_the_client(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'provider' => $provider] = $s;

        $chat = Chat::create([
            'opened_by_user_id' => $client->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'status' => Chat::STATUS_OPEN,
        ]);
        $chat->participants()->create(['user_id' => $client->id, 'side' => ChatParticipant::SIDE_CLIENT]);
        $chat->participants()->create(['user_id' => $provider->id, 'side' => ChatParticipant::SIDE_PROVIDER]);
        $chat->messages()->create(['sender_user_id' => $provider->id, 'body' => 'Call me at 555-123-4567', 'kind' => 'user']);

        Sanctum::actingAs($client);
        $this->getJson("/api/chats/{$chat->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.messages.0.body', 'Call me at [phone removed]');
    }
}
