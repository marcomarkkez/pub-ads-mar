<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * design.json §3/§21 — a campaign belongs to an ACCOUNT, not to a person.
 *
 * `user_id` is KEPT (see the comment on App\Models\Campaign): it is now the
 * CREATOR/owner-user link, `account_id` is the SCOPING key. Under MVP 1:1 they
 * are redundant; under the multi-owner accounts this table exists to enable they
 * are two different facts, and every §21 chain check, controller and test in the
 * app already authorizes on user_id.
 *
 * Rows whose owner has no account (staff-owned test junk) are DELETED — owner
 * 2026-08-01/03: "Existing rows are throwaway test data".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
        });

        DB::statement('UPDATE campaigns SET account_id = users.account_id FROM users WHERE users.id = campaigns.user_id');
        DB::table('campaigns')->whereNull('account_id')->delete();

        DB::statement('ALTER TABLE campaigns ALTER COLUMN account_id SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
