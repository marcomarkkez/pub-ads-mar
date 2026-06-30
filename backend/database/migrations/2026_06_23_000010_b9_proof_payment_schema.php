<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B9 proof/payment rework — schema (minimal, MVP).
 *
 * Owner decision: the CLIENT accepts/rejects the provider's proof (not Payments).
 * Accept -> payout releasable; reject -> payout HELD (payments.status already has
 * held|released). We only ADD the two client review states to proofs.status and a
 * rejection_reason for the provider Approve/Reject-with-reason action. No full
 * state-machine rewrite (kept out of scope to avoid overengineering the MVP).
 */
return new class extends Migration
{
    public function up(): void
    {
        // proofs.status: Laravel's enum() is a varchar + CHECK constraint on Postgres.
        // Widen the allowed set to include the client review states.
        DB::statement('ALTER TABLE proofs DROP CONSTRAINT IF EXISTS proofs_status_check');
        DB::statement("ALTER TABLE proofs ADD CONSTRAINT proofs_status_check CHECK (status IN ('pending_review','approved','rejected','client_accepted','client_rejected'))");

        if (! Schema::hasColumn('bookings', 'rejection_reason')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        DB::statement("UPDATE proofs SET status = 'rejected' WHERE status IN ('client_accepted','client_rejected')");
        DB::statement('ALTER TABLE proofs DROP CONSTRAINT IF EXISTS proofs_status_check');
        DB::statement("ALTER TABLE proofs ADD CONSTRAINT proofs_status_check CHECK (status IN ('pending_review','approved','rejected'))");

        if (Schema::hasColumn('bookings', 'rejection_reason')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
