<?php

namespace Tests\Feature;

use App\Models\RolePermission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * B9 · design.md §7/§17 — Payments does NOT review proof CONTENT.
 *
 * §2 gives Payments 👁 on Proof, not ✏: it may look at the proof behind a held
 * payout, but the verdict is the CLIENT's (POST /client/proofs/{proof}/accept
 * or /reject). `GET /payments/proofs` and its approve/reject siblings existed
 * in violation of that for months; these tests keep them dead.
 */
class PaymentsNoProofReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_the_payments_proof_review_routes_no_longer_exist(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'payments']));

        $this->getJson('/api/payments/proofs')->assertStatus(404);
        $this->postJson('/api/payments/proofs/1/approve')->assertStatus(404);
        $this->postJson('/api/payments/proofs/1/reject')->assertStatus(404);
    }

    public function test_payments_keeps_read_but_loses_update_on_proofs(): void
    {
        // 👁 stays — Payments must be able to SEE the proof behind a hold.
        $this->assertTrue(RolePermission::roleHasPermission('payments', 'proofs', 'read'));

        // ✏ is gone — no content verdict.
        $this->assertFalse(RolePermission::roleHasPermission('payments', 'proofs', 'update'));
    }

    public function test_the_proof_verdict_belongs_to_the_client(): void
    {
        // The replacement path still exists and is owner-scoped, so a caller with
        // no claim on the proof gets nothing back — not a 200, not a 403.
        Sanctum::actingAs(User::factory()->create(['role' => 'client']));

        $this->postJson('/api/client/proofs/999999/accept')->assertStatus(404);
    }
}
