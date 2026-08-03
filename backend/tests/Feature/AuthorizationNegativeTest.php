<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * Authorization boundaries around the proof/dispute/chat surface (UC-7/UC-8):
 * a client only acts on their OWN proofs, role prefixes are sealed, and chat object
 * attach is OWNERSHIP-CHECKED (R2 — no private-object leak).
 */
class AuthorizationNegativeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    public function test_client_cannot_reject_another_clients_proof(): void
    {
        ['proof' => $proof] = $this->bookingScenario();
        $stranger = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($stranger);
        // 404, not 403 — §21 rule 2 (Q37): a stranger must not learn that this proof exists.
        $this->postJson("/api/client/proofs/{$proof->id}/reject", ['reason' => 'nope'])->assertStatus(404);
        $this->assertSame('pending_review', $proof->fresh()->status);
    }

    public function test_provider_cannot_use_the_client_proof_review_routes(): void
    {
        ['provider' => $provider, 'proof' => $proof] = $this->bookingScenario();

        Sanctum::actingAs($provider);
        $this->postJson("/api/client/proofs/{$proof->id}/accept")->assertStatus(403);
        $this->postJson("/api/client/proofs/{$proof->id}/reject")->assertStatus(403);
    }

    public function test_client_cannot_reach_payments_or_support_prefixes(): void
    {
        ['client' => $client] = $this->bookingScenario();

        Sanctum::actingAs($client);
        $this->getJson('/api/payments/payments')->assertStatus(403);
        $this->getJson('/api/support/dashboard')->assertStatus(403);
    }

    /**
     * R2 · design.json §16 — a client cannot attach ANOTHER account's private object
     * (ad/campaign) to a chat, which would leak that object back on show.
     */
    public function test_client_cannot_attach_a_foreign_private_object(): void
    {
        $s = $this->bookingScenario();
        $client = $s['client'];

        // A foreign client's private campaign + ad.
        $otherClient = User::factory()->create(['role' => 'client']);
        $foreignCampaign = Campaign::create(['user_id' => $otherClient->id, 'name' => 'Foreign', 'status' => 'active']);

        // Open a support chat as the client (objectless).
        Sanctum::actingAs($client);
        $chatId = $this->postJson('/api/chats', ['body' => 'help'])->assertStatus(201)->json('id');

        // Attaching the foreign campaign is refused.
        $this->postJson("/api/chats/{$chatId}/objects", ['object_type' => 'campaign', 'object_id' => $foreignCampaign->id])
            ->assertStatus(403);

        // Attaching another provider's ad (not this client's) is refused too.
        $foreignAd = Ad::create([
            'space_id' => $s['space']->id, 'provider_user_id' => $s['provider']->id,
            'name' => 'Foreign Ad', 'media_type' => 'image', 'status' => 'active',
        ]);
        $this->postJson("/api/chats/{$chatId}/objects", ['object_type' => 'ad', 'object_id' => $foreignAd->id])
            ->assertStatus(403);
    }
}
