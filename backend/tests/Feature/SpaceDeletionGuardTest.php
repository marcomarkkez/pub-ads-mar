<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AD-delguard-08 · design.json §12 · UC-37 — nothing in use is ever destroyed.
 *
 * Owner 2026-08-03: "No deben poder borrarse ads que tengan aún clientes usándolos, se
 * pueden despublicar y programar para borrado."
 *
 * The load-bearing test here is `test_a_listing_with_live_bookings_cannot_be_scheduled`:
 * before this, DELETE was `$space->delete()` against a CASCADE foreign key, so a provider
 * tidying their catalog destroyed their clients' bookings and payments in one statement.
 */
class SpaceDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->provider = User::factory()->create(['role' => 'provider']);
    }

    private function space(): Space
    {
        return Space::create([
            'user_id' => $this->provider->id,
            'name' => 'Barda Centro',
            'latitude' => 25.6597,
            'longitude' => -100.4023,
            'price_per_day' => 100.00,
        ]);
    }

    private function bookingOn(Space $space, string $status): Booking
    {
        return Booking::create([
            'client_user_id' => User::factory()->create(['role' => 'client'])->id,
            'space_id' => $space->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'total_price' => 300.00,
            'status' => $status,
        ]);
    }

    public function test_a_listing_with_live_bookings_cannot_be_scheduled(): void
    {
        $space = $this->space();
        $this->bookingOn($space, 'confirmed');

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'OBJECT_IN_USE')
            ->assertJsonPath('blockers.0.kind', 'live_bookings');

        // Nothing moved: not the listing, not the client's booking.
        $space->refresh();
        $this->assertNull($space->delete_scheduled_at);
        $this->assertSame(Space::PUBLICATION_PUBLISHED, $space->publication_status);
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_unsettled_money_blocks_the_schedule_even_when_the_booking_is_over(): void
    {
        $space = $this->space();
        $booking = $this->bookingOn($space, 'completed'); // history — does not block on its own
        Payment::create(['booking_id' => $booking->id, 'amount' => 300.00, 'status' => 'held']);

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")
            ->assertStatus(409)
            ->assertJsonPath('blockers.0.kind', 'unsettled_payments');
    }

    public function test_a_free_listing_is_unpublished_and_programmed_not_destroyed(): void
    {
        $space = $this->space();
        $this->bookingOn($space, 'completed');

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();

        $space->refresh();
        $this->assertSame(Space::PUBLICATION_UNPUBLISHED, $space->publication_status);
        $this->assertNotNull($space->delete_scheduled_at);
        // The row is STILL THERE. That is the whole feature.
        $this->assertDatabaseHas('spaces', ['id' => $space->id]);

        $entry = AuditLog::where('action', 'schedule_deletion')->sole();
        $this->assertSame($this->provider->id, $entry->actor_id);
    }

    /** An unpublished listing is out of the catalog — the fourth level of scopeBookable. */
    public function test_an_unpublished_listing_leaves_the_catalog(): void
    {
        $space = $this->space();
        $this->assertTrue(Space::bookable()->where('id', $space->id)->exists());

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();

        $this->assertFalse(Space::bookable()->where('id', $space->id)->exists());
    }

    /**
     * The subtle one. `is_active` is the provider's lever and `unpublished` is a
     * consequence; if un-pausing could clear it, the programmed deletion would evaporate
     * the first time someone toggled a switch, silently.
     */
    public function test_the_providers_own_lever_cannot_un_program_a_deletion(): void
    {
        $space = $this->space();

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();

        $this->putJson("/api/provider/spaces/{$space->id}", ['is_active' => false])->assertOk();
        $this->putJson("/api/provider/spaces/{$space->id}", ['is_active' => true])->assertOk();

        $space->refresh();
        $this->assertSame(Space::PUBLICATION_UNPUBLISHED, $space->publication_status);
        $this->assertNotNull($space->delete_scheduled_at);
        $this->assertFalse(Space::bookable()->where('id', $space->id)->exists());
    }

    public function test_cancelling_returns_the_listing_to_the_providers_own_lever(): void
    {
        $space = $this->space();

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();
        $this->putJson("/api/provider/spaces/{$space->id}", ['is_active' => false])->assertOk();

        $this->postJson("/api/provider/spaces/{$space->id}/cancel-deletion")->assertOk();

        // Paused before scheduling, so it comes back PAUSED — not published.
        $space->refresh();
        $this->assertNull($space->delete_scheduled_at);
        $this->assertSame(Space::PUBLICATION_PAUSED, $space->publication_status);
        $this->assertSame(1, AuditLog::where('action', 'cancel_deletion')->count());
    }

    public function test_another_providers_listing_is_404_not_403(): void
    {
        $space = $this->space();
        $stranger = User::factory()->create(['role' => 'provider']);

        Sanctum::actingAs($stranger);
        // §21 rule 2 — a 403 would confirm the row exists.
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertStatus(404);
        $this->postJson("/api/provider/spaces/{$space->id}/cancel-deletion")->assertStatus(404);
    }

    public function test_the_purge_is_a_dry_run_until_it_is_confirmed(): void
    {
        $space = $this->space();
        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();
        // forceFill on purpose: `delete_scheduled_at` is NOT fillable, so that no client
        // payload can move a purge date. The test has to bypass it the same way the
        // controller does — by naming the attribute.
        $space->forceFill(['delete_scheduled_at' => now()->subDay()])->save();

        $this->artisan('spaces:purge')->assertSuccessful();
        $this->assertDatabaseHas('spaces', ['id' => $space->id]);

        $this->artisan('spaces:purge', ['--confirm' => true])->assertSuccessful();
        $this->assertDatabaseMissing('spaces', ['id' => $space->id]);

        $this->assertSame(1, AuditLog::where('action', 'purge')->count());
    }

    /**
     * Days pass between programming and purging. A listing booked again in the meantime
     * must survive its own due date — the check that ran a month ago described a
     * different world.
     */
    public function test_the_purge_skips_a_listing_that_got_booked_while_it_waited(): void
    {
        $space = $this->space();
        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();
        $space->forceFill(['delete_scheduled_at' => now()->subDay()])->save();

        $this->bookingOn($space, 'confirmed');

        $this->artisan('spaces:purge', ['--confirm' => true])->assertSuccessful();

        $this->assertDatabaseHas('spaces', ['id' => $space->id]);
        $this->assertSame(0, AuditLog::where('action', 'purge')->count());
    }

    public function test_a_listing_not_yet_due_is_left_alone(): void
    {
        $space = $this->space();
        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();

        $this->artisan('spaces:purge', ['--confirm' => true])->assertSuccessful();
        $this->assertDatabaseHas('spaces', ['id' => $space->id]);
    }

    /** The engine backs the guard: even raw SQL cannot take a booked listing away. */
    public function test_the_database_itself_refuses_to_drop_a_booked_listing(): void
    {
        $space = $this->space();
        $this->bookingOn($space, 'confirmed');

        $this->expectException(\Illuminate\Database\QueryException::class);
        $space->delete();
    }
}
