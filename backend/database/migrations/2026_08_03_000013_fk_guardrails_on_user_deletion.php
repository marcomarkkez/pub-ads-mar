<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * design.json §3/§12/§16 — what the DATABASE does when a user row disappears.
 *
 * Until now every root FK to `users` was `ON DELETE CASCADE`. One `DELETE FROM
 * users WHERE id = ?` therefore destroyed that person's campaigns, spaces,
 * bookings AND their entire wallet ledger, inside the same statement, before any
 * application `if` could run. An audit entry written afterwards recorded a loss
 * it could not undo. Guardrails belong in the engine that performs the delete.
 *
 * TWO POLICIES, chosen per column by asking "is this row the user's PROPERTY, or
 * is it something the user WROTE?":
 *
 *  RESTRICT — property, and the platform's own record of money and obligations.
 *    campaigns.user_id, spaces.user_id, bookings.client_user_id,
 *    wallet_entries.user_id. These cannot be orphaned (a booking with no client
 *    is not a booking) and must not vanish (a wallet ledger that can be deleted
 *    is not a ledger). The DB refuses the delete; the API surfaces that as 409.
 *
 *  SET NULL — authored CONTENT, which outlives its author.
 *    messages.sender_user_id, proofs.uploaded_by_user_id — owner 2026-08-03:
 *    "¿Los mensajes escritos y las pruebas subidas deben sobrevivir al borrado de
 *    su autor? — Sí, a menos que el autor sea el dueño de la cuenta, no un usuario
 *    collaborator, que confirma que sabe que sin pruebas no se le hará el pago."
 *    A collaborator (an installator crew that photographed the ad) leaving must
 *    never delete the evidence the provider gets PAID on. The text stays, the
 *    authorship link goes null. The account-OWNER case is an APPLICATION rule,
 *    not a DB one — it needs an explicit confirmation, so it lives in
 *    App\Http\Controllers\AccountController.
 *
 *    chats.opened_by_user_id and chat_objects.attached_by_user_id are in this
 *    group for the same reason: they were CASCADE, so deleting whoever opened a
 *    conversation deleted the conversation and with it every message in it —
 *    including the counterparty's. "Messages survive their author" is not true
 *    unless their chat does.
 *
 * Left CASCADE on purpose: chat_participants.user_id and chat_flags.created_by_user_id
 * are MEMBERSHIP and moderation records about a person, not content by them; when
 * the person is gone the membership genuinely is too.
 *
 * Postgres-specific DDL (`ALTER COLUMN … DROP NOT NULL`): this app runs on
 * Postgres in dev, test and prod — there is no second driver to keep portable.
 */
return new class extends Migration
{
    /** column => table, for the "this is property, refuse the delete" set. */
    private const RESTRICT = [
        'campaigns' => 'user_id',
        'spaces' => 'user_id',
        'bookings' => 'client_user_id',
        'wallet_entries' => 'user_id',
    ];

    /** column => table, for the "this is content, keep it and null the author" set. */
    private const NULLIFY = [
        'messages' => 'sender_user_id',
        'proofs' => 'uploaded_by_user_id',
        'chats' => 'opened_by_user_id',
        'chat_objects' => 'attached_by_user_id',
    ];

    public function up(): void
    {
        foreach (self::RESTRICT as $table => $column) {
            $this->repoint($table, $column, 'restrict');
        }

        foreach (self::NULLIFY as $table => $column) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL");
            $this->repoint($table, $column, 'null');
        }
    }

    public function down(): void
    {
        foreach (array_merge(self::RESTRICT, self::NULLIFY) as $table => $column) {
            $this->repoint($table, $column, 'cascade');
        }

        foreach (self::NULLIFY as $table => $column) {
            DB::table($table)->whereNull($column)->delete();
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET NOT NULL");
        }
    }

    private function repoint(string $table, string $column, string $onDelete): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($column, $onDelete) {
            $blueprint->dropForeign([$column]);

            $foreign = $blueprint->foreign($column)->references('id')->on('users');

            match ($onDelete) {
                'restrict' => $foreign->restrictOnDelete(),
                'null' => $foreign->nullOnDelete(),
                'cascade' => $foreign->cascadeOnDelete(),
            };
        });
    }
};
