<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Q42/Q53 — collapse proofs.status to the three states that actually exist.
 *
 * The column carried FIVE values, two of them fossils from the era when PAYMENTS
 * reviewed the proof: `approved`/`rejected`. Since B9 the CLIENT reviews it, so those
 * two were never written again — but they stayed legal, which meant a reader of the
 * schema could not tell which vocabulary was live.
 *
 * Owner ruling (Q53): the waiting state is named for the event that produced it —
 * the provider UPLOADED — not for a review queue that nobody owns. `pending_review`
 * implied Support had an inbox; it never did. Final set:
 *
 *   proof_uploaded   provider uploaded, waiting on the client   (the default)
 *   client_accepted  client accepted  -> payout releasable
 *   client_rejected  client rejected  -> payout held + 3 dispute chats
 *
 * The legacy pair maps onto its modern equivalent rather than being dropped, so
 * existing demo/production rows keep their meaning instead of failing the CHECK.
 */
return new class extends Migration
{
    /** Laravel's enum() is a varchar + CHECK on Postgres, so the set is edited by DDL. */
    public function up(): void
    {
        DB::statement('ALTER TABLE proofs DROP CONSTRAINT IF EXISTS proofs_status_check');

        DB::statement("UPDATE proofs SET status = 'proof_uploaded'  WHERE status = 'pending_review'");
        DB::statement("UPDATE proofs SET status = 'client_accepted' WHERE status = 'approved'");
        DB::statement("UPDATE proofs SET status = 'client_rejected' WHERE status = 'rejected'");

        // The default has to move BEFORE the constraint lands, or the column would
        // default to a value the CHECK forbids.
        DB::statement("ALTER TABLE proofs ALTER COLUMN status SET DEFAULT 'proof_uploaded'");

        DB::statement("ALTER TABLE proofs ADD CONSTRAINT proofs_status_check CHECK (status IN ('proof_uploaded','client_accepted','client_rejected'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE proofs DROP CONSTRAINT IF EXISTS proofs_status_check');

        DB::statement("UPDATE proofs SET status = 'pending_review' WHERE status = 'proof_uploaded'");

        DB::statement("ALTER TABLE proofs ALTER COLUMN status SET DEFAULT 'pending_review'");

        // Back to the five-value set B9 left behind (client_accepted/client_rejected
        // stay legal there, so those rows survive the rollback untouched).
        DB::statement("ALTER TABLE proofs ADD CONSTRAINT proofs_status_check CHECK (status IN ('pending_review','approved','rejected','client_accepted','client_rejected'))");
    }
};
