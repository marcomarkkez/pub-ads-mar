<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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

        $user = User::create($validated);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
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
            'permissions' => RolePermission::getCachedPermissions($user->role),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'permissions' => RolePermission::getCachedPermissions($user->role),
        ]);
    }
}
