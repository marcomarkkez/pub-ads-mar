<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Proof;
use App\Models\Space;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * design.json §3 — the account owner's own account: `GET /account`, `DELETE /account`.
 *
 * WHO MAY DELETE (owner 2026-08-03): "Borrado no, sólo el dueño del account
 * puede… el admin sólo puede quitar usuarios de sus roles, no eliminar." So the
 * only delete verb for an account in the whole API is this one, and it acts on
 * the CALLER — there is no `{id}` to point at somebody else.
 *
 * TWO REFUSALS, both 409 (owner 2026-08-03: in-use / conflicting state is 409,
 * 422 stays for malformed payloads):
 *
 *  1. THE DATABASE REFUSES. campaigns, spaces, bookings and wallet_entries hold
 *     `ON DELETE RESTRICT` on their user FK. This controller does NOT pre-check
 *     them: it attempts the delete and translates the engine's refusal. That is
 *     deliberate — a pre-check can drift out of step with the schema and start
 *     lying, whereas the constraint is the same rule the DB will enforce against
 *     any client, including a psql session. The counts in the response are read
 *     AFTER the refusal, to explain it, not to decide it.
 *
 *  2. THE PROOFS ARE NOT ACKNOWLEDGED. Owner 2026-08-03, on whether written
 *     messages and uploaded proofs survive their author's deletion: "Sí, a menos
 *     que el autor sea el dueño de la cuenta, no un usuario collaborator, que
 *     confirma que sabe que sin pruebas no se le hará el pago." A proof is the
 *     evidence a display actually happened — it is what a payout is argued from
 *     in a dispute. A collaborator (an installator crew) walking away must never
 *     take it: the FK is `ON DELETE SET NULL`, the file stays, the authorship
 *     link goes. The ACCOUNT OWNER is the exception, because destroying it costs
 *     only them, and they have to say so: `confirm_proof_loss: true`. Without
 *     the flag this is 409 and nothing is touched; with it, the proofs they
 *     uploaded ARE deleted (otherwise the confirmation confirms nothing) and the
 *     acknowledgement itself is audited.
 *
 * Their MESSAGES are kept either way, with the author nulled. The confirmation
 * is worded about proofs and payment; a chat is a shared record, and punching
 * holes in one party's half would corrupt the counterparty's copy of the very
 * conversation a dispute is read from.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * AD-delguard-09 · UC-37 (owner 2026-08-04) — WHAT THE CONFIRMATION CANNOT BUY.
 *
 * "Si el dueño de la cuenta es el proveedor, borrarse a sí mismo destruye la
 *  evidencia de disputas que quizá afecten a clientes que ya pagaron. Aquí
 *  volvemos a los guardrails tipo 'programar para destruir' donde no se puede
 *  borrar algo por completo, solo despublicarse si es que hay elementos en
 *  disputa."
 *
 * Refusal 2 above reads a proof as a thing the OWNER loses. In a dispute it is
 * not: it is the record two parties argue from, and the client on the other side
 * never agreed to lose it. `confirm_proof_loss` is a consent, and a consent is
 * only ever worth the consenter's own half. So DELETE now has three outcomes and
 * the flag can only decide the third:
 *
 *   1. AN OPEN DISPUTE      409, always. Not with the flag, not with any flag.
 *      (Account::disputeBlockers)   Nothing is unpublished, nothing is scheduled,
 *                                   nothing is touched. The blockers travel with
 *                                   the refusal so the owner knows what to close.
 *
 *   2. IN USE, NOT DISPUTED 200 — but a deletion is PROGRAMMED, not performed.
 *      (Account::inUseBlockers)     Same shape as one listing under §12/UC-37:
 *                                   the account goes `unpublished` (its whole
 *                                   catalog leaves circulation through
 *                                   Space::scopeBookable) and gets a
 *                                   `delete_scheduled_at` at `deletion_grace_days`.
 *                                   Nothing is destroyed HERE, by anyone —
 *                                   `accounts:purge` is a separate manual command
 *                                   that re-asks every blocker at the moment it
 *                                   runs.
 *
 *   3. NOTHING ATTACHED     the immediate delete this method always did.
 *
 * The proof confirmation is demanded BEFORE branching between 2 and 3, and when
 * it is given on the way into 2 it is PERSISTED (`accounts.proof_loss_confirmed_at`)
 * — the purge that finally destroys those proofs runs weeks later and must be able
 * to point at the acknowledgement rather than trust a boolean nobody kept.
 */
class AccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $account = $request->user()->account;

        if ($account === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($account->loadCount('collaborators', 'campaigns'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        if ($account === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // ── 1. An open dispute refuses outright ──────────────────────────────
        //
        // FIRST, and before the confirmation is even read: the whole point is that
        // `confirm_proof_loss=true` must not reach this decision. 409 and not 422 —
        // the payload is fine, it is the state that says no (owner 2026-08-03).
        $disputes = $account->disputeBlockers();

        if ($disputes !== []) {
            return response()->json([
                'error_code' => ApiErrorCode::ObjectInUse->value,
                'message' => 'This account is party to an open dispute and cannot be deleted — '
                    . 'not even with confirm_proof_loss. The chats, messages and proofs under a dispute '
                    . 'are the other party\'s record too, and your confirmation only covers your own loss. '
                    . 'Close the dispute first; Support can tell you where it stands.',
                'blockers' => $disputes,
            ], 409);
        }

        if ($account->isScheduledForDeletion()) {
            return response()->json([
                'error_code' => ApiErrorCode::AlreadyExists->value,
                'message' => 'A deletion is already programmed on this account.',
                'delete_scheduled_at' => $account->delete_scheduled_at,
            ], 409);
        }

        $proofCount = $account->ownerProofCount();
        $confirmed = $request->boolean('confirm_proof_loss');

        if ($proofCount > 0 && ! $confirmed) {
            return response()->json([
                'error_code' => ApiErrorCode::ConfirmationRequired->value,
                'message' => 'Deleting your account destroys the ' . $proofCount . ' proof(s) you uploaded. '
                    . 'Without proof of display a payment cannot be released to you. '
                    . 'Send confirm_proof_loss=true to confirm you understand this.',
                'proofs' => $proofCount,
                'confirmation_field' => 'confirm_proof_loss',
            ], 409);
        }

        // ── 2. In use but not disputed: unpublish and program a date ─────────
        $inUse = $account->inUseBlockers();

        if ($inUse !== []) {
            return $this->scheduleDeletion($request, $account, $proofCount, $confirmed, $inUse);
        }

        // ── 3. Nothing attached: the immediate delete ────────────────────────
        try {
            DB::transaction(function () use ($request, $user, $account, $proofCount, $confirmed) {
                if ($proofCount > 0) {
                    AuditLog::recordOn(
                        $request->user(),
                        'confirm_proof_loss',
                        'proofs',
                        null,
                        ['uploaded_by_user_id' => $user->id, 'count' => $proofCount],
                        null,
                        'account owner confirmed that deleting the account destroys their proofs '
                        . 'and that no payment can be released without them (§3, owner 2026-08-03)',
                    );

                    Proof::where('uploaded_by_user_id', $user->id)->delete();
                }

                AuditLog::record(
                    $request->user(),
                    'delete',
                    $account,
                    ['account' => $account->getAttributes(), 'owner_user_id' => $user->id, 'owner_email' => $user->email],
                    null,
                    'account owner deleted their own account'
                    . ($confirmed ? ' (confirm_proof_loss=true)' : ''),
                );

                // Sanctum tokens carry no FK to users — nothing would clean them up.
                $user->tokens()->delete();

                // The user first: users.account_id is RESTRICT, so the account row
                // can only go once nothing points at it.
                $user->delete();
                $account->delete();
            });
        } catch (QueryException $e) {
            if (! $this->isForeignKeyViolation($e)) {
                throw $e;
            }

            return response()->json([
                'error_code' => ApiErrorCode::ObjectInUse->value,
                'message' => 'This account still owns objects the platform must keep. '
                    . 'Remove or transfer them before deleting the account.',
                'blocking' => $this->blockingCounts($user),
            ], 409);
        }

        return response()->json(['message' => 'Account deleted.']);
    }

    /**
     * AD-delguard-09 · UC-37 — the space treatment, one level up. Nothing is destroyed
     * here: the account leaves circulation and acquires a date, and a human running
     * `accounts:purge` is the only thing that can act on that date.
     */
    private function scheduleDeletion(
        Request $request,
        Account $account,
        int $proofCount,
        bool $confirmed,
        array $blockers,
    ): JsonResponse {
        $graceDays = (int) SystemConfiguration::get('deletion_grace_days', 30);

        DB::transaction(function () use ($request, $account, $proofCount, $confirmed, $graceDays, $blockers) {
            $before = [
                'publication_status' => $account->publication_status,
                'delete_scheduled_at' => null,
            ];

            // Named attributes, not $fillable: no payload can put an account into a
            // programmed deletion by accident (§12 idiom, same as Space).
            $account->publication_status = Account::PUBLICATION_UNPUBLISHED;
            $account->delete_scheduled_at = now()->addDays($graceDays);

            if ($proofCount > 0 && $confirmed) {
                // The acknowledgement is given NOW and spent WEEKS from now, so it is
                // written down. Without this the purge would destroy an owner's proofs
                // on the strength of a boolean that left with the HTTP request.
                $account->proof_loss_confirmed_at = now();

                AuditLog::recordOn(
                    $request->user(),
                    'confirm_proof_loss',
                    'proofs',
                    null,
                    ['uploaded_by_user_id' => $account->owner?->id, 'count' => $proofCount],
                    null,
                    'account owner confirmed that the programmed deletion destroys their proofs '
                    . 'and that no payment can be released without them (§3, owner 2026-08-03)',
                );
            }

            $account->save();

            AuditLog::record(
                $request->user(),
                'schedule_deletion',
                $account,
                $before,
                [
                    'publication_status' => $account->publication_status,
                    'delete_scheduled_at' => $account->delete_scheduled_at,
                    'blockers' => $blockers,
                ],
                'account owner unpublished their account and programmed its deletion in '
                . $graceDays . ' days; objects still in use (§3 · §12 · UC-37)',
            );
        });

        return response()->json([
            'message' => 'Account unpublished and programmed for deletion. '
                . 'Objects still in use keep it alive until then; nothing has been destroyed.',
            'blockers' => $blockers,
            'account' => $account->fresh(),
        ]);
    }

    /**
     * The way back. Without it the only exit from `unpublished` would be the purge,
     * and an owner who changed their mind would have no move at all.
     */
    public function cancelDeletion(Request $request): JsonResponse
    {
        $account = $request->user()->account;

        if ($account === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! $account->isScheduledForDeletion()) {
            return response()->json([
                'error_code' => ApiErrorCode::ConflictingState->value,
                'message' => 'No deletion is programmed on this account.',
            ], 409);
        }

        DB::transaction(function () use ($request, $account) {
            $before = [
                'publication_status' => $account->publication_status,
                'delete_scheduled_at' => $account->delete_scheduled_at,
                'proof_loss_confirmed_at' => $account->proof_loss_confirmed_at,
            ];

            $account->publication_status = Account::PUBLICATION_PUBLISHED;
            $account->delete_scheduled_at = null;
            // The acknowledgement dies with the decision it was given for. If the owner
            // programs another deletion later they are asked again — a stale "yes" to
            // destroying evidence is not a "yes" anybody should be able to bank.
            $account->proof_loss_confirmed_at = null;
            $account->save();

            AuditLog::record(
                $request->user(),
                'cancel_deletion',
                $account,
                $before,
                ['publication_status' => $account->publication_status, 'delete_scheduled_at' => null],
                'account owner cancelled the programmed deletion (§3 · UC-37)',
            );
        });

        return response()->json([
            'message' => 'Programmed deletion cancelled.',
            'account' => $account->fresh(),
        ]);
    }

    /**
     * Postgres SQLSTATE 23503 = foreign_key_violation — i.e. one of the RESTRICT
     * guardrails fired. Any other query error is a real fault and is rethrown.
     */
    private function isForeignKeyViolation(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? $e->getCode()) === '23503';
    }

    /** Read after the refusal, to explain it — never to decide it. */
    private function blockingCounts(User $user): array
    {
        return array_filter([
            'campaigns' => Campaign::where('user_id', $user->id)->count(),
            'spaces' => Space::where('user_id', $user->id)->count(),
            'bookings' => Booking::where('client_user_id', $user->id)->count(),
            'wallet_entries' => WalletEntry::where('user_id', $user->id)->count(),
        ]);
    }
}
