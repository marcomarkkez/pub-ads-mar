<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

/**
 * UC-32 · design.json §8/§12 — the Admin payout-stop window and the clawback.
 *
 * The two are one use case and two different animals, and the tests are written to
 * keep them apart: a STOP happens before the money moves (local, no ledger entry, the
 * window can expire), a CLAWBACK happens after it moved (a new negative ledger entry
 * against the provider, no timer, never a netting of the original release).
 */
class PayoutStopClawbackTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookingScenario;

    private User $admin;

    private User $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->payments = User::factory()->create(['role' => 'payments']);
    }

    /** A booking whose client has ACCEPTED the proof: the payout is releasable and the window is open. */
    private function releasablePayout(): array
    {
        $s = $this->bookingScenario();

        Sanctum::actingAs($s['client']);
        $this->postJson("/api/client/proofs/{$s['proof']->id}/accept")->assertOk();

        $s['payment'] = $s['payment']->fresh();
        $this->assertSame(Payment::STATUS_FREE_PAYMENT, $s['payment']->status);
        $this->assertNotNull($s['payment']->payout_releasable_at, 'The stop window never opened.');

        return $s;
    }

    private function releasedPayout(): array
    {
        $s = $this->releasablePayout();

        Sanctum::actingAs($this->payments);
        $this->postJson("/api/payments/payments/{$s['payment']->id}/payout/release")->assertOk();

        $s['payment'] = $s['payment']->fresh();
        $this->assertSame(Payment::STATUS_RELEASED, $s['payment']->status);

        return $s;
    }

    private function lastEntryFor(string $action): AuditLog
    {
        $entry = AuditLog::where('action', $action)->latest('id')->first();
        $this->assertNotNull($entry, "No audit entry with action '{$action}' was written.");

        return $entry;
    }

    // ── the payout-stop window ───────────────────────────────────────────

    public function test_admin_stops_a_payout_inside_the_window(): void
    {
        $s = $this->releasablePayout();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/payout/stop", ['reason' => 'fraud alert'])
            ->assertOk()
            ->assertJsonPath('status', Payment::STATUS_HELD);

        $payment = $s['payment']->fresh();
        $this->assertSame(Payment::STATUS_HELD, $payment->status);
        $this->assertNotNull($payment->payout_stopped_at);

        // A stop moves NO money: it is the local, pre-gateway half of §8.
        $this->assertSame(0, WalletEntry::count());
    }

    public function test_a_stopped_payout_can_no_longer_be_released(): void
    {
        $s = $this->releasablePayout();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/payout/stop", ['reason' => 'fraud alert'])->assertOk();

        Sanctum::actingAs($this->payments);
        $this->postJson("/api/payments/payments/{$s['payment']->id}/payout/release")->assertStatus(409);

        $this->assertSame(Payment::STATUS_HELD, $s['payment']->fresh()->status);
    }

    public function test_payout_stop_writes_an_audit_row(): void
    {
        $s = $this->releasablePayout();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/payout/stop", ['reason' => 'fraud alert'])->assertOk();

        $entry = $this->lastEntryFor('payout_stop');
        $this->assertSame('payments', $entry->target_type);
        $this->assertSame($s['payment']->id, $entry->target_id);
        $this->assertSame($this->admin->id, $entry->actor_id);
        $this->assertSame(Payment::STATUS_FREE_PAYMENT, $entry->before['status']);
        $this->assertSame(Payment::STATUS_HELD, $entry->after['status']);
        $this->assertStringContainsString('fraud alert', $entry->context);
    }

    public function test_the_window_closes_after_the_configured_hours(): void
    {
        $s = $this->releasablePayout();

        // §8 — the window is `payout_stop_hours` (System Config, default 24) from the
        // moment the payout became releasable.
        $this->travel((int) SystemConfiguration::get('payout_stop_hours', 24) + 1)->hours();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/payout/stop", ['reason' => 'too late'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        $this->assertSame(Payment::STATUS_FREE_PAYMENT, $s['payment']->fresh()->status);
    }

    public function test_the_window_length_comes_from_the_system_configuration(): void
    {
        SystemConfiguration::setMany(['payout_stop_hours' => 1]);

        $s = $this->releasablePayout();

        $this->travel(2)->hours();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/payout/stop", ['reason' => 'too late'])
            ->assertStatus(409);
    }

    public function test_stopping_an_already_released_payout_is_409_and_points_at_the_clawback(): void
    {
        $s = $this->releasedPayout();

        Sanctum::actingAs($this->admin);
        $response = $this->postJson("/api/admin/payments/{$s['payment']->id}/payout/stop", ['reason' => 'too late'])
            ->assertStatus(409);

        $this->assertStringContainsString('clawback', $response->json('message'));
        $this->assertSame(Payment::STATUS_RELEASED, $s['payment']->fresh()->status);
    }

    public function test_stopping_a_payout_that_is_not_releasable_yet_is_409(): void
    {
        $s = $this->bookingScenario(); // payment still `pending`; nobody accepted anything

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/payout/stop", ['reason' => 'nothing to stop'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');
    }

    public function test_a_stop_reason_is_required(): void
    {
        $s = $this->releasablePayout();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/payout/stop", [])->assertStatus(422);
    }

    // ── the clawback ─────────────────────────────────────────────────────

    public function test_clawback_debits_the_provider_without_touching_the_release_entry(): void
    {
        $s = $this->releasedPayout();
        $provider = $s['provider'];

        $release = WalletEntry::where('idempotency_key', "escrow_release:payment:{$s['payment']->id}")->firstOrFail();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/clawback", ['reason' => 'fraudulent display'])
            ->assertOk()
            ->assertJsonPath('wallet_entry.type', 'clawback')
            ->assertJsonPath('wallet_entry.amount', -500)
            ->assertJsonPath('wallet_entry.idempotency_key', "clawback:payment:{$s['payment']->id}");

        // TWO rows, not one edited row: the ledger is append-only (§8), and the fact
        // that the provider WAS paid is exactly what a fraud case is argued from.
        $this->assertSame(2, WalletEntry::where('user_id', $provider->id)->count());
        $this->assertEquals(500.00, (float) $release->fresh()->amount, 'The original release entry was modified.');
        $this->assertSame('escrow_release', $release->fresh()->type);
        $this->assertEquals(0.0, WalletEntry::balanceFor($provider->id));
    }

    public function test_clawback_moves_the_money_authority_off_released(): void
    {
        $s = $this->releasedPayout();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/clawback", ['reason' => 'fraudulent display'])->assertOk();

        // §2/§8 — `payments.status` is the ONLY authority over where the money is.
        // The provider no longer has it, so `released` would be a lie; the platform
        // holds it, frozen, pending the next decision.
        $payment = $s['payment']->fresh();
        $this->assertSame(Payment::STATUS_HELD, $payment->status);
        $this->assertNotNull($payment->clawed_back_at);
    }

    public function test_clawback_writes_an_audit_row(): void
    {
        $s = $this->releasedPayout();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/clawback", ['reason' => 'fraudulent display'])->assertOk();

        $entry = $this->lastEntryFor('clawback');
        $this->assertSame('payments', $entry->target_type);
        $this->assertSame($s['payment']->id, $entry->target_id);
        $this->assertSame($this->admin->id, $entry->actor_id);
        $this->assertSame(Payment::STATUS_RELEASED, $entry->before['status']);
        $this->assertSame(Payment::STATUS_HELD, $entry->after['status']);
        $this->assertStringContainsString('fraudulent display', $entry->context);
    }

    public function test_clawing_back_a_payout_that_was_never_released_is_409(): void
    {
        $s = $this->releasablePayout();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/clawback", ['reason' => 'nothing to claw'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        $this->assertSame(0, WalletEntry::where('type', 'clawback')->count());
    }

    public function test_a_second_clawback_on_the_same_payment_is_409(): void
    {
        $s = $this->releasedPayout();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/clawback", ['reason' => 'fraudulent display'])->assertOk();
        $this->postJson("/api/admin/payments/{$s['payment']->id}/clawback", ['reason' => 'again'])
            ->assertStatus(409);

        $this->assertSame(1, WalletEntry::where('type', 'clawback')->count());
    }

    public function test_a_clawback_reason_is_required(): void
    {
        $s = $this->releasedPayout();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/clawback", [])->assertStatus(422);
    }

    public function test_the_client_can_still_be_refunded_after_a_clawback(): void
    {
        $s = $this->releasedPayout();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/clawback", ['reason' => 'fraudulent display'])->assertOk();

        // The money is back with the platform, so the refund path is open again —
        // which is the whole point of not leaving the payment `released`.
        Sanctum::actingAs($this->payments);
        $this->postJson("/api/payments/payments/{$s['payment']->id}/refund")->assertOk();

        $this->assertSame(Payment::STATUS_REFUNDED, $s['payment']->fresh()->status);
        $this->assertEquals(500.00, WalletEntry::balanceFor($s['client']->id));
        $this->assertEquals(0.0, WalletEntry::balanceFor($s['provider']->id));
    }

    public function test_payments_refund_still_refuses_a_released_payout(): void
    {
        $s = $this->releasedPayout();

        Sanctum::actingAs($this->payments);
        $response = $this->postJson("/api/payments/payments/{$s['payment']->id}/refund")->assertStatus(409);

        $this->assertStringContainsString('clawback', $response->json('message'));
        $this->assertEquals(500.00, WalletEntry::balanceFor($s['provider']->id));
    }

    // ── who may do this ──────────────────────────────────────────────────

    public function test_non_admins_cannot_stop_a_payout_or_claw_it_back(): void
    {
        $s = $this->releasedPayout();

        foreach (['payments', 'support', 'client', 'provider'] as $role) {
            $user = match ($role) {
                'client' => $s['client'],
                'provider' => $s['provider'],
                'payments' => $this->payments,
                default => User::factory()->create(['role' => $role]),
            };

            Sanctum::actingAs($user);
            // EH-14: parameterised → 404, indistinguishable from a payment that is not there.
            $this->postJson("/api/admin/payments/{$s['payment']->id}/payout/stop", ['reason' => 'x'])->assertStatus(404);
            $this->postJson("/api/admin/payments/{$s['payment']->id}/clawback", ['reason' => 'x'])->assertStatus(404);
        }

        $this->assertSame(Payment::STATUS_RELEASED, $s['payment']->fresh()->status);
        $this->assertSame(0, WalletEntry::where('type', 'clawback')->count());
    }

    public function test_the_clawback_power_is_revocable_on_its_own(): void
    {
        $s = $this->releasedPayout();

        // §12 keeps Admin read-only except the moderation actions; `moderation.refund`
        // is the money-reversal half and can be taken away without taking away the
        // listing/account moderation half.
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/permissions/admin/moderation', ['actions' => ['read', 'update']])->assertOk();

        $this->postJson("/api/admin/payments/{$s['payment']->id}/clawback", ['reason' => 'nope'])->assertStatus(404);
        $this->postJson("/api/admin/payments/{$s['payment']->id}/payout/stop", ['reason' => 'still allowed'])
            ->assertStatus(409); // refused by STATE (already released), not by permission
    }

    public function test_the_admin_payout_queue_reports_the_window(): void
    {
        $s = $this->releasablePayout();

        Sanctum::actingAs($this->admin);
        $body = $this->getJson('/api/admin/payouts')->assertOk()->json();

        $this->assertSame(24, $body['payout_stop_hours']);
        $this->assertSame($s['payment']->id, $body['payouts'][0]['id']);
        $this->assertTrue($body['payouts'][0]['can_stop']);
        $this->assertFalse($body['payouts'][0]['can_clawback']);
        $this->assertNotNull($body['payouts'][0]['payout_stop_deadline']);
    }
}
