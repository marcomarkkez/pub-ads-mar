<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Chat;
use App\Models\ChatParticipant;
use App\Models\Payment;
use App\Models\Proof;
use App\Models\RolePermission;
use App\Models\Space;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Models\WalletEntry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * design.json §12 (UC-31) — AU-record-02.
 *
 * §2 closes with "every staff (✏/$) action is 📝-logged". UC-23 (Support
 * edit-any) already wrote to the log; this covers the REST of that sentence:
 * every Payments money action, Admin's account/config/RBAC writes, and the
 * money flags Support raises.
 *
 * The invariant under test is not "a row appears" but "the row and the state
 * change are one fact": same transaction, real before/after, no secrets.
 */
class AuditWritersTest extends TestCase
{
    use RefreshDatabase;

    private User $payments;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->payments = User::factory()->create(['role' => 'payments', 'name' => 'Payments Clerk']);
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Boss']);
    }

    private function makePayment(string $status = 'pending'): Payment
    {
        $client = User::factory()->create(['role' => 'client']);
        $provider = User::factory()->create(['role' => 'provider']);

        $space = Space::create([
            'user_id' => $provider->id,
            'name' => 'Barda Centro',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
            'price_per_day' => 100.00,
        ]);

        $booking = Booking::create([
            'client_user_id' => $client->id,
            'space_id' => $space->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'total_price' => 300.00,
            'status' => 'active',
        ]);

        return Payment::create([
            'booking_id' => $booking->id,
            'amount' => 300.00,
            'status' => $status,
        ]);
    }

    private function lastEntry(): AuditLog
    {
        $entry = AuditLog::latest('id')->first();
        $this->assertNotNull($entry, 'The action wrote no audit entry.');

        return $entry;
    }

    // ── Payments: every $ action (§2 · §8) ────────────────────────────────────

    public function test_payment_approval_is_audited(): void
    {
        $payment = $this->makePayment();

        Sanctum::actingAs($this->payments);
        $this->postJson("/api/payments/payments/{$payment->id}/approve")->assertOk();

        $entry = $this->lastEntry();
        $this->assertSame('approve', $entry->action);
        $this->assertSame('payments', $entry->target_type);
        $this->assertSame($payment->id, $entry->target_id);
        $this->assertSame($this->payments->id, $entry->actor_id);
        $this->assertSame('payments', $entry->actor_role);
        $this->assertSame('pending', $entry->before['status']);
        $this->assertSame('completed', $entry->after['status']);
    }

    public function test_payment_rejection_is_audited(): void
    {
        $payment = $this->makePayment();

        Sanctum::actingAs($this->payments);
        $this->postJson("/api/payments/payments/{$payment->id}/reject")->assertOk();

        $entry = $this->lastEntry();
        $this->assertSame('reject', $entry->action);
        $this->assertSame('failed', $entry->after['status']);
    }

    public function test_refund_is_audited_and_names_the_wallet_entry(): void
    {
        $payment = $this->makePayment('completed');

        Sanctum::actingAs($this->payments);
        $this->postJson("/api/payments/payments/{$payment->id}/refund")->assertOk();

        $wallet = WalletEntry::where('idempotency_key', "refund:payment:{$payment->id}")->firstOrFail();

        $entry = $this->lastEntry();
        $this->assertSame('refund', $entry->action);
        $this->assertSame('refunded', $entry->after['status']);
        $this->assertStringContainsString('#' . $wallet->id, $entry->context);
    }

    public function test_payout_release_is_audited(): void
    {
        $payment = $this->makePayment('completed');
        $space = $payment->booking->space;

        $ad = Ad::create([
            'space_id' => $space->id,
            'provider_user_id' => $space->user_id,
            'name' => 'Creative',
            'media_type' => 'image',
            'status' => 'active',
        ]);

        // B9 gate: the payout only releases once the CLIENT accepted the proof.
        Proof::create([
            'ad_id' => $ad->id,
            'booking_id' => $payment->booking_id,
            'uploaded_by_user_id' => $space->user_id,
            'media_type' => 'image',
            'file_path' => 'proofs/x.jpg',
            'file_name' => 'x.jpg',
            'status' => 'client_accepted',
        ]);

        Sanctum::actingAs($this->payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/release")->assertOk();

        $entry = $this->lastEntry();
        $this->assertSame('release_payout', $entry->action);
        $this->assertSame('released', $entry->after['status']);
    }

    public function test_payout_hold_is_audited(): void
    {
        $payment = $this->makePayment('completed');

        Sanctum::actingAs($this->payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/hold")->assertOk();

        $entry = $this->lastEntry();
        $this->assertSame('hold_payout', $entry->action);
        $this->assertSame('held', $entry->after['status']);
    }

    /**
     * holdPayout() refuses to drag a settled payment back into escrow. Nothing
     * changed, so nothing may be logged — an audit trail that records non-events
     * is noise, and worse, it reads as if money moved.
     */
    public function test_a_hold_that_changes_nothing_writes_no_entry(): void
    {
        $payment = $this->makePayment('released');

        Sanctum::actingAs($this->payments);
        $this->postJson("/api/payments/payments/{$payment->id}/payout/hold")->assertOk();

        $this->assertSame(0, AuditLog::count());
        $this->assertSame('released', $payment->fresh()->status);
    }

    // ── Admin: accounts (§2 ✏📝 on User) ──────────────────────────────────────

    public function test_admin_creating_an_account_is_audited_without_the_password(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/users', [
            'name' => 'New Provider',
            'email' => 'new@pubads.test',
            'password' => 'supersecret123',
            'role' => 'provider',
        ])->assertCreated();

        $entry = $this->lastEntry();
        $this->assertSame('create', $entry->action);
        $this->assertSame('users', $entry->target_type);
        $this->assertSame('new@pubads.test', $entry->after['email']);
        $this->assertSame('[redacted]', $entry->after['password']);
        $this->assertStringNotContainsString('supersecret123', json_encode($entry->after));
    }

    public function test_admin_editing_a_role_is_audited(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/users/{$user->id}", ['role' => 'provider'])->assertOk();

        $entry = $this->lastEntry();
        $this->assertSame('update', $entry->action);
        $this->assertSame('client', $entry->before['role']);
        $this->assertSame('provider', $entry->after['role']);
    }

    public function test_admin_deleting_an_account_is_audited_before_the_row_disappears(): void
    {
        $user = User::factory()->create(['role' => 'client', 'email' => 'gone@pubads.test']);

        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/admin/users/{$user->id}")->assertOk();

        $entry = $this->lastEntry();
        $this->assertSame('delete', $entry->action);
        $this->assertSame($user->id, $entry->target_id);
        $this->assertSame('gone@pubads.test', $entry->before['email']);
        $this->assertNull($entry->after);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_a_no_op_account_edit_writes_no_entry(): void
    {
        $user = User::factory()->create(['role' => 'client', 'name' => 'Same Name']);

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/users/{$user->id}", ['name' => 'Same Name'])->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    // ── Admin: system config + RBAC ───────────────────────────────────────────

    public function test_config_change_is_audited_per_key(): void
    {
        SystemConfiguration::create(['key' => 'proof_deadline_days', 'value' => '3']);

        Sanctum::actingAs($this->admin);
        $this->putJson('/api/admin/configurations', [
            'configs' => [
                ['key' => 'proof_deadline_days', 'value' => '7'],
                ['key' => 'auto_approve_threshold', 'value' => '500'],
            ],
            'apply_scope' => 'new_only',
        ])->assertOk();

        $this->assertSame(2, AuditLog::where('target_type', 'system_configurations')->count());

        $deadline = AuditLog::where('target_type', 'system_configurations')
            ->get()
            ->first(fn (AuditLog $e) => array_key_exists('proof_deadline_days', $e->before ?? []));

        $this->assertNotNull($deadline);
        $this->assertSame('3', $deadline->before['proof_deadline_days']);
        $this->assertSame('7', $deadline->after['proof_deadline_days']);
    }

    public function test_a_config_write_that_changes_no_value_writes_no_entry(): void
    {
        SystemConfiguration::create(['key' => 'proof_deadline_days', 'value' => '3']);

        Sanctum::actingAs($this->admin);
        $this->putJson('/api/admin/configurations', [
            'configs' => [['key' => 'proof_deadline_days', 'value' => '3']],
            'apply_scope' => 'new_only',
        ])->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_rbac_rewrite_is_audited_against_the_permission_set(): void
    {
        Sanctum::actingAs($this->admin);

        $before = RolePermission::where('role', 'support')->where('resource', 'spaces')->pluck('action')->all();
        $this->assertContains('read', $before);

        $this->putJson('/api/admin/permissions/support', [
            'permissions' => ['spaces' => ['read']],
        ])->assertOk();

        $entry = $this->lastEntry();
        $this->assertSame('role_permissions', $entry->target_type);
        // The target is a SET, not a row: an invented id would be unjoinable.
        $this->assertNull($entry->target_id);
        $this->assertSame(['read'], $entry->after['spaces']);
        $this->assertArrayHasKey('users', $entry->before, 'support had users permissions before the rewrite');
        $this->assertArrayNotHasKey('users', $entry->after, 'the rewrite dropped every resource but spaces');
    }

    public function test_single_resource_rbac_patch_is_audited(): void
    {
        Sanctum::actingAs($this->admin);

        $this->patchJson('/api/admin/permissions/support/spaces', [
            'actions' => ['read'],
        ])->assertOk();

        $entry = $this->lastEntry();
        $this->assertSame('role_permissions', $entry->target_type);
        $this->assertSame(['read'], $entry->after['spaces']);
        $this->assertStringContainsString('support.spaces', $entry->context);
    }

    // ── Support: the money flags it raises (§2 flag$ · UC-22) ─────────────────

    public function test_raising_a_money_flag_is_audited_and_changing_it_records_the_previous(): void
    {
        $support = User::factory()->create(['role' => 'support', 'name' => 'Support Agent']);
        $client = User::factory()->create(['role' => 'client']);

        $chat = Chat::create(['opened_by_user_id' => $client->id, 'client_user_id' => $client->id]);
        $chat->participants()->create(['user_id' => $client->id, 'side' => ChatParticipant::SIDE_CLIENT]);
        $chat->participants()->create(['user_id' => $support->id, 'side' => ChatParticipant::SIDE_SUPPORT]);

        Sanctum::actingAs($support);

        $this->postJson("/api/chats/{$chat->id}/flags", ['type' => 'payment_held', 'reason' => 'mismatch'])
            ->assertCreated();

        $raised = $this->lastEntry();
        $this->assertSame('flag_raise', $raised->action);
        $this->assertSame('chats', $raised->target_type);
        $this->assertSame($chat->id, $raised->target_id);
        $this->assertNull($raised->before);
        $this->assertSame('payment_held', $raised->after['type']);

        $this->postJson("/api/chats/{$chat->id}/flags", ['type' => 'payment_held', 'reason' => 'resolved'])
            ->assertCreated();

        $changed = $this->lastEntry();
        $this->assertSame('flag_change', $changed->action);
        $this->assertSame('mismatch', $changed->before['reason']);
        $this->assertSame('resolved', $changed->after['reason']);
    }

    // ── The log itself stays append-only under the new writers ────────────────

    public function test_entries_written_by_the_new_writers_are_still_immutable(): void
    {
        $payment = $this->makePayment();

        Sanctum::actingAs($this->payments);
        $this->postJson("/api/payments/payments/{$payment->id}/approve")->assertOk();

        $entry = $this->lastEntry();

        $this->expectException(\RuntimeException::class);
        $entry->update(['action' => 'tampered']);
    }
}
