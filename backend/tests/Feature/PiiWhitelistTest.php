<?php

namespace Tests\Feature;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * design.md §10 [F08]: in a direct thread the booked space's OWN address is
 * whitelisted (the parties need it), while any OTHER street address is masked.
 */
class PiiWhitelistTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    public function test_booked_space_address_is_kept_but_other_addresses_are_masked(): void
    {
        $s = $this->bookingScenario();
        ['client' => $client, 'provider' => $provider, 'space' => $space] = $s;

        // Give the booked space a known address.
        $space->update(['location_text' => '123 Main St']);

        $direct = Conversation::create([
            'space_id' => $space->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'type' => Conversation::TYPE_DIRECT,
        ]);
        $direct->messages()->create([
            'sender_user_id' => $provider->id,
            'body' => 'The billboard is at 123 Main St, not at 999 Secret Ave.',
        ]);

        Sanctum::actingAs($client);
        $body = $this->getJson("/api/conversations/{$direct->id}/messages")
            ->assertStatus(200)
            ->json('data.messages.0.body');

        // The space's own address survives; the other one is masked.
        $this->assertStringContainsString('123 Main St', $body);
        $this->assertStringContainsString('[address removed]', $body);
        $this->assertStringNotContainsString('999 Secret Ave', $body);
    }
}
