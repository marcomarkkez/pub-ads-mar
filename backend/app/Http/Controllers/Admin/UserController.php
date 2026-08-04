<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * §2 gives Admin ✏📝 over User/account: creating, editing (role included) and
 * deleting an account are all audit-logged (UC-31). Passwords never reach the
 * log — AuditLog::scrub() redacts them on the way in.
 */
class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::latest()->paginate(20);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:client,provider,admin,support,payments',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $user = DB::transaction(function () use ($request, $validated) {
            $user = User::create($validated);

            AuditLog::record(
                $request->user(),
                'create',
                $user,
                null,
                $user->getAttributes(),
                'admin created the account (§2 ✏)',
            );

            return $user;
        });

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|in:client,provider,admin,support,payments',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $user->fill($validated);

        DB::transaction(function () use ($request, $user) {
            AuditLog::recordChange($request->user(), $user, 'admin edited the account (§2 ✏)');
            $user->save();
        });

        return response()->json($user);
    }

    /*
     * There is deliberately NO destroy() here (owner 2026-08-03: "Borrado no, sólo el dueño
     * del account puede… el admin sólo puede quitar usuarios de sus roles, no eliminar").
     *
     * The method that used to live here called $user->delete(), and every root foreign key
     * still points at users with cascadeOnDelete — campaigns, spaces, bookings and the wallet
     * ledger went with it, silently, in one statement. Auditing that was never protection: the
     * log recorded a loss it could not undo. Removing the route is the protection; the FK
     * policy change that makes the loss impossible at all is tracked separately.
     */
}
