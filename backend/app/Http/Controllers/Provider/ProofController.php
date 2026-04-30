<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Booking;
use App\Models\Proof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProofController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $proofs = Proof::where('uploaded_by_user_id', $request->user()->id)
            ->with(['ad', 'booking.space', 'reviewedBy'])
            ->latest()
            ->get();

        return response()->json($proofs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ad_id' => 'required|exists:ads,id',
            'booking_id' => 'required|exists:bookings,id',
            'media_type' => 'required|in:image,video',
            'file' => 'required|file|max:51200',
            'notes' => 'nullable|string',
        ]);

        $ad = Ad::findOrFail($validated['ad_id']);
        if ($ad->provider_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $file = $request->file('file');
        $path = $file->store('proofs', 'public');

        $proof = Proof::create([
            'ad_id' => $validated['ad_id'],
            'booking_id' => $validated['booking_id'],
            'uploaded_by_user_id' => $request->user()->id,
            'media_type' => $validated['media_type'],
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'notes' => $validated['notes'] ?? null,
            'deadline' => $ad->proof_deadline,
        ]);

        return response()->json($proof, 201);
    }

    public function show(Request $request, Proof $proof): JsonResponse
    {
        if ($proof->uploaded_by_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($proof->load(['ad', 'booking.space', 'reviewedBy']));
    }
}
