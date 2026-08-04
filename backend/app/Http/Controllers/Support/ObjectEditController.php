<?php

namespace App\Http\Controllers\Support;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Collaborator;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * design.json §11 / §17 (UC-23) — Support edits any NON-money object, audited.
 *
 * `PUT /support/{spaces|ads|users|bookings|collaborators}/{id}`
 *
 * Support is near-admin on CONTENT and has zero authority over money (§1): it
 * can only FLAG a refund or a payout hold, which Payments then executes (§8,
 * UC-22 → UC-25/UC-26). Two lines follow from that, and this controller draws
 * both:
 *
 *  1. OBJECT level — payments, invoices and wallet entries have no route here
 *     at all. The absence IS the rule; there is nothing to bypass.
 *
 *  2. FIELD level — a non-money object can still carry a money-DETERMINING
 *     field (`bookings.total_price`, `spaces.price_per_day`). Letting Support
 *     rewrite those would move money through the back door, so every whitelist
 *     below excludes them and assertNonMoney() re-checks at the last moment.
 *     Support raises a flag for a price problem; it does not retype the price.
 *
 * Two more fields are deliberately NOT Support's to change:
 *   - `users.role` and `users.password` — identity and RBAC are Admin's (§1).
 *   - ownership/structure keys (`ads.adset_id`, `ads.space_id`, `*.user_id`) —
 *     the §21 ownership chain is structural, not an editable attribute.
 *
 * `collaborators.role` IS editable: §1 grants Support exactly that power.
 *
 * Every successful edit writes ONE audit row inside the SAME transaction as the
 * change (§12, UC-31), so an edit can never exist without its trail. A PUT that
 * changes nothing writes nothing and is not an error.
 */
class ObjectEditController extends Controller
{
    /**
     * Field names that decide how much money moves. Never editable here,
     * on any object — see the class docblock, point 2.
     */
    private const MONEY_FIELDS = [
        'price',
        'total_price',
        'amount',
        'price_per_day',
        'price_per_month',
        'pricing_unit',
        'balance',
        'currency',
    ];

    public function updateSpace(Request $request, Space $space): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:50',
            'description' => 'sometimes|nullable|string',
            'location_text' => 'sometimes|nullable|string|max:255',
            'width' => 'sometimes|nullable|numeric|min:0',
            'height' => 'sometimes|nullable|numeric|min:0',
            'calendar_keyword' => 'sometimes|nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        // UC-29 · §12 — an admin TAKEDOWN is not Support's to lift either. Support is
        // near-admin on content, but the three levels of §12 belong to whoever set
        // them: `taken_down_at` is admin's and has no route outside
        // Admin\ModerationController. Structurally Support could not clear it (it is
        // not fillable and not in this whitelist); this refusal exists so a Support
        // agent re-publishing a moderated listing is told why nothing happened
        // instead of reading a 200 that changed nothing visible.
        if ($space->isTakenDown() && ($validated['is_active'] ?? false) === true) {
            return response()->json([
                'error_code' => ApiErrorCode::ConflictingState->value,
                'message' => 'This listing is under an admin takedown (§12 · UC-29). Only an admin can restore it: POST /admin/spaces/' . $space->id . '/restore.',
                'taken_down_at' => $space->taken_down_at,
                'takedown_reason' => $space->takedown_reason,
            ], 409);
        }

        return $this->applyEdit($request, $space, $validated);
    }

    public function updateAd(Request $request, Ad $ad): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'media_type' => 'sometimes|in:image,video,audio',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'proof_deadline' => 'sometimes|nullable|date',
            'status' => 'sometimes|in:draft,pending_approval,approved,rejected,active,paused,completed,cancelled',
        ]);

        return $this->applyEdit($request, $ad, $validated);
    }

    public function updateBooking(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'status' => 'sometimes|in:pending,waiting_approval,confirmed,active,waiting_proof,completed,cancelled,rejected',
            'rejection_reason' => 'sometimes|nullable|string|max:500',
        ]);

        return $this->applyEdit($request, $booking, $validated);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        // No `role`, no `password`: identity and RBAC belong to Admin (§1).
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'company_name' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        return $this->applyEdit($request, $user, $validated);
    }

    public function updateCollaborator(Request $request, Collaborator $collaborator): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'sometimes|in:' . implode(',', Collaborator::ROLES),
            'status' => 'sometimes|in:pending,accepted,revoked',
        ]);

        return $this->applyEdit($request, $collaborator, $validated);
    }

    /**
     * The one write path: guard, fill, audit and save atomically.
     */
    private function applyEdit(Request $request, Model $target, array $validated): JsonResponse
    {
        $this->assertNonMoney($validated);

        $target->fill($validated);

        if (!$target->isDirty()) {
            // Nothing changed — no state change, so no audit entry to write.
            return response()->json(['data' => $target->fresh(), 'audited' => false]);
        }

        $entry = DB::transaction(function () use ($request, $target) {
            $entry = AuditLog::recordChange(
                $request->user(),
                $target,
                'support edit-any (UC-23) via ' . $request->path(),
            );

            $target->save();

            return $entry;
        });

        return response()->json([
            'data' => $target->fresh(),
            'audited' => true,
            'audit_id' => $entry?->id,
        ]);
    }

    /**
     * Last-moment restatement of the invariant. The whitelists above already
     * exclude these, so reaching this throw means someone widened a whitelist
     * without reading §1 — which is exactly when a guard earns its keep.
     */
    private function assertNonMoney(array $validated): void
    {
        $money = array_intersect(array_keys($validated), self::MONEY_FIELDS);

        if ($money !== []) {
            throw new RuntimeException(
                'Support has no money authority (design.json §1): refusing to edit '
                . implode(', ', $money) . '. Raise a flag for Payments instead.'
            );
        }
    }
}
