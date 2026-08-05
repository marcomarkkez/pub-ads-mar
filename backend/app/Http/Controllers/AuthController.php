<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * §3 (AC-accounts-01) — "Every user gets an account at registration", and a
     * client/provider who registers is by definition "la persona que abre la cuenta",
     * i.e. an owner from their very first request (owner 2026-08-04).
     *
     * The account row itself is born in the `User::created` hook (see User::booted and
     * Account::provisionFor) rather than here, because registration is not the only door:
     * the admin user CRUD, the seeders and every factory in the suite create users too,
     * and an invariant that holds only where somebody remembered to write it is not an
     * invariant. What this method owes the hook is ATOMICITY: the account is a second
     * INSERT plus an UPDATE on the user, and if that half fails on its own the request
     * still answers 201 with a user whose `account_id` is NULL — permanently not an owner,
     * with no Collaborators tab and no way back except a manual DB fix. The transaction
     * makes "a registered client/provider without an account" a state that cannot exist.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:client,provider',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        [$user, $token] = DB::transaction(function () use ($validated): array {
            $user = User::create($validated);

            return [$user, $user->createToken('auth-token')->plainTextToken];
        });

        return response()->json([
            'user' => $user,
            'token' => $token,
            ...$user->accountContext(),
            'permissions' => RolePermission::getCachedPermissions($user->role),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error_code' => ApiErrorCode::AuthInvalidCredentials->value,
                'message' => 'The email or password is incorrect.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'error_code' => ApiErrorCode::AuthAccountDeactivated->value,
                'message' => 'This account has been deactivated.',
            ], 403);
        }

        // UC-29 · design.json §12 — a freeze "revokes active Sanctum tokens" immediately.
        // Revoking them and then handing out a fresh one at the next login would make the
        // revocation theatre, so the freeze also closes the door it just pushed people
        // through. Distinct from `is_active` on purpose: deactivation is the admin CRM's
        // switch, a freeze is moderation with money consequences (§12) and the client
        // needs to be able to tell the two apart.
        if ($user->isFrozen()) {
            return response()->json([
                'error_code' => ApiErrorCode::AuthAccountFrozen->value,
                'message' => 'This account is frozen by platform moderation. Contact Support.',
                'frozen_at' => $user->frozen_at,
                'reason' => $user->freeze_reason,
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            ...$user->accountContext(),
            'permissions' => RolePermission::getCachedPermissions($user->role),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * §3 (owner 2026-08-04) — "un dueño es la persona que abre la cuenta y puede añadir
     * colaboradores, así de simple." The definition itself lives in
     * User::accountContext(); this endpoint is now just one of its three readers.
     *
     * Until §3 gave us `is_owner`, the frontend had no way to tell an account owner from
     * one of their collaborators — both are `client`-role users — and the Collaborators
     * screen approximated it with the `collaborators.create` permission. An approximation
     * is a guess, and a guess in an authorization-shaped decision eventually guesses wrong.
     *
     * `/me` stays the canonical answer on page load; `/login` and `/register` return the
     * SAME three keys so a session that has just started already knows which menu it is.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            ...$user->accountContext(),
            'permissions' => RolePermission::getCachedPermissions($user->role),
        ]);
    }
}
