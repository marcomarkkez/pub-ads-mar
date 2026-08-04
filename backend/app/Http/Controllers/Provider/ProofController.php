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
    /**
     * design.json §21 — access DERIVES FROM THE OWNERSHIP CHAIN, not from authorship.
     *
     * This used to filter on `uploaded_by_user_id`, which is the uploader, not the owner.
     * §7 says the INSTALLATOR subrole uploads the proof of display, so the moment provider
     * collaborators ship, a provider looking at their own listing's proofs would see an
     * empty list and a 404 on each one — their own space, their own booking, invisible
     * because someone else pressed the button. The chain is proof → booking → space → user.
     * The uploader stays an allowed reader on top of it (they must be able to see what they
     * just uploaded even when their grant on the space is later revoked).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $proofs = Proof::where(function ($query) use ($userId) {
            $query->whereHas('booking.space', fn ($q) => $q->where('user_id', $userId))
                ->orWhere('uploaded_by_user_id', $userId);
        })
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
        // §21 — the ownership CHAIN decides (see index()), not who pressed upload: the
        // provider who owns the space always reads the proofs on their own bookings, and
        // the uploader (installator subrole, §7) reads what they uploaded.
        //
        // 404, never 403 — §21 rule 2 (Q37): see Client\ProofFlagController::ownedProof().
        $userId = $request->user()->id;
        $proof->loadMissing('booking.space');

        $ownsSpace = $proof->booking?->space?->user_id === $userId;
        $uploaded = $proof->uploaded_by_user_id === $userId;

        if (! $ownsSpace && ! $uploaded) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($proof->load(['ad', 'booking.space', 'reviewedBy']));
    }
}
