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

        $validated = $request->validate([
            'status' => 'required|in:confirmed,rejected,active,waiting_proof,completed',
        ]);

        $booking->update($validated);

        return response()->json($booking->load(['client', 'space', 'ad', 'adset']));
    }
}
