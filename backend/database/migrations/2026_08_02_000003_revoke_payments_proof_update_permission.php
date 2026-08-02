<?php

use App\Models\RolePermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B9 · design.md §7/§17 — Payments must not review proof CONTENT.
 *
 * The `/payments/proofs` approve/reject routes are gone; this revokes the
 * permission that backed them, so the capability disappears from the RBAC
 * matrix too instead of lingering as a grant with nothing to grant.
 *
 * `payments.proofs.read` STAYS: §2 gives Payments 👁 on Proof — it needs to see
 * the proof behind a held payout, it just has no verdict over it. The verdict
 * is the client's (POST /client/proofs/{proof}/accept|reject).
 *
 * The seeder truncates, so it never reaches a populated DB — same reason
 * 2026_08_02_000002 exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')
            ->where('role', 'payments')
            ->where('resource', 'proofs')
            ->where('action', 'update')
            ->delete();

        RolePermission::clearCache('payments');
    }

    public function down(): void
    {
        DB::table('role_permissions')->updateOrInsert(
            ['role' => 'payments', 'resource' => 'proofs', 'action' => 'update'],
            ['allowed' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        RolePermission::clearCache('payments');
    }
};
