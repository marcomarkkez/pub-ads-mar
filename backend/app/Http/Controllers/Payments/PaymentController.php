<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['booking.client', 'booking.space', 'approvedBy']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->latest()->paginate(20);

        return response()->json($payments);
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load(['booking.client', 'booking.space', 'booking.ad', 'approvedBy']));
    }

    public function approve(Request $request, Payment $payment): JsonResponse
    {
        $payment->update([
            'approved_by_payments' => true,
            'approved_by_user_id' => $request->user()->id,
            'status' => 'completed',
        ]);

        return response()->json($payment->load('approvedBy'));
    }

    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $payment->update([
            'approved_by_payments' => false,
            'approved_by_user_id' => $request->user()->id,
            'status' => 'failed',
        ]);

        return response()->json($payment->load('approvedBy'));
    }
}
