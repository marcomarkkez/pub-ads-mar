<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Proof;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProofFlagController extends Controller
{
    public function flagMismatch(Request $request, Proof $proof): JsonResponse
    {
        // Ownership guard: only the client who owns the proof's booking may flag it.
        $proof->loadMissing('booking');
        abort_unless(
            $proof->booking && $proof->booking->client_user_id === $request->user()->id,
            403,
            'You can only flag proofs on your own bookings.'
        );

        DB::transaction(function () use ($request, $proof) {
            $proof->update([
                'status' => 'rejected',
                // Preserve the provider's original notes; append the flag marker.
                'notes' => trim(($proof->notes ? $proof->notes . ' | ' : '') . 'mismatch flagged by client'),
            ]);

            Ticket::create([
                'user_id' => $request->user()->id,
                'ticketable_type' => Ad::class,
                'ticketable_id' => $proof->ad_id,
                'subject' => 'Proof mismatch flagged',
                'description' => 'Client flagged proof #'.$proof->id.' as not matching the booked ad.',
                'priority' => 'high',
            ]);
        });

        return response()->json($proof->load(['ad', 'booking.space']));
    }
}
