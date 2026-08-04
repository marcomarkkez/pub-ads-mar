<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-29 · design.json §12 — the state that makes admin moderation DIFFERENT from
 * the provider's own pause.
 *
 * §12 names THREE levels of taking a listing off, and until now the schema could
 * only express one of them:
 *
 *   1. pause / unpublish — the PROVIDER's own, reversible by them. That is
 *      `spaces.is_active`, which already exists.
 *   2. takedown / restore — ADMIN moderation; "the provider CANNOT self-reverse".
 *   3. freeze — ADMIN, ACCOUNT-level: no new bookings, upcoming ones auto-cancelled
 *      and refunded.
 *
 * Levels 2 and 3 cannot reuse `is_active` / `users.is_active`: those columns are
 * writable by the very person the moderation acts against (the provider's own
 * space PUT, the admin user CRUD), so a takedown expressed as `is_active = false`
 * is undone by one click on the provider's own screen — which is exactly the
 * thing §12 forbids. They get their own columns, and NO route lets the provider
 * write them.
 *
 * WHO did it is deliberately NOT stored here. Every one of these actions writes an
 * AuditLog row with actor, target, before/after and timestamp (§12, UC-31); a
 * `taken_down_by_user_id` next to it would be a second, divergeable copy of a fact
 * the append-only log already owns. The REASON is stored, because it is shown to
 * the affected provider and is not an audit concern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->timestamp('taken_down_at')->nullable()->after('is_active');
            $table->string('takedown_reason', 500)->nullable()->after('taken_down_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('frozen_at')->nullable()->after('is_active');
            $table->string('freeze_reason', 500)->nullable()->after('frozen_at');
        });
    }

    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table) {
            $table->dropColumn(['taken_down_at', 'takedown_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['frozen_at', 'freeze_reason']);
        });
    }
};
