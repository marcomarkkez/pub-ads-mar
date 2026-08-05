<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Collaborator;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

/**
 * design.json §11 / §12 — UC-23 (Support edits any NON-money object, audited)
 * and UC-31 (the immutable audit log).
 *
 * The two rules these defend:
 *   1. Support is near-admin on CONTENT and has ZERO money authority (§1).
 *      Money OBJECTS have no route; money-determining FIELDS are unreachable.
 *   2. An edit and its audit row are one atomic fact. There is no way to get
 *      one without the other, and no way to alter the row afterwards.
 */
class SupportEditAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->support = User::factory()->create(['role' => 'support', 'name' => 'Support Agent']);
    }

    private function makeSpace(): Space
    {
        return Space::create([
            'user_id' => User::factory()->create(['role' => 'provider'])->id,
            'name' => 'Barda Centro',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
            'price_per_day' => 100.00,
        ]);
    }

    // ── UC-23: the edit works, and it is audited ──────────────────────────────

    public function test_support_edits_a_space_and_the_change_is_audited(): void
    {
        $space = $this->makeSpace();

        Sanctum::actingAs($this->support);

        $this->putJson("/api/support/spaces/{$space->id}", [
            'name' => 'Barda Centro (corregida)',
            'location_text' => 'Av. Constitución 100',
        ])->assertStatus(200)->assertJsonPath('audited', true);

        $this->assertSame('Barda Centro (corregida)', $space->fresh()->name);

        $entry = AuditLog::where('target_type', 'spaces')->where('target_id', $space->id)->sole();

        $this->assertSame('update', $entry->action);
        $this->assertSame($this->support->id, $entry->actor_id);
        $this->assertSame('support', $entry->actor_role);
        $this->assertSame('Barda Centro', $entry->before['name']);
        $this->assertSame('Barda Centro (corregida)', $entry->after['name']);
    }

    public function test_a_no_op_edit_writes_no_audit_entry(): void
    {
        $space = $this->makeSpace();

        Sanctum::actingAs($this->support);

        $this->putJson("/api/support/spaces/{$space->id}", ['name' => 'Barda Centro'])
            ->assertStatus(200)
            ->assertJsonPath('audited', false);

        $this->assertSame(0, AuditLog::count());
    }

    public function test_support_edits_a_provider_collaborator_role(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $collaborator = Collaborator::create([
            'account_id' => $client->account_id,
            'invited_by_user_id' => $client->id,
            'email' => 'colab@pubads.test',
            'role' => 'installator',
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($this->support);

        $this->putJson("/api/support/collaborators/{$collaborator->id}", ['role' => 'manager'])
            ->assertStatus(200);

        $this->assertSame('manager', $collaborator->fresh()->role);
        $this->assertSame(1, AuditLog::where('target_type', 'collaborators')->count());
    }

    // ── §1: Support has no money authority ────────────────────────────────────

    public function test_support_cannot_change_a_space_price(): void
    {
        $space = $this->makeSpace();

        Sanctum::actingAs($this->support);

        $this->putJson("/api/support/spaces/{$space->id}", [
            'name' => 'Renombrada',
            'price_per_day' => 1.00,
        ])->assertStatus(200);

        $this->assertSame('Renombrada', $space->fresh()->name);
        $this->assertSame('100.00', $space->fresh()->price_per_day);

        $entry = AuditLog::where('target_type', 'spaces')->sole();
        $this->assertArrayNotHasKey('price_per_day', $entry->after);
    }

    public function test_support_cannot_change_a_booking_total(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $space = $this->makeSpace();
        $campaign = Campaign::create(['user_id' => $client->id, 'name' => 'C', 'status' => 'active']);
        $adset = $campaign->adsets()->create(['name' => 'A', 'status' => 'active']);
        $ad = $adset->ads()->create([
            'space_id' => $space->id,
            'provider_user_id' => $space->user_id,
            'name' => 'Ad',
            'media_type' => 'image',
            'status' => 'draft',
        ]);

        $booking = Booking::create([
            'client_user_id' => $client->id,
            'space_id' => $space->id,
            'ad_id' => $ad->id,
            'adset_id' => $adset->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'total_price' => 3000,
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($this->support);

        $this->putJson("/api/support/bookings/{$booking->id}", [
            'status' => 'cancelled',
            'total_price' => 1,
        ])->assertStatus(200);

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('3000.00', $booking->fresh()->total_price);
    }

    public function test_support_cannot_change_a_user_role_or_password(): void
    {
        $victim = User::factory()->create(['role' => 'client']);
        $originalHash = $victim->password;

        Sanctum::actingAs($this->support);

        $this->putJson("/api/support/users/{$victim->id}", [
            'name' => 'Nombre corregido',
            'role' => 'admin',
            'password' => 'pwned-by-support',
        ])->assertStatus(200);

        $victim->refresh();
        $this->assertSame('Nombre corregido', $victim->name);
        $this->assertSame('client', $victim->role);
        $this->assertSame($originalHash, $victim->password);
    }

    public function test_money_objects_have_no_support_edit_route(): void
    {
        Sanctum::actingAs($this->support);

        // Support flags, Payments executes (§8) — there is nothing to call here.
        $this->putJson('/api/support/payments/1', ['amount' => 1])->assertStatus(404);
        $this->putJson('/api/support/invoices/1', ['amount' => 1])->assertStatus(404);
    }

    // ── Who may call these at all ─────────────────────────────────────────────

    public function test_a_client_cannot_use_the_support_edit_endpoints(): void
    {
        $space = $this->makeSpace();

        Sanctum::actingAs(User::factory()->create(['role' => 'client']));

        // EH-14: 404 — a 403 would confirm to any client that this listing id exists.
        $this->putJson("/api/support/spaces/{$space->id}", ['name' => 'pwned'])
            ->assertStatus(404);

        $this->assertSame('Barda Centro', $space->fresh()->name);
    }

    public function test_payments_cannot_use_the_support_edit_endpoints(): void
    {
        $space = $this->makeSpace();

        Sanctum::actingAs(User::factory()->create(['role' => 'payments']));

        $this->putJson("/api/support/spaces/{$space->id}", ['name' => 'pwned'])
            ->assertStatus(404);
    }

    // ── UC-31: the log itself ─────────────────────────────────────────────────

    public function test_admin_reads_the_audit_log_and_filters_it(): void
    {
        $space = $this->makeSpace();
        $other = $this->makeSpace();

        Sanctum::actingAs($this->support);
        $this->putJson("/api/support/spaces/{$space->id}", ['name' => 'Uno'])->assertStatus(200);
        $this->putJson("/api/support/spaces/{$other->id}", ['name' => 'Dos'])->assertStatus(200);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/audit')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/admin/audit?target_type=spaces&target_id={$space->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target_id', $space->id)
            ->assertJsonPath('data.0.actor.name', 'Support Agent');
    }

    public function test_support_cannot_read_the_audit_log(): void
    {
        Sanctum::actingAs($this->support);

        // §2 gives Admin alone 👁 read-all on the log; Support only writes to it.
        $this->getJson('/api/admin/audit')->assertStatus(403);
    }

    public function test_an_audit_entry_can_never_be_modified(): void
    {
        $space = $this->makeSpace();

        Sanctum::actingAs($this->support);
        $this->putJson("/api/support/spaces/{$space->id}", ['name' => 'Uno'])->assertStatus(200);

        $entry = AuditLog::sole();

        $this->expectException(RuntimeException::class);
        $entry->update(['after' => ['name' => 'rewritten history']]);
    }

    public function test_an_audit_entry_can_never_be_deleted(): void
    {
        $space = $this->makeSpace();

        Sanctum::actingAs($this->support);
        $this->putJson("/api/support/spaces/{$space->id}", ['name' => 'Uno'])->assertStatus(200);

        $this->expectException(RuntimeException::class);
        AuditLog::sole()->delete();
    }

    public function test_the_edit_and_its_audit_row_are_one_transaction(): void
    {
        $ad = Ad::create([
            'space_id' => $this->makeSpace()->id,
            'provider_user_id' => User::factory()->create(['role' => 'provider'])->id,
            'name' => 'Creative',
            'media_type' => 'image',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($this->support);

        $this->putJson("/api/support/ads/{$ad->id}", ['status' => 'paused'])
            ->assertStatus(200)
            ->assertJsonPath('audited', true);

        $this->assertSame('paused', $ad->fresh()->status);

        $entry = AuditLog::where('target_type', 'ads')->sole();
        $this->assertSame('draft', $entry->before['status']);
        $this->assertSame('paused', $entry->after['status']);
        $this->assertStringContainsString('UC-23', $entry->context);
    }
}
