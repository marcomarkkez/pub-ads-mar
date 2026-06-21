<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Payment;
use App\Models\Space;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * [todo B8] Admin stats dashboard — "stats about everything" (owner decision 2026-06-20).
 * Eagle-eye platform-wide counts across users, spaces, campaigns, tickets and money.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usersByRole = User::query()
            ->selectRaw('role, count(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        return response()->json([
            'users_total'      => User::count(),
            'users_by_role'    => $usersByRole,
            'active_spaces'    => Space::where('is_active', true)->count(),
            'total_spaces'     => Space::count(),
            'active_campaigns' => Campaign::where('status', 'active')->count(),
            'total_campaigns'  => Campaign::count(),
            'open_tickets'     => Ticket::whereIn('status', ['open', 'in_progress'])->count(),
            'payments_held'    => ['count' => Payment::where('status', 'held')->count(), 'amount' => (float) Payment::where('status', 'held')->sum('amount')],
            'revenue_paid'     => ['count' => Payment::whereIn('status', ['completed', 'released'])->count(), 'amount' => (float) Payment::whereIn('status', ['completed', 'released'])->sum('amount')],
            'refunded'         => ['count' => Payment::where('status', 'refunded')->count(), 'amount' => (float) Payment::where('status', 'refunded')->sum('amount')],
        ]);
    }
}
