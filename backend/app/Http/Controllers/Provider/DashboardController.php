<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
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
        $totalRevenue = (clone $bookings)->where('bookings.status', 'confirmed')
            ->join('payments', 'bookings.id', '=', 'payments.booking_id')
            ->where('payments.status', 'completed')
            ->sum('payments.amount');

        $unreadMessages = Conversation::where('provider_user_id', $user->id)
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
            'total_revenue' => $totalRevenue,
            'unread_messages' => $unreadMessages,
        ]);
    }
}
