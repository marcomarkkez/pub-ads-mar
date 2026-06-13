<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Space;
use App\Models\SystemConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::where('client_user_id', $request->user()->id)
            ->with(['space.user', 'ad', 'adset', 'payment'])
            ->latest()
            ->get();

        return response()->json($bookings);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'ad_id' => 'nullable|exists:ads,id',
            'adset_id' => 'nullable|exists:adsets,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'book_for_later' => 'nullable|boolean',
        ]);

        $space = Space::with('availabilities')->findOrFail($validated['space_id']);
        $days = now()->parse($validated['start_date'])->diffInDays(now()->parse($validated['end_date'])) + 1;

        $pricePerDay = $space->price_per_day ?? ($space->price_per_month ? $space->price_per_month / 30 : 0);
        $totalPrice = $pricePerDay * $days;

        // C12/C13: determine status from availability overlap (coincide).
        $coincides = $space->availabilities
            ->where('status', 'available')
            ->contains(function ($a) use ($validated) {
                return $a->start_date <= $validated['end_date']
                    && $a->end_date >= $validated['start_date'];
            });

        // Coinciding availability -> goes to the provider for approval.
        // Otherwise the booking is queued as pending (incl. book_for_later).
        $status = $coincides ? 'waiting_approval' : 'pending';

        $booking = Booking::create([
            'client_user_id' => $request->user()->id,
            'space_id' => $validated['space_id'],
            'ad_id' => $validated['ad_id'] ?? null,
            'adset_id' => $validated['adset_id'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'total_price' => $totalPrice,
            'status' => $status,
            'config_snapshot' => [
                'proof_deadline_days' => SystemConfiguration::get('proof_deadline_days', 5),
            ],
        ]);

        $booking->payment()->create([
            'amount' => $totalPrice,
            'status' => 'pending',
            'payment_method' => 'mocked',
            'transaction_id' => 'TXN-' . Str::upper(Str::random(10)),
        ]);

        return response()->json($booking->load(['space', 'ad', 'adset', 'payment']), 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->client_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($booking->load(['space.user', 'ad', 'adset', 'payment', 'proofs']));
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->client_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:cancelled',
        ]);

        $booking->update($validated);

        return response()->json($booking);
    }
}
