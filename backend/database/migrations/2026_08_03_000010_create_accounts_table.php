<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * design.json §3 (AC-accounts-01) — the `accounts` table §3 always prescribed and
 * that never existed.
 *
 * DIRECTION (owner 2026-08-01, the latest decision on this table, quoted verbatim):
 *   "Direction: `users.account_id` → FK `accounts` (NOT accounts.owner_user_id),
 *    so several owner-users can later share one account with NO schema change."
 *
 * That is why the link lives on `users` and not the other way round: §15's entity
 * sketch and the `AC-accounts-01` backlog line still say `owner_user_id unique`,
 * but they predate the 2026-08-01 ruling and the file's own rule is "latest
 * decision wins on conflict" (meta.preamble).
 *
 * The MVP invariant "1 user = 1 account" is enforced by a UNIQUE INDEX on
 * users.account_id, not by the column's position. Multi-owner accounts arrive by
 * DROPPING that index — no column moves, no FK rewrite, no data migration.
 *
 * Staff (admin/support/payments) have NO account: `type` is client|provider and a
 * staff user owns no campaigns or spaces. Their account_id stays NULL, which the
 * unique index allows (Postgres treats NULLs as distinct).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['client', 'provider']);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // restrictOnDelete: an account is deleted only AFTER its owner user is
            // gone (App\Http\Controllers\AccountController), never underneath them.
            $table->foreignId('account_id')->nullable()->after('role')->constrained()->restrictOnDelete();
            // MVP 1:1. Drop this one index the day an account gets a second owner.
            $table->unique('account_id');
        });

        $now = now();

        foreach (DB::table('users')->whereIn('role', ['client', 'provider'])->orderBy('id')->get() as $user) {
            $accountId = DB::table('accounts')->insertGetId([
                'name' => $user->company_name ?: $user->name,
                'type' => $user->role,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('users')->where('id', $user->id)->update(['account_id' => $accountId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropUnique(['account_id']);
            $table->dropColumn('account_id');
        });

        Schema::dropIfExists('accounts');
    }
};
