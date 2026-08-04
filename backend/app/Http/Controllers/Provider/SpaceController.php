<?php

namespace App\Http\Controllers\Provider;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Space;
use App\Models\SystemConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $spaces = $request->user()->spaces()->with(['photos', 'availabilities'])->latest()->get();

        return response()->json($spaces);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'price_per_day' => 'nullable|numeric|min:0',
            'price_per_month' => 'nullable|numeric|min:0',
            'pricing_unit' => 'nullable|in:day,month,custom',
            'description' => 'nullable|string',
            'location_text' => 'nullable|string|max:255',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'ical_url' => 'nullable|url|max:500',
            'calendar_keyword' => 'nullable|string|max:255',
        ]);

        $space = $request->user()->spaces()->create($validated);

        return response()->json($space, 201);
    }

    public function show(Request $request, Space $space): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($space->load(['photos', 'availabilities', 'bookings.client', 'ads']));
    }

    public function update(Request $request, Space $space): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'nullable|string|max:255',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'price_per_day' => 'nullable|numeric|min:0',
            'price_per_month' => 'nullable|numeric|min:0',
            'pricing_unit' => 'nullable|in:day,month,custom',
            'description' => 'nullable|string',
            'location_text' => 'nullable|string|max:255',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'ical_url' => 'nullable|url|max:500',
            'calendar_keyword' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        // UC-29 · design.json §12 — the provider CANNOT self-reverse an admin takedown.
        //
        // Two things keep that true and both are needed. Structurally,
        // `taken_down_at` is not in Space::$fillable and appears in no provider
        // payload, so nothing here can clear it — publishing again only flips the
        // provider's OWN level (`is_active`), and the listing stays hidden because
        // Space::scopeBookable() requires all three levels to be clear. But silently
        // accepting a re-publish that changes nothing visible is its own bug: the
        // provider gets 200, sees "published", and the listing never appears. So the
        // attempt is refused out loud.
        //
        // 409 and not 403 (owner 2026-08-03): this IS the provider's own listing and
        // they do hold the permission — it is the listing's moderation STATE that
        // forbids the move. Everything else on the listing stays editable, including
        // pausing it further: level 1 remains theirs.
        if ($space->isTakenDown() && ($validated['is_active'] ?? false) === true) {
            return response()->json([
                'error_code' => ApiErrorCode::ConflictingState->value,
                'message' => 'This listing was taken down by platform moderation and cannot be re-published by its owner. '
                    . 'Contact Support; only an admin can restore it.',
                'taken_down_at' => $space->taken_down_at,
                'takedown_reason' => $space->takedown_reason,
            ], 409);
        }

        $space->update($validated);

        return response()->json($space);
    }

    /**
     * AD-delguard-08 · §12 · UC-37 — DELETE no longer deletes.
     *
     * Owner 2026-08-03: "No deben poder borrarse ads que tengan aún clientes usándolos,
     * se pueden despublicar y programar para borrado."
     *
     * This method used to be four lines that called `$space->delete()`. With
     * `bookings.space_id` on CASCADE, that one statement destroyed every booking on the
     * listing and, through `payments.booking_id`, every payment on those bookings — a
     * client's paid campaign could disappear because a provider tidied up their catalog.
     *
     * Now it UNPUBLISHES and programs a date. Nothing is destroyed here, by anyone: the
     * purge is a separate, manual command a human runs (`spaces:purge`), and it re-checks
     * the blockers at that moment rather than trusting this one.
     */
    public function destroy(Request $request, Space $space): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $blockers = $space->deletionBlockers();

        // 409 (owner 2026-08-03): the request is well-formed — it is the listing that is
        // in use. The blockers travel WITH the refusal, because "no" without a reason
        // leaves the provider guessing at which of their clients is holding it.
        if ($blockers !== []) {
            return response()->json([
                'error_code' => ApiErrorCode::ObjectInUse->value,
                'message' => 'This listing is still in use and cannot be scheduled for deletion.',
                'blockers' => $blockers,
            ], 409);
        }

        if ($space->isScheduledForDeletion()) {
            return response()->json([
                'error_code' => ApiErrorCode::AlreadyExists->value,
                'message' => 'A deletion is already programmed on this listing.',
                'delete_scheduled_at' => $space->delete_scheduled_at,
            ], 409);
        }

        $graceDays = (int) SystemConfiguration::get('deletion_grace_days', 30);

        DB::transaction(function () use ($request, $space, $graceDays) {
            $before = [
                'publication_status' => $space->publication_status,
                'delete_scheduled_at' => null,
            ];

            // Written by naming the attributes, not through $fillable: no client payload
            // can put a listing into — or out of — a programmed deletion by accident.
            $space->publication_status = Space::PUBLICATION_UNPUBLISHED;
            $space->delete_scheduled_at = now()->addDays($graceDays);
            $space->save();

            AuditLog::record(
                $request->user(),
                'schedule_deletion',
                $space,
                $before,
                ['publication_status' => $space->publication_status, 'delete_scheduled_at' => $space->delete_scheduled_at],
                'provider unpublished the listing and programmed its deletion in ' . $graceDays . ' days (§12 · UC-37)',
            );
        });

        return response()->json([
            'message' => 'Listing unpublished and programmed for deletion.',
            'space' => $space->fresh(),
        ]);
    }

    /**
     * The way back. A programmed deletion survives the provider's own pause/unpause lever
     * ON PURPOSE, so there has to be one explicit act that undoes it — otherwise the only
     * exit from `unpublished` would be the purge.
     */
    public function cancelDeletion(Request $request, Space $space): JsonResponse
    {
        if ($space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! $space->isScheduledForDeletion()) {
            return response()->json([
                'error_code' => ApiErrorCode::ObjectInUse->value,
                'message' => 'No deletion is programmed on this listing.',
            ], 409);
        }

        DB::transaction(function () use ($request, $space) {
            $before = [
                'publication_status' => $space->publication_status,
                'delete_scheduled_at' => $space->delete_scheduled_at,
            ];

            // Back to whatever the provider's own lever says — NOT unconditionally to
            // `published`. A listing they had paused before scheduling stays paused.
            $space->delete_scheduled_at = null;
            $space->publication_status = $space->is_active
                ? Space::PUBLICATION_PUBLISHED
                : Space::PUBLICATION_PAUSED;
            $space->save();

            AuditLog::record(
                $request->user(),
                'cancel_deletion',
                $space,
                $before,
                ['publication_status' => $space->publication_status, 'delete_scheduled_at' => null],
                'provider cancelled the programmed deletion (§12 · UC-37)',
            );
        });

        return response()->json([
            'message' => 'Programmed deletion cancelled.',
            'space' => $space->fresh(),
        ]);
    }
}
