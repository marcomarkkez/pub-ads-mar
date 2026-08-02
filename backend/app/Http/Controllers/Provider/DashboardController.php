<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalSpaces = $user->spaces()->count();
        $activeSpaces = $user->spaces()->where('is_active', true)->count();

        $bookings = Booking::whereHas('space', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        $pendingBookings = (clone $bookings)->where('status', 'pending')->count();
        $confirmedBookings = (clone $bookings)->where('status', 'confirmed')->count();

        // [todo B8] Money buckets for the provider's spaces: paid / held / refunded.
        $payQuery = fn (array $statuses) => Booking::whereHas('space', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
            ->join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->whereIn('payments.status', $statuses)
            ->sum('payments.amount');

        $revenuePaid = (float) $payQuery(['completed', 'released']);
        $revenueHeld = (float) $payQuery(['held']);
        $revenueRefunded = (float) $payQuery(['refunded']);

        // UC-8 · design.json §10 — unread across the provider's chats.
        $unreadMessages = Chat::where('provider_user_id', $user->id)
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('sender_user_id', '!=', $user->id)->where('is_read', false);
            }])
            ->get()
            ->sum('unread_count');

        return response()->json([
            'total_spaces' => $totalSpaces,
            'active_spaces' => $activeSpaces,
            'pending_bookings' => $pendingBookings,
            'confirmed_bookings' => $confirmedBookings,
            'total_revenue' => $revenuePaid, // kept for back-compat
            'revenue_paid' => $revenuePaid,
            'revenue_held' => $revenueHeld,
            'revenue_refunded' => $revenueRefunded,
            'unread_messages' => $unreadMessages,
        ]);
    }
}
