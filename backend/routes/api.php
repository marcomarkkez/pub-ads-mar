<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OversightController;
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
use App\Http\Controllers\Manager\UserController;
use App\Http\Controllers\Payments\DashboardController as PaymentsDashboardController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Payments\ProofReviewController;
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
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ── Client routes ───────────────────────────────────────
    Route::middleware('role:client')->prefix('client')->group(function () {
        // Campaigns
        Route::get('campaigns', [CampaignController::class, 'index'])->middleware('permission:campaigns,read');
        Route::post('campaigns', [CampaignController::class, 'store'])->middleware('permission:campaigns,create');
        Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->middleware('permission:campaigns,read');
        Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->middleware('permission:campaigns,update');
        Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->middleware('permission:campaigns,delete');

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

        // Bookings
        Route::get('bookings', [ClientBookingController::class, 'index'])->middleware('permission:bookings,read');
        Route::post('bookings', [ClientBookingController::class, 'store'])->middleware('permission:bookings,create');
        Route::get('bookings/{booking}', [ClientBookingController::class, 'show'])->middleware('permission:bookings,read');
        Route::put('bookings/{booking}', [ClientBookingController::class, 'update'])->middleware('permission:bookings,update');

        // Space search
        Route::get('spaces/search', [SpaceSearchController::class, 'search'])->middleware('permission:spaces,read');

        // Collaborators
        Route::get('campaigns/{campaign}/collaborators', [CollaboratorController::class, 'index'])->middleware('permission:collaborators,read');
        Route::post('campaigns/{campaign}/collaborators', [CollaboratorController::class, 'store'])->middleware('permission:collaborators,create');
        Route::delete('campaigns/{campaign}/collaborators/{collaborator}', [CollaboratorController::class, 'destroy'])->middleware('permission:collaborators,delete');

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
        Route::delete('spaces/{space}', [SpaceController::class, 'destroy'])->middleware('permission:spaces,delete');

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
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->middleware('permission:users,delete');

        // Permissions management (NO permission middleware — prevent lockout)
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('permissions/{role}', [PermissionController::class, 'show']);
        Route::put('permissions/{role}', [PermissionController::class, 'update']);
        Route::patch('permissions/{role}/{resource}', [PermissionController::class, 'updateResource']);

        // UC-28 · design.md §10/§12/§17 — ONE eagle-eye chat oversight (read-only,
        // incognito). Replaces the retired /oversight/{conversations,tickets} views.
        Route::get('oversight/chats', [OversightController::class, 'chats'])->middleware('permission:chats,read');
        Route::get('oversight/chats/{chat}', [OversightController::class, 'chat'])->middleware('permission:chats,read');

        // UC-28 · Admin's only chat write-power: reopen a closed chat for investigation.
        Route::post('chats/{chat}/reopen', [ChatController::class, 'reopen']);

        // System configurations (C12)
        Route::get('configurations', [ConfigurationController::class, 'index'])->middleware('permission:configurations,read');
        Route::put('configurations', [ConfigurationController::class, 'update'])->middleware('permission:configurations,update');
    });

    // ── Support routes ──────────────────────────────────────
    Route::middleware('role:support')->prefix('support')->group(function () {
        // [todo B8] Support stats dashboard (role-gated only — see note in admin block).
        Route::get('dashboard', [SupportDashboardController::class, 'index']);

        // design.md §10/§17: Support acts on chats via the shared /chats map
        // (join/flag/resolve/close). The old /tickets, /conversations/{c}/join and
        // /payments/{p}/flag-* routes are RETIRED into the ONE chat primitive.
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

        Route::get('proofs', [ProofReviewController::class, 'index'])->middleware('permission:proofs,read');
        Route::post('proofs/{proof}/approve', [ProofReviewController::class, 'approve'])->middleware('permission:proofs,update');
        Route::post('proofs/{proof}/reject', [ProofReviewController::class, 'reject'])->middleware('permission:proofs,update');
    });

    // ── Shared routes — Chats (any authenticated user) ──────────────
    // design.md §10/§17 — the ONE communication primitive. Nature + ACL DERIVED from
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
