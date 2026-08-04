<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AD-delguard-09 · design.json §3/§12 · UC-37 — "programmed for deletion", for ACCOUNTS.
 *
 * Owner 2026-08-04: "si el dueño de la cuenta es el proveedor, borrarse a sí mismo
 * destruye la evidencia de disputas que quizá afecten a clientes que ya pagaron. Aquí
 * volvemos a los guardrails tipo 'programar para destruir' donde no se puede borrar algo
 * por completo, solo despublicarse si es que hay elementos en disputa."
 *
 * §12/UC-37 already did this for one listing (AD-delguard-08). The account is the same
 * shape one level up, so the columns are the same three words and NOT a second dialect:
 *
 *   publication_status   published | unpublished. `unpublished` is a CONSEQUENCE —
 *                        a deletion is programmed on this account — and it is what
 *                        Space::scopeBookable() reads as its fifth level, so every
 *                        listing the account owns leaves the catalog at once without
 *                        any per-space write that a later restore would have to undo.
 *                        Accounts have no `paused`: there is no owner-facing lever to
 *                        keep in step with, so the value has exactly two states.
 *
 *   delete_scheduled_at  null = nothing programmed. Non-null = the earliest moment
 *                        `accounts:purge` may destroy the owner + the account row.
 *
 *   proof_loss_confirmed_at
 *                        the `confirm_proof_loss` acknowledgement, PERSISTED. Before
 *                        this the confirmation and the destruction were the same HTTP
 *                        request, so a boolean in the payload was enough. Now weeks sit
 *                        between them, and the purge must not destroy an owner's proofs
 *                        on the strength of a flag nobody can point at any more. Null +
 *                        proofs on file = the purge SKIPS the account.
 *
 * The account's own FK guardrails are unchanged and still do the real work:
 * `users.account_id` is RESTRICT (the account row can only go once its owner is gone)
 * and §3's campaigns/spaces/bookings/wallet_entries are RESTRICT on the owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->enum('publication_status', ['published', 'unpublished'])
                ->default('published')
                ->after('type');
            $table->timestamp('delete_scheduled_at')->nullable()->after('publication_status');
            $table->timestamp('proof_loss_confirmed_at')->nullable()->after('delete_scheduled_at');
        });

        // Nothing is programmed until somebody programs it.
        DB::statement("UPDATE accounts SET publication_status = 'published'");
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['publication_status', 'delete_scheduled_at', 'proof_loss_confirmed_at']);
        });
    }
};
