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
            'ad_id' => 'nullable|exists:ads,id',
            'booking_id' => 'required|exists:bookings,id',
            'file' => 'required|file|max:51200',
            'notes' => 'nullable|string',
        ]);

        $booking = Booking::with('adset.ads')->findOrFail($validated['booking_id']);

        // Resolve the target ad: explicit ad_id, else booking->ad, else first adset ad.
        $ad = null;
        if (! empty($validated['ad_id'])) {
            $ad = Ad::findOrFail($validated['ad_id']);
        } elseif ($booking->ad_id) {
            $ad = $booking->ad;
        } elseif ($booking->relationLoaded('adset') && $booking->adset) {
            $ad = $booking->adset->ads->first();
        }

        if (! $ad) {
            return response()->json(['message' => 'No ad found for this booking.'], 422);
        }

        if ($ad->provider_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $file = $request->file('file');

        // Infer media_type from the uploaded file mime rather than trusting the client.
        $mime = (string) $file->getMimeType();
        if (str_starts_with($mime, 'image/')) {
            $mediaType = 'image';
        } elseif (str_starts_with($mime, 'video/')) {
            $mediaType = 'video';
        } else {
            return response()->json(['message' => 'Proof must be an image or video file.'], 422);
        }

        // Radio/audio ads can only be proven with a video.
        if ($ad->media_type === 'sound' && $mediaType !== 'video') {
            return response()->json(['message' => 'Radio/audio ads require a video proof.'], 422);
        }

        $path = $file->store('proofs', 'public');

        $proof = Proof::create([
            'ad_id' => $ad->id,
            'booking_id' => $validated['booking_id'],
            'uploaded_by_user_id' => $request->user()->id,
            'media_type' => $mediaType,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'notes' => $validated['notes'] ?? null,
            'deadline' => $ad->proof_deadline,
        ]);

        return response()->json($proof->load(['ad', 'booking.space']), 201);
    }

    public function show(Request $request, Proof $proof): JsonResponse
    {
        if ($proof->uploaded_by_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($proof->load(['ad', 'booking.space', 'reviewedBy']));
    }
}
