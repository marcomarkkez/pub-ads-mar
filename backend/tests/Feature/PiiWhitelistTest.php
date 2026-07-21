<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\ChatParticipant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * UC-8 · design.md §10 — in a client↔provider chat the booked/attached space's OWN
 * address is whitelisted (the parties need it), while any OTHER street address is masked.
 */
class PiiWhitelistTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    public function test_attached_space_address_is_kept_but_other_addresses_are_masked(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'provider' => $provider, 'space' => $space] = $s;

        $space->update(['location_text' => '123 Main St']);

        $chat = Chat::create([
            'opened_by_user_id' => $client->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'status' => Chat::STATUS_OPEN,
        ]);
        $chat->participants()->create(['user_id' => $client->id, 'side' => ChatParticipant::SIDE_CLIENT]);
        $chat->participants()->create(['user_id' => $provider->id, 'side' => ChatParticipant::SIDE_PROVIDER]);
        // The booked space is attached as a chat_object (the old space_id anchor is retired).
        $chat->objects()->create([
            'objectable_type' => $space->getMorphClass(),
            'objectable_id' => $space->id,
            'attached_by_user_id' => $client->id,
        ]);
        $chat->messages()->create([
            'sender_user_id' => $provider->id,
            'body' => 'The billboard is at 123 Main St, not at 999 Secret Ave.',
            'kind' => 'user',
        ]);

        Sanctum::actingAs($client);
        $body = $this->getJson("/api/chats/{$chat->id}")
            ->assertStatus(200)
            ->json('data.messages.0.body');

        $this->assertStringContainsString('123 Main St', $body);
        $this->assertStringContainsString('[address removed]', $body);
        $this->assertStringNotContainsString('999 Secret Ave', $body);
    }
}
