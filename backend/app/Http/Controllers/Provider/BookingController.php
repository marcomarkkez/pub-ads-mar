<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::whereHas('space', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })
        ->with(['client', 'space', 'ad', 'adset', 'payment', 'proofs'])
        ->latest()
        ->get();

        return response()->json($bookings);
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->space->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Provider action: Approve (confirmed -> send to installator) OR Reject.
        // Rejection reason is OPTIONAL. 'cancelled' kept so the Cancel button works.
        $validated = $request->validate([
            'status' => 'required|in:confirmed,rejected,active,waiting_proof,completed,cancelled',
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        // Clear any stale reason when not rejecting.
        if ($validated['status'] !== 'rejected') {
            $validated['rejection_reason'] = null;
        }

        $booking->update($validated);

        // Frontend (provider-booking-list) expects { data: booking }.
        return response()->json(['data' => $booking->load(['client', 'space', 'ad', 'adset'])]);
    }
}
