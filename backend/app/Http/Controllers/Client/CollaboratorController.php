<?php

namespace App\Http\Controllers\Client;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Collaborator;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * design.json §3 (UC-19, AC-collab-03/04) — the client account's Collaborators
 * screen: `GET|POST|DELETE /client/collaborators`.
 *
 * These routes used to be nested under a campaign
 * (`/client/campaigns/{campaign}/collaborators`), which encoded the exact thing
 * §3 forbids: "a collaborator points to account_id (never a campaign or space)".
 * The nesting was not just wrong on paper — with `unique(campaign_id, email)` the
 * same person could be invited once per campaign, so one human became N grants
 * that N separate revokes were needed to undo, silently.
 *
 * Authorization is now the account itself: the caller's own account is the only
 * scope that exists here, so there is no id to confuse with someone else's and
 * no ownership chain to walk. A collaborator id from another account is 404, not
 * 403 (§21 rule 2 — a 403 would confirm the row exists).
 */
class CollaboratorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->scope($request)->with('user')->latest()->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $account = $request->user()->account;

        if ($account === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            // §3 (owner 2026-07-17) — installator is PROVIDER-side only. Accepting it here
            // created a collaborator that Chat::CLIENT_CHAT_SUBROLES then refuses, so the
            // person could see campaigns but never open a chat, with nothing explaining why.
            'role' => 'required|in:' . implode(',', Collaborator::CLIENT_ROLES),
        ]);

        $existing = $account->collaborators()->where('email', $validated['email'])->first();

        // 409, not 422 (owner 2026-08-03): the payload is well-formed and the email
        // is a real one — it is the ACCOUNT's state that refuses, because this
        // person already holds a grant on it. Re-inviting them used to look like a
        // success and quietly created a second grant; say so instead.
        if ($existing !== null && ! in_array($existing->status, Collaborator::REINVITABLE_STATUSES, true)) {
            return response()->json([
                'error_code' => ApiErrorCode::AlreadyExists->value,
                'message' => 'This email already collaborates on your account.',
                'collaborator' => $existing,
            ], 409);
        }

        // …but a DECLINED or REVOKED grant is a closed episode, not an occupied slot, and
        // the owner is allowed to open a new one. `unique(account_id, email)` means the
        // row IS the person, so a re-invitation has to reuse it rather than insert beside
        // it. Without this branch a single "no thanks" would be permanent: the invitee
        // cannot re-open their own invitation (that is the owner's move by design) and the
        // owner would hit ALREADY_EXISTS forever — a decline would silently work as a ban
        // nobody chose. The subrole is re-read from this payload: it is a NEW offer.
        if ($existing !== null) {
            return $this->reinvite($request, $existing, $validated);
        }

        $collaborator = DB::transaction(function () use ($request, $account, $validated) {
            $collaborator = $account->collaborators()->create([
                'invited_by_user_id' => $request->user()->id,
                'user_id' => User::where('email', $validated['email'])->value('id'),
                'email' => $validated['email'],
                'role' => $validated['role'],
            ]);

            AuditLog::record(
                $request->user(),
                'invite',
                $collaborator,
                null,
                $collaborator->getAttributes(),
                'account owner invited a collaborator (§3 UC-19)',
            );

            return $collaborator;
        });

        return response()->json($collaborator, 201);
    }

    /**
     * Re-open a grant the owner or the invitee had closed. Same row (the unique key
     * leaves no choice), same audit action as a first invite — from the account's point
     * of view this IS an invitation, and the `before` half of the audit entry already
     * says which ending it is reversing.
     *
     * 201 like a fresh invite, because that is what the caller asked for and got; the
     * response body carries the row so a client that cares can see the id is not new.
     */
    private function reinvite(Request $request, Collaborator $collaborator, array $validated): JsonResponse
    {
        DB::transaction(function () use ($request, $collaborator, $validated): void {
            $collaborator->fill([
                'invited_by_user_id' => $request->user()->id,
                // Re-resolved: the person may have registered since the first invitation.
                'user_id' => User::where('email', $validated['email'])->value('id'),
                'role' => $validated['role'],
                'status' => Collaborator::STATUS_PENDING,
            ]);

            AuditLog::recordChange(
                $request->user(),
                $collaborator,
                'account owner re-invited a previously ' . $collaborator->getOriginal('status') . ' collaborator (§3 UC-19)',
                'invite',
            );

            $collaborator->save();
        });

        return response()->json($collaborator, 201);
    }

    public function destroy(Request $request, int $collaborator): JsonResponse
    {
        $target = $this->scope($request)->find($collaborator);

        if ($target === null) {
            // Not on my account — so before the blanket 404, ask the one question the
            // 404 would answer wrongly: is this row the caller's OWN grant into someone
            // else's account, i.e. an attempt to walk out of a collaboration?
            if ($refusal = $this->refuseSelfUnlink($request, $collaborator)) {
                return $refusal;
            }

            return response()->json(['message' => 'Not found.'], 404);
        }

        // Revoked, not deleted: the grant is the record of who had access and when
        // it was taken away. A deleted row answers no question later.
        DB::transaction(function () use ($request, $target) {
            $target->fill(['status' => Collaborator::STATUS_REVOKED]);

            AuditLog::recordChange(
                $request->user(),
                $target,
                'account owner revoked a collaborator (§3 UC-19)',
                'revoke',
            );

            $target->save();
        });

        return response()->json(['message' => 'Collaborator revoked.']);
    }

    /**
     * §3 (owner ruling 2026-08-04) — a collaboration cannot be left by the collaborator:
     *
     *   "un colaborador aunque es registrado, estaría bajo la cuenta del dueño original,
     *    en un futuro veremos formas de desvincular pero eso evitará que haya algún
     *    fraude, así que por el momento no hay formas de desvincular la cuenta de
     *    colaboración si alguien la agrega excepto que hablen con soporte (no hay aún esa
     *    capacidad para soporte en la plataforma, eso es un tema para después)."
     *
     * The reason is ANTI-FRAUD, and it is worth stating so nobody relaxes this as a UX
     * papercut. The grant is the audit trail of who was operating an account — uploading
     * proofs, moving bookings, talking to the other side in chat. If the collaborator can
     * revoke themselves, the person best placed to want the trail gone is the one holding
     * the button, and they can push it the minute a dispute opens. Removal therefore stays
     * with the account OWNER (who did not do the acting) and, eventually, with Support —
     * a capability the platform does not have yet, which the message says out loud instead
     * of promising a route that would 404.
     *
     * 409 AND NOT 404 — deliberately, and against the local habit. §21 rule 2 / BR-3 says
     * a broken ownership chain answers 404 because a 403 would confirm the row exists.
     * That reasoning does not reach here: the caller IS the person named on the row, they
     * were invited to it, they see it in their own session. Nothing is disclosed by
     * admitting it exists, and a 404 would be an outright lie that reads as a bug ("my
     * collaboration vanished?") instead of a rule. The refusal is about STATE, which is
     * exactly what 409 is for in this codebase (owner 2026-08-03). Do not "fix" this to
     * 404 for consistency — the two situations are different questions.
     *
     * "Is this row mine" is `Collaborator::addressedTo()` — the same bound the
     * /collaborations endpoints answer invitations through, not a second copy of it: the
     * two questions ("may I answer this invitation" and "is this the row I am trying to
     * walk out of") are the same question, and two implementations of it would drift.
     *
     * The message splits on status because the answer genuinely differs. A PENDING
     * invitation was never accepted, so BR-15 does not reach it and there IS a door: the
     * person may decline it (see CollaborationController::decline for why that is not a
     * contradiction). Sending them to Support for that would be a false statement in an
     * error message, which is worse than no message. From `accepted` onwards, Support is
     * the only truthful answer — and today not even that, which the text says out loud
     * rather than promising a route that would 404.
     */
    private function refuseSelfUnlink(Request $request, int $collaborator): ?JsonResponse
    {
        $row = Collaborator::query()->addressedTo($request->user())->find($collaborator);

        if ($row === null) {
            return null;
        }

        $message = $row->status === Collaborator::STATUS_PENDING
            ? 'This endpoint revokes collaborators on YOUR account; this invitation is one you received. '
                . 'An invitation you have not accepted can be turned down at POST /api/collaborations/'
                . $row->id . '/decline.'
            : 'A collaboration cannot be left by the collaborator. Only the owner of the account '
                . 'can revoke this access; anything else has to go through Support, and that capability does '
                . 'not exist on the platform yet.';

        return response()->json([
            'error_code' => ApiErrorCode::ConflictingState->value,
            'message' => $message,
            'account_id' => $row->account_id,
        ], 409);
    }

    /**
     * The ONLY scope this controller can ever address: the caller's own account.
     *
     * This is also why the owner keeps working normally in destroy(): a row on MY account
     * is found here and never reaches the self-unlink guard, including the odd case of an
     * owner who invited their own email.
     */
    private function scope(Request $request): Builder
    {
        return Collaborator::query()->where('account_id', $request->user()->account_id);
    }
}
