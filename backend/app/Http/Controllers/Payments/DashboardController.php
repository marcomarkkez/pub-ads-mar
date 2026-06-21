<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * [todo B8] Payments stats dashboard.
 * Owner decision 2026-06-20: Payments dashboard shows money numbers — paid vs on-hold
 * (+ pending and refunded). Payments does NOT review proof content (see todo B9), so this
 * is purely a money view.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // "paid" = money that left to providers (completed booking payment or released payout)
        $paid = Payment::whereIn('status', ['completed', 'released']);
        // "on hold" = payout held pending proof/dispute resolution
        $held = Payment::where('status', 'held');
        $pending = Payment::where('status', 'pending');
        $refunded = Payment::where('status', 'refunded');

        return response()->json([
            'paid'     => ['count' => (clone $paid)->count(),     'amount' => (float) (clone $paid)->sum('amount')],
            'on_hold'  => ['count' => (clone $held)->count(),     'amount' => (float) (clone $held)->sum('amount')],
            'pending'  => ['count' => (clone $pending)->count(),  'amount' => (float) (clone $pending)->sum('amount')],
            'refunded' => ['count' => (clone $refunded)->count(), 'amount' => (float) (clone $refunded)->sum('amount')],
        ]);
    }
}
