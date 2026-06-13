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
        DB::transaction(function () use ($request, $proof) {
            $proof->update([
                'status' => 'rejected',
                'notes' => 'mismatch flagged by client',
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
