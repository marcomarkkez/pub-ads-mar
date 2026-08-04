<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * design.json §3 (AC-collab-03) — collaborators are ACCOUNT-scoped:
 *   "a collaborator points to **account_id** (never a campaign or space) […]
 *    (DB: `collaborators` table, `unique(account_id, email)`.)"
 *
 * THE BUG THIS CLOSES: the old key was `unique(campaign_id, email)`. The same
 * person could therefore be invited once per campaign — five campaigns, five
 * live collaborator rows for one human, five revokes needed to actually remove
 * them, and no screen anywhere showed that. Revoking "the" collaborator revoked
 * one row and left the other four granting access. `unique(account_id, email)`
 * makes a person a single fact about the account, which is what §3 always said.
 *
 * cascadeOnDelete on account_id: a collaborator row is a grant INTO one account
 * and is meaningless once that account is gone. It carries no authored content —
 * the messages and proofs the person wrote survive them (see the FK guardrails
 * migration), the grant does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaborators', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE collaborators
               SET account_id = users.account_id
              FROM campaigns
              JOIN users ON users.id = campaigns.user_id
             WHERE campaigns.id = collaborators.campaign_id
        SQL);

        // Throwaway test data (owner 2026-08-01): a row that cannot be mapped to an
        // account is dropped rather than backfilled into a guessed one.
        DB::table('collaborators')->whereNull('account_id')->delete();

        // Collapse the duplicates the old key allowed: keep the FIRST invite per
        // (account, email) — the one whose acceptance the person actually acted on.
        DB::statement(<<<'SQL'
            DELETE FROM collaborators c
             USING collaborators keep
             WHERE c.account_id = keep.account_id
               AND c.email = keep.email
               AND c.id > keep.id
        SQL);

        Schema::table('collaborators', function (Blueprint $table) {
            $table->dropUnique(['campaign_id', 'email']);
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('campaign_id');
            $table->unique(['account_id', 'email']);
        });

        DB::statement('ALTER TABLE collaborators ALTER COLUMN account_id SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('collaborators', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'email']);
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['campaign_id', 'email']);
        });
    }
};
