<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UC-32 · design.json §8 — the Admin payout-stop WINDOW and the clawback ledger type.
 *
 * §8: "Once approved and nothing holds it, **Admin** has a configurable window
 * (default 24 h) to stop it; if Admin doesn't, funds release automatically."
 *
 * A window needs a start, and the payment had none. `updated_at` is not it: it moves
 * on every unrelated touch, so a payout could silently gain or lose hours of admin
 * reach because somebody edited a transaction id. The start is the moment the payout
 * became RELEASABLE — in live code that is the moment the client accepts the proof
 * and `payments.status` becomes `free_payment` (Client\ProofFlagController::accept).
 * `payout_releasable_at` records exactly that instant, once, and never moves again.
 *
 * `payout_stopped_at` marks an admin stop, so the §12 dashboard indicator "payments
 * stopped" can be told apart from a payout frozen by a client's proof rejection —
 * both sit in `payments.status = held`, which stays the ONLY authority over where
 * the money is (§2/§8); these columns annotate WHEN and WHY, never WHETHER.
 *
 * `clawed_back_at` marks a payout that was already RELEASED to the provider and then
 * reversed. §8 puts the reversal of a released payout outside the refund path on
 * purpose ("reversing it is a clawback (UC-32), not a refund"), because a refund
 * credits the CLIENT while the provider keeps the money.
 *
 * The ledger gains a `clawback` type for the same reason: a clawback is a NEW,
 * signed-negative, independently keyed entry against the provider. It must never be
 * netted against the original `escrow_release` row — `wallet_entries` is append-only
 * and double-entry (§8), and a ledger whose past rows change is not a ledger.
 */
return new class extends Migration
{
    private const OLD_TYPES = ['refund', 'withdrawal', 'escrow_capture', 'escrow_release', 'adjustment'];

    private const NEW_TYPES = ['refund', 'withdrawal', 'escrow_capture', 'escrow_release', 'adjustment', 'clawback'];

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('payout_releasable_at')->nullable()->after('status');
            $table->timestamp('payout_stopped_at')->nullable()->after('payout_releasable_at');
            $table->timestamp('clawed_back_at')->nullable()->after('payout_stopped_at');
        });

        $this->setTypeCheck(self::NEW_TYPES);
    }

    public function down(): void
    {
        // A clawback row cannot be re-labelled without lying about what it is; the
        // rollback drops the constraint's knowledge of it only after there is none.
        DB::table('wallet_entries')->where('type', 'clawback')->delete();

        $this->setTypeCheck(self::OLD_TYPES);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payout_releasable_at', 'payout_stopped_at', 'clawed_back_at']);
        });
    }

    /** Laravel's enum() is a varchar + CHECK on Postgres, so the set is edited by DDL. */
    private function setTypeCheck(array $values): void
    {
        $list = implode(',', array_map(fn ($v) => "'" . $v . "'", $values));

        DB::statement('ALTER TABLE wallet_entries DROP CONSTRAINT IF EXISTS wallet_entries_type_check');
        DB::statement("ALTER TABLE wallet_entries ADD CONSTRAINT wallet_entries_type_check CHECK (type IN ({$list}))");
    }
};
