<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\OversightController;
use App\Http\Controllers\Admin\PayoutInterventionController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Client\AdController;
use App\Http\Controllers\Client\AdsetController;
use App\Http\Controllers\Client\BacklogController;
use App\Http\Controllers\Client\BookingController as ClientBookingController;
use App\Http\Controllers\Client\CampaignController;
use App\Http\Controllers\Client\CollaboratorController;
use App\Http\Controllers\Client\InvoiceController;
use App\Http\Controllers\Client\ProofFlagController;
use App\Http\Controllers\Client\SpaceSearchController;
use App\Http\Controllers\Client\WalletController;
use App\Http\Controllers\CollaborationController;
use App\Http\Controllers\Payments\DashboardController as PaymentsDashboardController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Provider\BookingController as ProviderBookingController;
use App\Http\Controllers\Provider\DashboardController;
use App\Http\Controllers\Provider\ProofController;
use App\Http\Controllers\Provider\SpaceAvailabilityController;
use App\Http\Controllers\Provider\SpaceController;
use App\Http\Controllers\Provider\SpacePhotoController;
use App\Http\Controllers\Shared\ChatController;
use App\Http\Controllers\Shared\ChatFlagController;
use App\Http\Controllers\Shared\ChatObjectController;
use App\Http\Controllers\Support\DashboardController as SupportDashboardController;
use App\Http\Controllers\Support\ObjectEditController as SupportObjectEditController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ── The caller's own account (design.json §3) ───────────────────
    // Owner 2026-08-03: only the account OWNER deletes an account, and only their
    // own — hence no {id} here and no delete verb anywhere else in the API. Staff
    // roles have no account (accounts.type is client|provider), so they are not
    // in the role list. DELETE takes `confirm_proof_loss` — see AccountController.
    //
    // AD-delguard-09 · UC-37: DELETE no longer always deletes. With an open dispute it
    // refuses (409, whatever the confirmation says); with other objects in use it
    // unpublishes and programs a date, which `cancel-deletion` undoes and the manual
    // `php artisan accounts:purge` eventually executes.
    Route::middleware('role:client,provider')->group(function () {
        Route::get('account', [AccountController::class, 'show']);
        Route::delete('account', [AccountController::class, 'destroy']);
        Route::post('account/cancel-deletion', [AccountController::class, 'cancelDeletion']);

        // ── Invitations addressed to ME (§3 UC-19/UC-20 · WALK-6 step 3) ────────────
        // NOT under the /client prefix, and that is the point: the caller is answering
        // an invitation into someone ELSE's account, so nothing here can be scoped by
        // the caller's own account_id. The bound is Collaborator::addressedTo() — "this
        // invitation names me". Provider-side subroles exist too (installator/sales/
        // supervisor), so this is role:client,provider, not role:client.
        //
        // Deliberately NO `permission:` middleware. The `collaborators` resource in
        // RolePermission is the account OWNER's power to invite/list/revoke on their own
        // account — provider has none of it, and gating these routes on it would make a
        // provider unable to answer an invitation they were legitimately sent. Answering
        // an invitation addressed to you is not an account power; the row itself is the
        // authorization, which is exactly what the scope checks.
        Route::get('collaborations', [CollaborationController::class, 'index']);
        Route::post('collaborations/{collaborator}/accept', [CollaborationController::class, 'accept']);
        Route::post('collaborations/{collaborator}/decline', [CollaborationController::class, 'decline']);
    });

    // ── Client routes ───────────────────────────────────────
    Route::middleware('role:client')->prefix('client')->group(function () {
        // Campaigns
        Route::get('campaigns', [CampaignController::class, 'index'])->middleware('permission:campaigns,read');
        Route::post('campaigns', [CampaignController::class, 'store'])->middleware('permission:campaigns,create');
        Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->middleware('permission:campaigns,read');
        Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('permission:campaigns,update');
        Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->middleware('permission:campaigns,delete');

        // Adsets + Ads — design.json §21 (UC-43): nested bindings are ALWAYS scoped, so
        // a child is resolved THROUGH its parent and a foreign id 404s at the router.
        // The controllers re-check each link explicitly on top of this.
        Route::scopeBindings()->group(function () {
            // Adsets
            Route::get('campaigns/{campaign}/adsets', [AdsetController::class, 'index'])->middleware('permission:adsets,read');
            Route::post('campaigns/{campaign}/adsets', [AdsetController::class, 'store'])->middleware('permission:adsets,create');
            Route::get('campaigns/{campaign}/adsets/{adset}', [AdsetController::class, 'show'])->middleware('permission:adsets,read');
            Route::put('campaigns/{campaign}/adsets/{adset}', [AdsetController::class, 'update'])->middleware('permission:adsets,update');
            Route::delete('campaigns/{campaign}/adsets/{adset}', [AdsetController::class, 'destroy'])->middleware('permission:adsets,delete');

            // Ads
            Route::get('campaigns/{campaign}/adsets/{adset}/ads', [AdController::class, 'index'])->middleware('permission:ads,read');
            Route::post('campaigns/{campaign}/adsets/{adset}/ads', [AdController::class, 'store'])->middleware('permission:ads,create');
            Route::get('campaigns/{campaign}/adsets/{adset}/ads/{ad}', [AdController::class, 'show'])->middleware('permission:ads,read');
            Route::put('campaigns/{campaign}/adsets/{adset}/ads/{ad}', [AdController::class, 'update'])->middleware('permission:ads,update');
            Route::delete('campaigns/{campaign}/adsets/{adset}/ads/{ad}', [AdController::class, 'destroy'])->middleware('permission:ads,delete');
        });

        // Bookings
        Route::get('bookings', [ClientBookingController::class, 'index'])->middleware('permission:bookings,read');
        Route::post('bookings', [ClientBookingController::class, 'store'])->middleware('permission:bookings,create');
        Route::get('bookings/{booking}', [ClientBookingController::class, 'show'])->middleware('permission:bookings,read');
        Route::put('bookings/{booking}', [ClientBookingController::class, 'update'])->middleware('permission:bookings,update');

        // Space search
        Route::get('spaces/search', [SpaceSearchController::class, 'search'])->middleware('permission:spaces,read');

        // Collaborators — ACCOUNT-scoped (§3, AC-collab-04). The campaign-nested
        // routes are RETIRED: §3 says a collaborator "points to account_id (never
        // a campaign or space)", and nesting them under a campaign is what let the
        // same person be invited once per campaign under unique(campaign_id,email).
        Route::get('collaborators', [CollaboratorController::class, 'index'])->middleware('permission:collaborators,read');
        Route::post('collaborators', [CollaboratorController::class, 'store'])->middleware('permission:collaborators,create');
        Route::delete('collaborators/{collaborator}', [CollaboratorController::class, 'destroy'])->middleware('permission:collaborators,delete');

        // Invoices
        Route::get('invoices', [InvoiceController::class, 'index'])->middleware('permission:invoices,read');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoices,read');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->middleware('permission:invoices,read');

        // Campaign backlog / orphan ads (C03)
        Route::post('campaigns/{campaign}/backlog', [BacklogController::class, 'addBacklog'])->middleware('permission:campaigns,update');
        Route::get('campaigns/{campaign}/orphans', [BacklogController::class, 'listOrphans'])->middleware('permission:campaigns,read');
        Route::post('campaigns/{campaign}/adsets/move', [BacklogController::class, 'moveToAdset'])->middleware('permission:campaigns,update');

        // Client proof review (B9): accept -> payout releasable; reject -> payout held + ticket.
        Route::post('proofs/{proof}/accept', [ProofFlagController::class, 'accept'])->middleware('permission:proofs,read');
        Route::post('proofs/{proof}/reject', [ProofFlagController::class, 'reject'])->middleware('permission:proofs,read');
        // Back-compat alias (now == reject).
        Route::post('proofs/{proof}/flag-mismatch', [ProofFlagController::class, 'flagMismatch'])->middleware('permission:proofs,read');

        // Wallet (C10)
        Route::get('wallet', [WalletController::class, 'index'])->middleware('permission:bookings,read');
    });

    // ── Provider routes ─────────────────────────────────────
    Route::middleware('role:provider')->prefix('provider')->group(function () {
        // Spaces
        Route::get('spaces', [SpaceController::class, 'index'])->middleware('permission:spaces,read');
        Route::post('spaces', [SpaceController::class, 'store'])->middleware('permission:spaces,create');
        Route::get('spaces/{space}', [SpaceController::class, 'show'])->middleware('permission:spaces,read');
        Route::put('spaces/{space}', [SpaceController::class, 'update'])->middleware('permission:spaces,update');
        // UC-37 — DELETE unpublishes and PROGRAMS a deletion; it does not destroy. The
        // purge is `php artisan spaces:purge`, run by a human.
        Route::delete('spaces/{space}', [SpaceController::class, 'destroy'])->middleware('permission:spaces,delete');
        Route::post('spaces/{space}/cancel-deletion', [SpaceController::class, 'cancelDeletion'])->middleware('permission:spaces,update');

        // Space photos
        Route::post('spaces/{space}/photos', [SpacePhotoController::class, 'store'])->middleware('permission:space_photos,create');
        Route::delete('spaces/{space}/photos/{photo}', [SpacePhotoController::class, 'destroy'])->middleware('permission:space_photos,delete');

        // Space availabilities
        Route::get('spaces/{space}/availabilities', [SpaceAvailabilityController::class, 'index'])->middleware('permission:space_availabilities,read');
        Route::post('spaces/{space}/availabilities', [SpaceAvailabilityController::class, 'store'])->middleware('permission:space_availabilities,create');
        Route::delete('spaces/{space}/availabilities/{availability}', [SpaceAvailabilityController::class, 'destroy'])->middleware('permission:space_availabilities,delete');
        Route::post('spaces/{space}/sync-ical', [SpaceAvailabilityController::class, 'syncIcal'])->middleware('permission:space_availabilities,create');
        Route::post('spaces/{space}/import-ical', [SpaceAvailabilityController::class, 'importIcal'])->middleware('permission:space_availabilities,create');

        // Bookings
        Route::get('bookings', [ProviderBookingController::class, 'index'])->middleware('permission:bookings,read');
        Route::put('bookings/{booking}', [ProviderBookingController::class, 'update'])->middleware('permission:bookings,update');

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard,read');

        // Proofs
        Route::get('proofs', [ProofController::class, 'index'])->middleware('permission:proofs,read');
        Route::post('proofs', [ProofController::class, 'store'])->middleware('permission:proofs,create');
        Route::get('proofs/{proof}', [ProofController::class, 'show'])->middleware('permission:proofs,read');
    });

    // ── Admin routes ────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // [todo B8] Admin stats dashboard. Role-gated only (admin has dashboard:read, but new
        // support/payments dashboards omit permission mw to avoid an RBAC reseed on the running DB).
        Route::get('dashboard', [AdminDashboardController::class, 'index']);

        // Users CRM
        Route::get('users', [AdminUserController::class, 'index'])->middleware('permission:users,read');
        Route::post('users', [AdminUserController::class, 'store'])->middleware('permission:users,create');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->middleware('permission:users,read');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->middleware('permission:users,update');
        // No DELETE users/{user} (owner 2026-08-03). An admin takes a person off their roles;
        // only the account owner deletes an account. See Admin\UserController for why.

        // Permissions management (NO permission middleware — prevent lockout)
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('permissions/{role}', [PermissionController::class, 'show']);
        Route::put('permissions/{role}', [PermissionController::class, 'update']);
        Route::patch('permissions/{role}/{resource}', [PermissionController::class, 'updateResource']);

        // UC-28 · design.json §10/§12/§17 — ONE eagle-eye chat oversight (read-only,
        // incognito). Replaces the retired /oversight/{conversations,tickets} views.
        Route::get('oversight/chats', [OversightController::class, 'chats'])->middleware('permission:chats,read');
        Route::get('oversight/chats/{chat}', [OversightController::class, 'chat'])->middleware('permission:chats,read');

        // UC-28 · Admin's only chat write-power: reopen a closed chat for investigation.
        Route::post('chats/{chat}/reopen', [ChatController::class, 'reopen']);

        // UC-31 · design.json §12/§17 — the immutable audit log, Admin read-only.
        // There is no write verb here by design: entries are written by the actions
        // themselves, in their own transaction.
        Route::get('audit', [AuditController::class, 'index'])->middleware('permission:audit,read');

        // UC-29 · design.json §12/§17 — ADMIN MODERATION. The only writes Admin has,
        // and each one is audited (slugs: takedown / restore / freeze / unfreeze).
        // §12's three levels stay distinct: the provider's own pause is
        // PUT /provider/spaces/{s} {is_active}, which cannot clear a takedown (409).
        Route::get('moderation', [ModerationController::class, 'index'])->middleware('permission:moderation,read');
        Route::post('spaces/{space}/takedown', [ModerationController::class, 'takedown'])->middleware('permission:moderation,update');
        Route::post('spaces/{space}/restore', [ModerationController::class, 'restore'])->middleware('permission:moderation,update');
        Route::post('providers/{user}/freeze', [ModerationController::class, 'freeze'])->middleware('permission:moderation,update');
        Route::post('providers/{user}/unfreeze', [ModerationController::class, 'unfreeze'])->middleware('permission:moderation,update');

        // UC-32 · design.json §8 — the payout-stop window and the clawback. Admin has
        // no other money power (§12: "no money-execution role beyond the payout-stop
        // window"); the clawback is separately gated on moderation.refund because it
        // is the one action here that takes money BACK from a provider already paid.
        Route::get('payouts', [PayoutInterventionController::class, 'index'])->middleware('permission:moderation,read');
        Route::post('payments/{payment}/payout/stop', [PayoutInterventionController::class, 'stop'])->middleware('permission:moderation,update');
        Route::post('payments/{payment}/clawback', [PayoutInterventionController::class, 'clawback'])->middleware('permission:moderation,refund');

        // System configurations (C12)
        Route::get('configurations', [ConfigurationController::class, 'index'])->middleware('permission:configurations,read');
        Route::put('configurations', [ConfigurationController::class, 'update'])->middleware('permission:configurations,update');
    });

    // ── Support routes ──────────────────────────────────────
    Route::middleware('role:support')->prefix('support')->group(function () {
        // [todo B8] Support stats dashboard (role-gated only — see note in admin block).
        Route::get('dashboard', [SupportDashboardController::class, 'index']);

        // design.json §10/§17: Support acts on chats via the shared /chats map
        // (join/flag/resolve/close). The old /tickets, /conversations/{c}/join and
        // /payments/{p}/flag-* routes are RETIRED into the ONE chat primitive.

        // UC-23 · design.json §11/§17 — edit-any-NON-money object, audited.
        // Money objects (payments, invoices, wallet entries) have NO route here:
        // Support flags, Payments executes (§8). Money-determining FIELDS are
        // excluded inside the controller.
        Route::put('spaces/{space}', [SupportObjectEditController::class, 'updateSpace'])->middleware('permission:spaces,update');
        Route::put('ads/{ad}', [SupportObjectEditController::class, 'updateAd'])->middleware('permission:ads,update');
        Route::put('bookings/{booking}', [SupportObjectEditController::class, 'updateBooking'])->middleware('permission:bookings,update');
        Route::put('users/{user}', [SupportObjectEditController::class, 'updateUser'])->middleware('permission:users,update');
        Route::put('collaborators/{collaborator}', [SupportObjectEditController::class, 'updateCollaborator'])->middleware('permission:collaborators,update');
    });

    // ── Payments routes ─────────────────────────────────────
    Route::middleware('role:payments')->prefix('payments')->group(function () {
        // [todo B8] Payments stats dashboard (role-gated only — see note in admin block).
        Route::get('dashboard', [PaymentsDashboardController::class, 'index']);

        Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments,read');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->middleware('permission:payments,read');
        Route::post('payments/{payment}/approve', [PaymentController::class, 'approve'])->middleware('permission:payments,update');
        Route::post('payments/{payment}/reject', [PaymentController::class, 'reject'])->middleware('permission:payments,update');

        // Refunds + payouts (C10)
        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])->middleware('permission:payments,refund');
        Route::post('payments/{payment}/payout/release', [PaymentController::class, 'releasePayout'])->middleware('permission:payments,update');
        Route::post('payments/{payment}/payout/hold', [PaymentController::class, 'holdPayout'])->middleware('permission:payments,update');

        // B9 · design.json §7/§17 — Payments does NOT review proof CONTENT. The
        // proof verdict is the CLIENT's (POST /client/proofs/{proof}/accept|reject,
        // ProofFlagController); Payments only reacts to it with money. The old
        // GET /payments/proofs + approve/reject lived here in violation of that
        // and are REMOVED (2026-08-02), along with the payments.proofs.update
        // permission. Payments keeps proofs.read — §2 gives it 👁, not ✏.
    });

    // ── Shared routes — Chats (any authenticated user) ──────────────
    // design.json §10/§17 — the ONE communication primitive. Nature + ACL DERIVED from
    // participants+objects (never a `type` column, never a flag). Admin never posts
    // here (read-only/incognito via /admin/oversight/chats — R1); admin holds only
    // chats.read, so the create-gated mutations 403 for admin at the middleware.
    Route::get('chats', [ChatController::class, 'index'])->middleware('permission:chats,read');                       // UC-8/UC-9/UC-27
    Route::post('chats', [ChatController::class, 'store'])->middleware('permission:chats,create');                    // UC-8/UC-9
    Route::get('chats/{chat}', [ChatController::class, 'show'])->middleware('permission:chats,read');                 // UC-8/UC-9
    Route::post('chats/{chat}/messages', [ChatController::class, 'postMessage'])->middleware('permission:chats,create'); // UC-8/UC-9
    Route::post('chats/{chat}/objects', [ChatObjectController::class, 'attach'])->middleware('permission:chats,create'); // UC-8 (R2)
    Route::delete('chats/{chat}/objects/{object}', [ChatObjectController::class, 'detach'])->middleware('permission:chats,create');
    Route::post('chats/{chat}/flags', [ChatFlagController::class, 'store'])->middleware('permission:chats,create');   // UC-22
    Route::post('chats/{chat}/join', [ChatController::class, 'join'])->middleware('role:support');                    // UC-21
    Route::post('chats/{chat}/resolve', [ChatController::class, 'resolve'])->middleware('permission:chats,create');   // UC-22/UC-23
    Route::post('chats/{chat}/close', [ChatController::class, 'close'])->middleware('permission:chats,create');       // UC-8/UC-9
    // (reopen is admin-only — defined in the /admin block above.)
});
