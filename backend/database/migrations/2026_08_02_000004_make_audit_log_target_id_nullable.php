<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * design.json §12 (UC-31) — AU-record-02.
 *
 * Wiring the rest of the §2 ✏/$ actions into the log surfaced actions whose
 * target is a SET, not a row: "admin rewrote the permission matrix of role
 * support" targets `role_permissions` as a whole. Inventing an id for those
 * would make the entry unjoinable and quietly wrong, so target_id becomes
 * nullable and AuditLog::recordOn() writes null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('target_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows with a null target_id cannot survive the column going back to
        // NOT NULL, and the log is append-only — so drop nothing, refuse instead.
        if (DB::table('audit_logs')->whereNull('target_id')->exists()) {
            throw new RuntimeException(
                'audit_logs has set-scoped entries (target_id IS NULL); reverting would require deleting append-only rows.'
            );
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('target_id')->nullable(false)->change();
        });
    }
};
