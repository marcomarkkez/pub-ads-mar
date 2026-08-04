<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * §8 (owner 2026-08-03) — `payments.status` gains `free_payment`.
 *
 * Until now `held` meant two incompatible things and the column could not tell them
 * apart:
 *   - the client ACCEPTED the proof, so the payout is merely waiting for Payments to
 *     run the release — the happy path;
 *   - the client REJECTED the proof, so the payout is frozen inside a live dispute.
 *
 * Both wrote `held`. The Payments dashboard therefore summed "ready to pay" and
 * "frozen, do not touch" into one tile, and `releasePayout()` could not trust the
 * payment at all — it had to reach back into `booking.proofs` and re-derive the
 * client's verdict on every call, which is how the authority over money ended up
 * living in a table that does not hold any.
 *
 * `free_payment` is the accepted-and-releasable state. After this, `held` means
 * exactly one thing: frozen.
 *
 * Existing rows are NOT remapped. An old `held` row is ambiguous by construction —
 * guessing which of the two it meant would invent facts. They stay `held` (the safe
 * reading: frozen until a human looks), and the demo data is reseeded anyway.
 */
return new class extends Migration
{
    private const OLD = ['pending', 'completed', 'failed', 'refunded', 'held', 'released'];

    private const NEW = ['pending', 'completed', 'failed', 'refunded', 'held', 'released', 'free_payment'];

    public function up(): void
    {
        $this->setCheck(self::NEW);
    }

    public function down(): void
    {
        // Nothing may sit in a value the old CHECK forbids, or the constraint cannot land.
        DB::statement("UPDATE payments SET status = 'held' WHERE status = 'free_payment'");

        $this->setCheck(self::OLD);
    }

    /** Laravel's enum() is a varchar + CHECK on Postgres, so the set is edited by DDL. */
    private function setCheck(array $values): void
    {
        $list = implode(',', array_map(fn ($v) => "'" . $v . "'", $values));

        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ({$list}))");
    }
};
