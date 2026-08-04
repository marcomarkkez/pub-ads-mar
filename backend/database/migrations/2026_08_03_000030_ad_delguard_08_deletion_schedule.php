<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AD-delguard-08 · design.json §12 · UC-37 — "programmed for deletion".
 *
 * Owner 2026-08-03: "No deben poder borrarse ads que tengan aún clientes usándolos,
 * se pueden despublicar y programar para borrado." Nothing that is in use is ever
 * destroyed; deletion becomes a STATE with a date, and a human runs the purge.
 *
 * THREE things land here, and each one is load-bearing.
 *
 * 1. `spaces.publication_status` — the state a blocked/queued listing goes into.
 *    Asked how that state should be represented, the owner answered with one word:
 *    "Unpublished". It gets its OWN enum value and NOT a reuse of `is_active`,
 *    because §12 already spends `is_active` on a different fact:
 *
 *      is_active = false   the PROVIDER paused the listing. Their lever, their
 *                          decision, reversible by them at any time.
 *      unpublished         the listing is out of circulation as a CONSEQUENCE —
 *                          a deletion is programmed. The provider's lever cannot
 *                          give it back; only cancelling the deletion can.
 *      taken_down_at       ADMIN moderation (UC-29), which the provider cannot
 *                          self-reverse at all.
 *
 *    Collapsing the first two into one boolean would mean a provider un-pausing a
 *    listing silently un-queues its deletion, and the glossary entry for
 *    "despublicar (unpublished)" says the opposite: "es distinto de `paused`:
 *    `paused` es una decisión del cliente, `unpublished` es consecuencia de un
 *    borrado programado o de una acción de moderación."
 *
 *    `is_active` is KEPT and keeps meaning exactly what it meant — the provider's
 *    lever. `publication_status` is what the catalog reads (Space::scopeBookable),
 *    and the two are held in step by the one mutator in App\Models\Space.
 *
 * 2. `delete_scheduled_at` on spaces and ads + `unpublished` in the ads.status enum
 *    (ads already had a status enum, so the owner's "own enum value" is literal
 *    there). Null = nothing programmed. Non-null = the earliest moment the purge
 *    command may destroy the row, i.e. the end of the safety window.
 *
 * 3. `bookings.space_id` becomes ON DELETE RESTRICT. This is the actual safety net.
 *    It was CASCADE: one `DELETE FROM spaces WHERE id = ?` destroyed every booking
 *    on that listing and — through `payments.booking_id`, also CASCADE — every
 *    payment on those bookings, in one statement, before any application `if` could
 *    run. An application-level guard that the engine does not back is a guard that a
 *    psql session, a seeder or a future controller walks straight through. Same
 *    policy and same reasoning as the §3/§12 user-deletion guardrails: property and
 *    the platform's record of money are RESTRICT, authored content is SET NULL.
 *
 * Postgres-specific DDL: this app runs on Postgres in dev, test and prod, and
 * Laravel's enum() is a varchar + CHECK there, so an enum set is edited by hand.
 */
return new class extends Migration
{
    private const AD_STATUSES_OLD = [
        'draft', 'pending_approval', 'approved', 'rejected', 'active', 'paused', 'completed', 'cancelled',
    ];

    private const AD_STATUSES_NEW = [
        'draft', 'pending_approval', 'approved', 'rejected', 'active', 'paused', 'completed', 'cancelled', 'unpublished',
    ];

    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->enum('publication_status', ['published', 'paused', 'unpublished'])
                ->default('published')
                ->after('is_active');
            $table->timestamp('delete_scheduled_at')->nullable()->after('takedown_reason');
        });

        // Existing rows carry the provider's lever and nothing else yet, so the two
        // start out in agreement. Nothing is `unpublished` until someone programs a
        // deletion.
        DB::statement("UPDATE spaces SET publication_status = CASE WHEN is_active THEN 'published' ELSE 'paused' END");

        Schema::table('ads', function (Blueprint $table) {
            $table->timestamp('delete_scheduled_at')->nullable()->after('proof_deadline');
        });

        $this->setAdStatusCheck(self::AD_STATUSES_NEW);

        $this->repointBookingSpaceFk('restrict');
    }

    public function down(): void
    {
        $this->repointBookingSpaceFk('cascade');

        // Nothing may sit in a value the old CHECK forbids, or the constraint cannot
        // land. An unpublished ad rolls back to the nearest older meaning: paused.
        DB::statement("UPDATE ads SET status = 'paused' WHERE status = 'unpublished'");
        $this->setAdStatusCheck(self::AD_STATUSES_OLD);

        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn('delete_scheduled_at');
        });

        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn(['publication_status', 'delete_scheduled_at']);
        });
    }

    private function setAdStatusCheck(array $values): void
    {
        $list = implode(',', array_map(fn (string $v) => "'" . $v . "'", $values));

        DB::statement('ALTER TABLE ads DROP CONSTRAINT IF EXISTS ads_status_check');
        DB::statement("ALTER TABLE ads ADD CONSTRAINT ads_status_check CHECK (status IN ({$list}))");
    }

    private function repointBookingSpaceFk(string $onDelete): void
    {
        Schema::table('bookings', function (Blueprint $table) use ($onDelete) {
            $table->dropForeign(['space_id']);

            $foreign = $table->foreign('space_id')->references('id')->on('spaces');

            match ($onDelete) {
                'restrict' => $foreign->restrictOnDelete(),
                'cascade' => $foreign->cascadeOnDelete(),
            };
        });
    }
};
