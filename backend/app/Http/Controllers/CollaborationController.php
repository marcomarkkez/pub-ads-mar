<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Models\AuditLog;
use App\Models\Collaborator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * design.json §3 (UC-19/UC-20, WALK-6 step 3) — the receiving end of an invitation:
 * `GET /collaborations`, `POST /collaborations/{id}/accept|decline`.
 *
 * WHAT WAS MISSING: `collaborators.status` has always had an `accepted` value, and
 * `User::accountContext()` has always filtered `collaborating_on` on it — but nothing in
 * the application ever wrote it. Only the seeders did. So in production every invitation
 * stayed `pending` forever, `collaborating_on` was empty for everybody, and an invited
 * collaborator could never actually collaborate: the top half of a staircase with no
 * bottom half. WALK-6 step 3 ("invitar a un colaborador y aceptar la invitación desde la
 * otra sesión") could not be walked at all.
 *
 * SCOPE — read this before adding a method here. These routes sit next to /collaborators
 * and must NOT reuse CollaboratorController's account scope. There the caller acts
 * on their own account (`account_id = mine`); here the caller answers an invitation into
 * SOMEONE ELSE's account, where `account_id` is by definition not theirs. Reusing that
 * scope would match zero rows and 404 forever; dropping the scope instead — checking only
 * "is this user allowed to accept invitations" — is EH-5, the permission check with no
 * scope of its own, which has already bitten this codebase once. The only correct bound
 * is `Collaborator::addressedTo($user)`: this invitation names me. Nothing wider, and an
 * invitation that names somebody else is 404, never 403 (§21 rule 2 — a 403 would confirm
 * the row exists, and here the caller genuinely has no business knowing).
 */
class CollaborationController extends Controller
{
    /**
     * The invitations and collaborations addressed to me.
     *
     * `pending` (what I can act on) and `accepted` (what I am already part of) only:
     * a revoked or declined row grants nothing and offers nothing, so putting it in this
     * list would just be a screen full of endings. `status` travels with each row so the
     * UI never has to infer which of the two it is holding.
     *
     * The inviter is exposed as id+name ONLY. The account name and the offered subrole are
     * what the decision needs; the inviter's email, phone and address are not, and an
     * endpoint that hands out a full User model teaches the next screen to do the same.
     */
    public function index(Request $request): JsonResponse
    {
        $invitations = Collaborator::query()
            ->addressedTo($request->user())
            ->whereIn('status', [Collaborator::STATUS_PENDING, Collaborator::STATUS_ACCEPTED])
            ->with(['account', 'invitedBy:id,name'])
            ->latest()
            ->get();

        return response()->json($invitations);
    }

    /**
     * Walk in. Links `user_id` if the invitation predates my account, and flips the
     * status to `accepted` — the one status that grants anything.
     *
     * IDEMPOTENT: accepting twice is the same fact stated twice, not an error and not a
     * second grant (`unique(account_id, email)` means there is only ever one row to flip
     * anyway). The second call writes NO audit row, because nothing changed and an audit
     * log that records non-events is one nobody can read.
     *
     * AUDITED, and this is the entry that matters most in the whole collaborator flow:
     * it is the moment a person gains the ability to act inside an account that is not
     * theirs, and BR-15 (owner 2026-08-04) then makes that entry impossible for them to
     * undo. The audit row is written inside the same transaction as the status change, so
     * a rolled-back accept leaves no trace of an access that never happened.
     */
    public function accept(Request $request, int $collaborator): JsonResponse
    {
        $invitation = $this->addressedToMe($request, $collaborator);

        if ($invitation === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($invitation->status === Collaborator::STATUS_ACCEPTED) {
            return response()->json($invitation->load('account'));
        }

        if ($invitation->status !== Collaborator::STATUS_PENDING) {
            // Revoked or declined: the door is closed, and it was closed by an act — the
            // owner's or my own. Re-opening it is the OWNER's move (a fresh invite revives
            // the row), never the invitee's, or a declined invitation would be a button
            // the inviter cannot take away.
            return $this->conflict(
                $invitation,
                'This invitation is no longer open (' . $invitation->status . '). '
                . 'The account owner has to invite you again.',
            );
        }

        DB::transaction(function () use ($request, $invitation): void {
            $invitation->fill([
                // The invitation may have been addressed to an email that had no user yet.
                // Registration back-links it (Collaborator::linkNewUser), but an invite
                // sent to an address that ALREADY had a user row and was then matched here
                // by email still needs the link written, or the grant would be invisible
                // to every user_id-keyed ACL in the app.
                'user_id' => $request->user()->id,
                'status' => Collaborator::STATUS_ACCEPTED,
            ]);

            AuditLog::recordChange(
                $request->user(),
                $invitation,
                'collaborator accepted an invitation into another account (§3 UC-20)',
                'accept',
            );

            $invitation->save();
        });

        return response()->json($invitation->load('account'));
    }

    /**
     * Say no. Only from `pending`.
     *
     * WHY THIS DOES NOT CONTRADICT BR-15 (owner 2026-08-04: "no hay formas de desvincular
     * la cuenta de colaboración si alguien la agrega excepto que hablen con soporte") —
     * and this paragraph is here so nobody deletes this endpoint believing it does:
     *
     *   Declining is never having entered. BR-15 is about LEAVING an account you have
     *   already acted inside, and its reason is anti-fraud: the grant is the record of who
     *   was operating the account, so the person who did the acting must not be able to
     *   erase it the minute a dispute opens. A `pending` invitation records no acting.
     *   Nobody uploaded a proof, moved a booking or spoke in a chat under it. There is no
     *   trail to destroy, because there is no trail. Refusing a decline would not protect
     *   anything — it would only mean a stranger can permanently attach their account's
     *   name to your inbox by typing your email once.
     *
     * The moment the invitation is `accepted` that reasoning stops applying, and so does
     * this endpoint: 409, and the same Support message as
     * CollaboratorController::destroy. One rule, stated in the two places a person
     * can reach it from.
     */
    public function decline(Request $request, int $collaborator): JsonResponse
    {
        $invitation = $this->addressedToMe($request, $collaborator);

        if ($invitation === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if ($invitation->status === Collaborator::STATUS_DECLINED) {
            return response()->json($invitation);
        }

        if ($invitation->status === Collaborator::STATUS_ACCEPTED) {
            return $this->conflict(
                $invitation,
                'You already accepted this collaboration. A collaboration cannot be left by the '
                . 'collaborator: only the owner of the account can revoke this access, and anything '
                . 'else has to go through Support — a capability the platform does not have yet.',
            );
        }

        if ($invitation->status !== Collaborator::STATUS_PENDING) {
            // Revoked. Declining it would overwrite the owner's act with mine and lose the
            // only interesting thing the row still says: who closed the door.
            return $this->conflict($invitation, 'This invitation is no longer open (' . $invitation->status . ').');
        }

        DB::transaction(function () use ($request, $invitation): void {
            $invitation->fill(['status' => Collaborator::STATUS_DECLINED]);

            AuditLog::recordChange(
                $request->user(),
                $invitation,
                'invited person declined a collaboration (§3 UC-20)',
                'decline',
            );

            $invitation->save();
        });

        return response()->json($invitation);
    }

    /**
     * The ONE lookup these endpoints may use. See the class docblock: the bound is
     * "this invitation names me", not "this row is on my account".
     */
    private function addressedToMe(Request $request, int $collaborator): ?Collaborator
    {
        return Collaborator::query()
            ->addressedTo($request->user())
            ->find($collaborator);
    }

    /**
     * 409, not 422 and not 404 (owner 2026-08-03 · §21 rule 2): the payload is fine and
     * the caller is the row's own subject — they know it exists, so nothing is protected
     * by pretending otherwise. What refuses is the invitation's STATE.
     */
    private function conflict(Collaborator $invitation, string $message): JsonResponse
    {
        return response()->json([
            'error_code' => ApiErrorCode::ConflictingState->value,
            'message' => $message,
            'status' => $invitation->status,
        ], 409);
    }
}
