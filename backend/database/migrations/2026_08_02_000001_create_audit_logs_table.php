<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * design.md §12 (UC-31) — the immutable audit log.
 *
 * Append-only: one row per action of ANY role, carrying actor, target,
 * before/after and a timestamp. There is no `updated_at` on purpose — a row
 * that can be updated is not an audit trail. Model-level immutability lives in
 * App\Models\AuditLog; the DB-level guarantee (INSERT-only grant + a trigger
 * raising on UPDATE/DELETE, optional hash-chain) is AFTER-MVP per §12.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // The actor survives its user: nullOnDelete keeps the row readable
            // (actor_role + actor_label are snapshots taken at write time).
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 20);
            $table->string('actor_label', 255)->nullable();

            $table->string('action', 40);          // 'update', 'create', 'delete', ...
            $table->string('target_type', 60);     // API resource slug: 'spaces', 'ads', ...
            $table->unsignedBigInteger('target_id');

            // Only the fields that actually changed, mirrored.
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->string('context', 255)->nullable(); // free-text reason/route note

            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->index('actor_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
