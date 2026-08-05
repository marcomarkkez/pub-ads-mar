<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Space;
use App\Models\User;
use App\Models\WalletEntry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * UC-29 · design.json §12 — admin moderation, and the THREE levels staying apart.
 *
 * The load-bearing test in this file is
 * `test_provider_cannot_self_reverse_an_admin_takedown`. Everything else here can
 * pass while the feature is a lie: if the provider's own publish button clears a
 * takedown, "the provider CANNOT self-reverse" is not implemented, whatever the
 * admin routes return.
 */
class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $provider;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->provider = User::factory()->create(['role' => 'provider']);
        $this->space = Space::create([
            'user_id' => $this->provider->id,
            'name' => 'Barda Centro',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
            'price_per_day' => 100.00,
            'is_active' => true,
        ]);
    }

    private function lastEntryFor(string $action): AuditLog
    {
        $entry = AuditLog::where('action', $action)->latest('id')->first();
        $this->assertNotNull($entry, "No audit entry with action '{$action}' was written.");

        return $entry;
    }

    // ── takedown / restore ───────────────────────────────────────────────

    public function test_admin_takes_a_listing_down_and_it_leaves_the_catalog(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($client);
        $this->getJson('/api/client/spaces/search')->assertOk()->assertJsonPath('total', 1);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'Illegal content'])
            ->assertOk()
            ->assertJsonPath('takedown_reason', 'Illegal content');

        $this->assertNotNull($this->space->fresh()->taken_down_at);

        Sanctum::actingAs($client);
        $this->getJson('/api/client/spaces/search')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_takedown_writes_an_audit_row(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'Illegal content'])->assertOk();

        $entry = $this->lastEntryFor('takedown');
        $this->assertSame('spaces', $entry->target_type);
        $this->assertSame($this->space->id, $entry->target_id);
        $this->assertSame($this->admin->id, $entry->actor_id);
        $this->assertNull($entry->before['taken_down_at']);
        $this->assertNotNull($entry->after['taken_down_at']);
        $this->assertStringContainsString('Illegal content', $entry->context);
    }

    public function test_taking_down_an_already_taken_down_listing_is_409(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'first'])->assertOk();

        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'again'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        // The reason of record is the FIRST one; the refused call changed nothing.
        $this->assertSame('first', $this->space->fresh()->takedown_reason);
    }

    public function test_restoring_a_listing_that_is_not_taken_down_is_409(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/restore")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');
    }

    public function test_restore_lifts_the_takedown_and_is_audited(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'checking'])->assertOk();
        $this->postJson("/api/admin/spaces/{$this->space->id}/restore")->assertOk();

        $this->assertNull($this->space->fresh()->taken_down_at);

        $entry = $this->lastEntryFor('restore');
        $this->assertNotNull($entry->before['taken_down_at']);
        $this->assertNull($entry->after['taken_down_at']);
    }

    public function test_a_non_admin_cannot_moderate(): void
    {
        foreach (['provider', 'client', 'support', 'payments'] as $role) {
            $user = $role === 'provider' ? $this->provider : User::factory()->create(['role' => $role]);
            Sanctum::actingAs($user);

            // EH-14: these routes carry a parameter, so a refusal must look exactly like a
            // missing row. A 403 would tell a client that space #7 and provider #3 exist.
            $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'nope'])->assertStatus(404);
            $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'nope'])->assertStatus(404);
        }

        $this->assertNull($this->space->fresh()->taken_down_at);
        $this->assertNull($this->provider->fresh()->frozen_at);
    }

    // ── THE load-bearing one: level 1 cannot undo level 2 ────────────────

    public function test_provider_cannot_self_reverse_an_admin_takedown(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'moderation'])->assertOk();

        // The provider's OWN publish/unpublish route — the level-1 lever.
        Sanctum::actingAs($this->provider);
        $this->putJson("/api/provider/spaces/{$this->space->id}", ['is_active' => true])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        // Not merely refused: the moderation state is untouched…
        $this->assertNotNull($this->space->fresh()->taken_down_at);

        // …and the listing is still out of the catalog, which is what a takedown means.
        Sanctum::actingAs(User::factory()->create(['role' => 'client']));
        $this->getJson('/api/client/spaces/search')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_provider_may_still_pause_their_own_listing_under_a_takedown(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'moderation'])->assertOk();

        // Level 1 remains the provider's: they can go further off, never back on.
        Sanctum::actingAs($this->provider);
        $this->putJson("/api/provider/spaces/{$this->space->id}", ['is_active' => false])->assertOk();

        $this->assertFalse((bool) $this->space->fresh()->is_active);
        $this->assertNotNull($this->space->fresh()->taken_down_at);
    }

    public function test_support_cannot_lift_a_takedown_either(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'moderation'])->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'support']));
        $this->putJson("/api/support/spaces/{$this->space->id}", ['is_active' => true])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        $this->assertNotNull($this->space->fresh()->taken_down_at);
    }

    public function test_restore_does_not_republish_a_listing_the_provider_paused(): void
    {
        Sanctum::actingAs($this->provider);
        $this->putJson("/api/provider/spaces/{$this->space->id}", ['is_active' => false])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'moderation'])->assertOk();
        $this->postJson("/api/admin/spaces/{$this->space->id}/restore")->assertOk();

        // Level 2 lifted, level 1 still the provider's own: still hidden.
        $this->assertNull($this->space->fresh()->taken_down_at);
        $this->assertFalse((bool) $this->space->fresh()->is_active);

        Sanctum::actingAs(User::factory()->create(['role' => 'client']));
        $this->getJson('/api/client/spaces/search')->assertOk()->assertJsonPath('total', 0);
    }

    // ── freeze / unfreeze ────────────────────────────────────────────────

    private function upcomingBooking(string $status = 'confirmed', string $paymentStatus = 'completed'): Booking
    {
        $client = User::factory()->create(['role' => 'client']);

        $booking = Booking::create([
            'client_user_id' => $client->id,
            'space_id' => $this->space->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'total_price' => 500.00,
            'status' => $status,
        ]);

        $booking->payment()->create(['amount' => 500.00, 'status' => $paymentStatus]);

        return $booking;
    }

    public function test_freeze_cancels_upcoming_bookings_and_refunds_them(): void
    {
        $booking = $this->upcomingBooking();
        $client = $booking->client;

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'fraud review'])
            ->assertOk()
            ->assertJsonPath('cancelled_bookings.0', $booking->id)
            ->assertJsonPath('refunds.0.amount', 500);

        $this->assertNotNull($this->provider->fresh()->frozen_at);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('refunded', $booking->payment->fresh()->status);

        // The SAME money path as Payments' own refund: same ledger type, same key.
        $entry = WalletEntry::where('idempotency_key', 'refund:payment:' . $booking->payment->id)->firstOrFail();
        $this->assertSame($client->id, $entry->user_id);
        $this->assertSame('refund', $entry->type);
        $this->assertEquals(500.00, (float) $entry->amount);

        Sanctum::actingAs($client);
        $this->getJson('/api/client/wallet')->assertOk()->assertJsonPath('balance', 500);
    }

    public function test_freeze_does_not_cancel_a_running_booking(): void
    {
        $running = $this->upcomingBooking('active');
        $running->update(['start_date' => now()->subDay()->toDateString()]);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'fraud review'])
            ->assertOk()
            ->assertJsonPath('cancelled_bookings', []);

        $this->assertSame('active', $running->fresh()->status);
        $this->assertSame('completed', $running->payment->fresh()->status);
    }

    public function test_freeze_does_not_refund_an_already_released_payout(): void
    {
        $booking = $this->upcomingBooking('confirmed', 'released');

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'fraud review'])
            ->assertOk()
            ->assertJsonPath('refunds', [])
            ->assertJsonPath('not_refunded.0.payment_id', $booking->payment->id);

        // Reversing a released payout is a CLAWBACK (UC-32), never an automatic refund.
        $this->assertSame('released', $booking->payment->fresh()->status);
        $this->assertSame(0, WalletEntry::count());
    }

    public function test_freeze_pauses_new_bookings_and_hides_the_listing(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'fraud review'])->assertOk();

        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client);

        $this->getJson('/api/client/spaces/search')->assertOk()->assertJsonPath('total', 0);

        $this->postJson('/api/client/bookings', [
            'space_id' => $this->space->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ])->assertStatus(409)->assertJsonPath('error_code', 'CONFLICTING_STATE');
    }

    public function test_freeze_revokes_tokens_and_blocks_a_new_login(): void
    {
        $this->provider->forceFill(['password' => 'secret-pass'])->save();
        $this->provider->createToken('auth-token');
        $this->assertSame(1, $this->provider->tokens()->count());

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'fraud review'])->assertOk();

        $this->assertSame(0, $this->provider->fresh()->tokens()->count());

        $this->postJson('/api/login', ['email' => $this->provider->email, 'password' => 'secret-pass'])
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'AUTH_ACCOUNT_FROZEN');
    }

    public function test_freeze_writes_an_audit_row(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'fraud review'])->assertOk();

        $entry = $this->lastEntryFor('freeze');
        $this->assertSame('users', $entry->target_type);
        $this->assertSame($this->provider->id, $entry->target_id);
        $this->assertNull($entry->before['frozen_at']);
        $this->assertNotNull($entry->after['frozen_at']);
        $this->assertStringContainsString('fraud review', $entry->context);
    }

    public function test_freezing_an_already_frozen_account_is_409(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'first'])->assertOk();

        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'again'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        $this->assertSame('first', $this->provider->fresh()->freeze_reason);
    }

    public function test_unfreezing_an_account_that_is_not_frozen_is_409(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/unfreeze")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');
    }

    public function test_unfreeze_restores_the_account_and_is_audited(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'fraud review'])->assertOk();
        $this->postJson("/api/admin/providers/{$this->provider->id}/unfreeze")->assertOk();

        $this->assertNull($this->provider->fresh()->frozen_at);

        $entry = $this->lastEntryFor('unfreeze');
        $this->assertNotNull($entry->before['frozen_at']);
        $this->assertNull($entry->after['frozen_at']);

        // The listing is bookable again — the freeze hid it, the unfreeze gives it back.
        Sanctum::actingAs(User::factory()->create(['role' => 'client']));
        $this->getJson('/api/client/spaces/search')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_freezing_a_non_provider_is_404(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$client->id}/freeze", ['reason' => 'wrong target'])
            ->assertStatus(404);

        $this->assertNull($client->fresh()->frozen_at);
    }

    public function test_a_freeze_reason_is_required(): void
    {
        // 422, not 409: THIS one is a malformed payload (owner 2026-08-03).
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", [])->assertStatus(422);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", [])->assertStatus(422);
    }

    public function test_the_moderation_console_lists_state_without_changing_it(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'moderation'])->assertOk();

        $this->getJson('/api/admin/moderation')
            ->assertOk()
            ->assertJsonPath('spaces.0.id', $this->space->id)
            ->assertJsonPath('spaces.0.bookable', false)
            ->assertJsonPath('providers.0.id', $this->provider->id)
            ->assertJsonPath('providers.0.frozen_at', null);
    }

    public function test_admin_still_has_no_chat_write_path(): void
    {
        // §10/§16 R1 — moderation gave Admin writes on listings and accounts, and
        // deliberately none in a chat. Admin holds chats.read only.
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/chats', ['target' => 'support', 'message' => 'hello'])->assertStatus(403);
    }

    /** A frozen provider whose payment was already refunded is not credited twice. */
    public function test_freeze_is_idempotent_against_an_existing_refund(): void
    {
        $booking = $this->upcomingBooking();
        $payments = User::factory()->create(['role' => 'payments']);

        Sanctum::actingAs($payments);
        $this->postJson("/api/payments/payments/{$booking->payment->id}/refund")->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/providers/{$this->provider->id}/freeze", ['reason' => 'fraud review'])->assertOk();

        $this->assertSame(1, WalletEntry::where('ref_id', $booking->payment->id)->count());
        $this->assertEquals(500.00, WalletEntry::sum('amount'));
    }

    public function test_payment_status_stays_the_only_money_authority(): void
    {
        // A takedown/freeze annotates the listing and the account; it never rewrites
        // a payment. Money moves only through the refund path, and only for the
        // upcoming bookings the freeze cancels.
        $booking = $this->upcomingBooking('confirmed', 'pending');

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/spaces/{$this->space->id}/takedown", ['reason' => 'moderation'])->assertOk();

        $this->assertSame('pending', $booking->payment->fresh()->status);
        $this->assertSame(0, Payment::where('status', 'refunded')->count());
    }
}
