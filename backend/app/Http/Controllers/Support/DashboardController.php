<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * [todo B8] Support stats dashboard.
 * Owner decision 2026-06-20: Support dashboard shows tickets solved vs waiting to review.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            // waiting to review = open or in-progress (still needs Support action)
            'waiting_review' => Ticket::whereIn('status', ['open', 'in_progress'])->count(),
            'open'           => Ticket::where('status', 'open')->count(),
            'in_progress'    => Ticket::where('status', 'in_progress')->count(),
            'waiting_user'   => Ticket::where('status', 'waiting_user')->count(),
            // solved = resolved or closed
            'solved'         => Ticket::whereIn('status', ['resolved', 'closed'])->count(),
            // unassigned & still actionable — needs someone to pick it up
            'unassigned'     => Ticket::whereNull('assigned_to_user_id')
                ->whereIn('status', ['open', 'in_progress'])->count(),
            'total'          => Ticket::count(),
        ]);
    }
}
