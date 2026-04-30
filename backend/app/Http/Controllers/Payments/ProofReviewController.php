<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Proof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProofReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Proof::with(['ad.adset.campaign', 'booking.space', 'uploadedBy', 'reviewedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $proofs = $query->latest()->paginate(20);

        return response()->json($proofs);
    }

    public function approve(Request $request, Proof $proof): JsonResponse
    {
        $proof->update([
            'status' => 'approved',
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json($proof->load(['ad', 'reviewedBy']));
    }

    public function reject(Request $request, Proof $proof): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $proof->update([
            'status' => 'rejected',
            'reviewed_by_user_id' => $request->user()->id,
            'reviewed_at' => now(),
            'notes' => $request->notes,
        ]);

        return response()->json($proof->load(['ad', 'reviewedBy']));
    }
}
