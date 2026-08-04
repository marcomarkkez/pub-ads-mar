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
        if ($existing !== null) {
            return response()->json([
                'error_code' => ApiErrorCode::AlreadyExists->value,
                'message' => 'This email already collaborates on your account.',
                'collaborator' => $existing,
            ], 409);
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

    public function destroy(Request $request, int $collaborator): JsonResponse
    {
        $target = $this->scope($request)->find($collaborator);

        if ($target === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        // Revoked, not deleted: the grant is the record of who had access and when
        // it was taken away. A deleted row answers no question later.
        DB::transaction(function () use ($request, $target) {
            $target->fill(['status' => 'revoked']);

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
     * The ONLY scope this controller can ever address: the caller's own account.
     */
    private function scope(Request $request): Builder
    {
        return Collaborator::query()->where('account_id', $request->user()->account_id);
    }
}
