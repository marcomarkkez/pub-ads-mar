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
 * The load-bearing fact here is that DELETE never destroys: before this, DELETE was
 * `$space->delete()` against a CASCADE foreign key, so a provider tidying their catalog
 * destroyed their clients' bookings and payments in one statement.
 *
 * ── WHY THE EXPECTATIONS IN THIS FILE CHANGED [owner 2026-09-05] ────────────────────
 * Two tests here used to PIN a 409 OBJECT_IN_USE: live bookings, or unsettled payments,
 * made the schedule itself impossible. That was backwards, and the owner said so:
 *
 *   "Lo de schedule for deletion se ve raro, creo que más bien debería de hacerse el
 *    schedule, dejando en claro que no se puede borrar inmediatamente porque hay anuncios
 *    corriendo, pero que sí se puede despublicar. […] básicamente agendarlo solo sirve
 *    cuando hay anuncios o pagos pendientes, pero al mismo tiempo que se debe agendar se
 *    debe pausar la publicación, es decir no se puede eliminar del todo."
 *
 * A grace period exists precisely FOR the listing clients are still on. Refusing there
 * meant the schedule only ever worked on listings that needed no schedule, and the
 * provider with a busy listing had no move at all. So those two tests now assert the
 * opposite — the schedule SUCCEEDS, the listing is unpublished in the same transaction,
 * and the old `blockers[]` ride along on the 200 as `purge_blockers`, information rather
 * than a refusal.
 *
 * Nothing was relaxed about destruction. `spaces:purge` re-checks the same blockers on the
 * due date and skips anything still held — see the purge tests at the bottom, which are
 * now the ONLY thing standing between a busy listing and deletion, and are unchanged.
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

    /**
     * The case the whole feature exists for. Was `…_cannot_be_scheduled` and asserted a 409
     * until [owner 2026-09-05] — see the class docblock.
     */
    public function test_a_listing_with_live_bookings_is_scheduled_and_unpublished_not_refused(): void
    {
        $space = $this->space();
        $this->bookingOn($space, 'confirmed');

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")
            ->assertOk()
            // The reasons still travel, on the 200 and under a key that cannot be mistaken
            // for the old refusal body.
            ->assertJsonPath('purge_blockers.0.kind', 'live_bookings')
            ->assertJsonPath('purge_blockers.0.count', 1)
            ->assertJsonMissingPath('error_code');

        $space->refresh();
        $this->assertNotNull($space->delete_scheduled_at);
        // The unpublish is not a separate step the caller can skip: same request, same
        // transaction. Without it the listing keeps taking bookings all through its own
        // wind-down and the purge date can never arrive clear.
        $this->assertSame(Space::PUBLICATION_UNPUBLISHED, $space->publication_status);

        // The client's booking is NOT cancelled. It runs to completion — that is what the
        // grace period is for.
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', ['space_id' => $space->id, 'status' => 'confirmed']);
    }

    public function test_unsettled_money_is_reported_on_the_schedule_even_when_the_booking_is_over(): void
    {
        $space = $this->space();
        $booking = $this->bookingOn($space, 'completed'); // history — no live booking here
        Payment::create(['booking_id' => $booking->id, 'amount' => 300.00, 'status' => 'held']);

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")
            ->assertOk()
            ->assertJsonPath('purge_blockers.0.kind', 'unsettled_payments');

        // Money still moving is a reason the ROW cannot go, not a reason the provider
        // cannot ask for it to go.
        $space->refresh();
        $this->assertNotNull($space->delete_scheduled_at);
        $this->assertSame(Space::PUBLICATION_UNPUBLISHED, $space->publication_status);
    }

    /**
     * The promise the unpublish makes. Scheduling only ever ends is if the set of clients
     * holding the listing shrinks, so a booking landing on it afterwards would make the
     * wind-down unbounded — and the space id is public enough (old tabs, bookmarks, chat
     * attachments) that leaving it out of search is not the same as closing it.
     */
    public function test_a_scheduled_listing_stops_taking_new_bookings(): void
    {
        $space = $this->space();
        $this->bookingOn($space, 'confirmed');

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();

        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client);
        $this->postJson('/api/client/bookings', [
            'space_id' => $space->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');

        // Only the one that was already there.
        $this->assertDatabaseCount('bookings', 1);
    }

    /**
     * The one refusal left on this endpoint. A second DELETE would push the purge date a
     * further grace period out, so a provider double-clicking would silently postpone their
     * own deletion and never see why the date kept moving.
     */
    public function test_scheduling_twice_is_refused_and_does_not_move_the_date(): void
    {
        $space = $this->space();

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();
        $due = $space->fresh()->delete_scheduled_at;

        $this->travel(2)->days();

        $this->deleteJson("/api/provider/spaces/{$space->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'ALREADY_EXISTS');

        $this->assertTrue($due->equalTo($space->fresh()->delete_scheduled_at));
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

    /**
     * The other half of that lever, and the answer to "does cancelling re-publish?": YES,
     * when the listing was published before — and only then.
     *
     * Scheduling never writes `is_active`, so the provider's own lever still holds the
     * pre-schedule truth and cancelling restores exactly it. Anything else would be a
     * choice nobody made: unconditionally publishing would put a listing the provider had
     * deliberately paused back in front of clients, and unconditionally leaving it
     * unpublished would mean an undo that does not undo — the listing would sit out of the
     * catalog with no deletion to explain it, and `is_active` toggling deliberately cannot
     * clear `unpublished`, so there would be no way back at all.
     *
     * This matters more since [owner 2026-09-05] than it did before: scheduling now
     * unpublishes listings that were live and busy, so cancelling is the path back into the
     * catalog for a listing whose clients never knew it had left.
     */
    public function test_cancelling_republishes_a_listing_that_was_live_when_it_was_scheduled(): void
    {
        $space = $this->space();
        $this->bookingOn($space, 'confirmed');

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();
        $this->assertFalse(Space::bookable()->where('id', $space->id)->exists());

        $this->postJson("/api/provider/spaces/{$space->id}/cancel-deletion")->assertOk();

        $space->refresh();
        $this->assertNull($space->delete_scheduled_at);
        $this->assertSame(Space::PUBLICATION_PUBLISHED, $space->publication_status);
        $this->assertTrue(Space::bookable()->where('id', $space->id)->exists());
    }

    /** Nothing to cancel is a STATE conflict, not an object in use — different code. */
    public function test_cancelling_a_listing_with_no_programmed_deletion_is_a_state_conflict(): void
    {
        $space = $this->space();

        Sanctum::actingAs($this->provider);
        $this->postJson("/api/provider/spaces/{$space->id}/cancel-deletion")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICTING_STATE');
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

    /**
     * The guardrail, standing alone. Since [owner 2026-09-05] the endpoint schedules a busy
     * listing instead of refusing it, so THIS is the only check between live bookings and a
     * destroyed row — and it has to hold on the ordinary path, not just on the race where a
     * booking arrived after the schedule.
     */
    public function test_the_purge_refuses_a_listing_that_was_busy_when_it_was_scheduled(): void
    {
        $space = $this->space();
        $this->bookingOn($space, 'confirmed');

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();
        $space->forceFill(['delete_scheduled_at' => now()->subDay()])->save();

        $this->artisan('spaces:purge', ['--confirm' => true])
            ->expectsOutputToContain('1 booking(s) are still live on this listing.')
            ->assertSuccessful();

        $this->assertDatabaseHas('spaces', ['id' => $space->id]);
        $this->assertSame(0, AuditLog::where('action', 'purge')->count());

        // The client's booking is untouched by the attempt, and the listing stays out of
        // the catalog while it waits — it is not quietly re-published by being skipped.
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame(Space::PUBLICATION_UNPUBLISHED, $space->fresh()->publication_status);
        $this->assertFalse(Space::bookable()->where('id', $space->id)->exists());

        // Run it again: a skip is not a one-off, it repeats for as long as the reason does.
        $this->artisan('spaces:purge', ['--confirm' => true])->assertSuccessful();
        $this->assertDatabaseHas('spaces', ['id' => $space->id]);
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

    // ── booking HISTORY: the purge date that never comes [2026-09-05] ───────────────────
    //
    // `bookings.space_id` is ON DELETE RESTRICT for EVERY status, but deletionBlockers()
    // only counted LIVE bookings. So a listing that had ever been booked once cleared every
    // application check the day its bookings completed, and then threw SQLSTATE[23503] out
    // of $space->delete() on its due date — a refusal from the engine that no screen and no
    // blocker list had ever mentioned. The FK is right (invoices, payments and proofs are
    // argued from those bookings); what was missing was SAYING so, ahead of the date.

    public function test_finished_bookings_are_reported_as_a_permanent_blocker_when_scheduling(): void
    {
        $space = $this->space();
        $this->bookingOn($space, 'completed');

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")
            ->assertOk()
            ->assertJsonPath('purge_blockers.0.kind', 'booking_history')
            ->assertJsonPath('purge_blockers.0.count', 1)
            // The flag, not the wording, is what the screen branches on: this listing is
            // unpublished for good and must not be promised a destruction date.
            ->assertJsonPath('purge_blockers.0.permanent', true);
    }

    /** A live booking and a finished one are two different sentences, and both get said. */
    public function test_a_live_booking_and_a_finished_one_are_reported_apart(): void
    {
        $space = $this->space();
        $this->bookingOn($space, 'confirmed');
        $this->bookingOn($space, 'completed');

        $kinds = array_column($space->deletionBlockers(), 'kind');
        $this->assertSame(['live_bookings', 'booking_history'], $kinds);

        // Neither count absorbed the other: widening `live_bookings` to 2 would have told
        // the provider two clients are waiting on a listing only one of them is using.
        $this->assertSame(1, $space->deletionBlockers()[0]['count']);
        $this->assertSame(1, $space->deletionBlockers()[1]['count']);
    }

    public function test_the_purge_keeps_a_listing_whose_bookings_are_only_history(): void
    {
        $space = $this->space();
        $booking = $this->bookingOn($space, 'completed');
        Payment::create([
            'booking_id' => $booking->id,
            'amount' => 300.00,
            'status' => Payment::STATUS_RELEASED, // settled — nothing pending, only history
        ]);

        Sanctum::actingAs($this->provider);
        $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();
        $space->forceFill(['delete_scheduled_at' => now()->subDay()])->save();

        // Reported as KEPT, not skipped, and the run itself is a success: there is nothing
        // for a human to fix here.
        $this->artisan('spaces:purge', ['--confirm' => true])
            ->expectsOutputToContain('cannot be destroyed')
            ->assertSuccessful();

        $this->assertDatabaseHas('spaces', ['id' => $space->id]);
        $this->assertSame(0, AuditLog::where('action', 'purge')->count());

        // The client's record of what they bought and paid for is untouched. Destroying it
        // to satisfy a provider's tidy-up is the trade §12 exists to refuse.
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'space_id' => $space->id]);
        $this->assertDatabaseCount('payments', 1);

        // And it stays out of the catalog while it sits there forever — kept is not
        // un-scheduled.
        $this->assertSame(Space::PUBLICATION_UNPUBLISHED, $space->fresh()->publication_status);
        $this->assertFalse(Space::bookable()->where('id', $space->id)->exists());
    }

    /**
     * The half of the defect that cost the most. The un-purgeable listing threw out of the
     * loop, so every listing queued BEHIND it was silently left alone — a run that looked
     * like it had simply found nothing to do.
     */
    public function test_a_listing_that_cannot_be_purged_does_not_hold_up_the_ones_behind_it(): void
    {
        $held = $this->space();
        $this->bookingOn($held, 'completed');
        $clean = $this->space();

        Sanctum::actingAs($this->provider);
        foreach ([$held, $clean] as $space) {
            $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();
            $space->forceFill(['delete_scheduled_at' => now()->subDay()])->save();
        }

        // Ordering matters to the point of the test: the broken one has to be reached first.
        $this->assertLessThan($clean->id, $held->id);

        $this->artisan('spaces:purge', ['--confirm' => true])->assertSuccessful();

        $this->assertDatabaseHas('spaces', ['id' => $held->id]);
        $this->assertDatabaseMissing('spaces', ['id' => $clean->id]);
        $this->assertSame(1, AuditLog::where('action', 'purge')->count());
    }

    /**
     * The same isolation, for a failure nobody predicted. The blocker above closes the one
     * cause we know about; this pins the LOOP, so the next unforeseen throw costs one line
     * of output instead of a whole run. A hook on the delete stands in for it — the shape
     * that matters is "something threw between the listings", not what it was.
     */
    public function test_an_unexpected_failure_is_reported_per_row_and_the_run_carries_on(): void
    {
        $doomed = $this->space();
        $clean = $this->space();

        Sanctum::actingAs($this->provider);
        foreach ([$doomed, $clean] as $space) {
            $this->deleteJson("/api/provider/spaces/{$space->id}")->assertOk();
            $space->forceFill(['delete_scheduled_at' => now()->subDay()])->save();
        }

        $doomedId = $doomed->id;
        Space::deleting(function (Space $space) use ($doomedId) {
            if ($space->id === $doomedId) {
                throw new \RuntimeException('something downstream refused this delete');
            }
        });

        $this->artisan('spaces:purge', ['--confirm' => true])
            ->expectsOutputToContain('FAILED  #' . $doomedId)
            // A FAILED row is the one outcome that needs a person, so the command says so
            // in its exit code too.
            ->assertExitCode(1);

        $this->assertDatabaseHas('spaces', ['id' => $doomedId]);
        $this->assertDatabaseMissing('spaces', ['id' => $clean->id]);

        // The audit entry is written inside the same transaction as the delete, so the row
        // that failed left no record claiming it was purged.
        $this->assertSame(1, AuditLog::where('action', 'purge')->count());
    }
}
