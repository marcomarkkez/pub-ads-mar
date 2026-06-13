<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Proof;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($request, $proof) {
            $proof->update([
                'status' => 'rejected',
                'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(),
                'notes' => $request->notes,
            ]);

            // Auto-open a high-priority ticket attached to the ad for the mismatch/rejection.
            $ownerId = $proof->uploaded_by_user_id
                ?? optional($proof->booking)->client_user_id
                ?? $request->user()->id;

            Ticket::create([
                'user_id' => $ownerId,
                'ticketable_type' => Ad::class,
                'ticketable_id' => $proof->ad_id,
                'subject' => 'Proof rejected/mismatch',
                'description' => 'Proof #'.$proof->id.' was rejected during review.'
                    .($request->notes ? ' Reason: '.$request->notes : ''),
                'priority' => 'high',
            ]);
        });

        return response()->json($proof->load(['ad', 'reviewedBy']));
    }
}
