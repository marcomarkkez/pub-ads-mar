<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\WalletEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $entries = WalletEntry::where('user_id', $userId)
            ->latest()
            ->get();

        return response()->json([
            'entries' => $entries,
            'balance' => WalletEntry::balanceFor($userId),
        ]);
    }
}
