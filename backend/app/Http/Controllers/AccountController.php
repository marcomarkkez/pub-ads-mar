<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Proof;
use App\Models\Space;
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

        $proofCount = Proof::where('uploaded_by_user_id', $user->id)->count();
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
