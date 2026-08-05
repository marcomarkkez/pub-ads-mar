<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * design.json §3 (UC-19/UC-20) — `collaborators.status` gains `declined`.
 *
 * WHY A FOURTH VALUE AND NOT A REUSE OF `revoked`: the three existing values answer
 * "does this grant give access", and for that question a decline and a revocation look
 * identical — neither grants anything. They are opposite answers to a different and more
 * important question: WHO closed the door. `revoked` is the account owner taking access
 * away from someone who had it; `declined` is the invited person never having walked in.
 * That difference is the entire reason declining is allowed at all while unlinking is not
 * (BR-15, owner 2026-08-04) — see CollaborationController::decline. Collapsing the two
 * into one column value would make the rule unexplainable to the next reader, and the
 * next reader is the one who decides whether a "revoked" row may be self-served.
 *
 * Postgres stores Laravel's `enum()` as varchar + a CHECK constraint (see the earlier
 * `alter_collaborators_role_to_string` migration, which learned the same thing), so
 * widening the set is a constraint swap and not a type change. The existing DEFAULT
 * ('pending') is a member of both the old and the new set, so it survives untouched and
 * there is no ordering hazard here — the hazard only appears when the default leaves the
 * allowed set, or when `->change()` is used and silently rewrites the column definition,
 * dropping the default before the new CHECK lands. Raw SQL keeps both facts visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE collaborators DROP CONSTRAINT IF EXISTS collaborators_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE collaborators
              ADD CONSTRAINT collaborators_status_check
              CHECK (status IN ('pending', 'accepted', 'revoked', 'declined'))
        SQL);
    }

    public function down(): void
    {
        // A declined invitation reverts to the closest older meaning — "no access" — so
        // the narrower CHECK can land. The information about WHO closed the door is what
        // the down-migration costs; that is the price of removing the column value.
        DB::statement("UPDATE collaborators SET status = 'revoked' WHERE status = 'declined'");

        DB::statement('ALTER TABLE collaborators DROP CONSTRAINT IF EXISTS collaborators_status_check');
        DB::statement(<<<'SQL'
            ALTER TABLE collaborators
              ADD CONSTRAINT collaborators_status_check
              CHECK (status IN ('pending', 'accepted', 'revoked'))
        SQL);
    }
};
