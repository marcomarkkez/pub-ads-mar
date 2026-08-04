<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::whereHas('campaign', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })
        ->with('campaign')
        ->latest()
        ->get();

        return response()->json($invoices);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->campaign->user_id !== $request->user()->id) {
            // 404, never 403 — §21 rule 2 (BR-3): a 403 confirms the row exists, which is
            // enough to enumerate another account's ids. "Not yours" and "does not exist"
            // must be indistinguishable to a stranger.
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json($invoice->load('campaign'));
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        if ($invoice->campaign->user_id !== $request->user()->id) {
            // 404, never 403 — §21 rule 2 (BR-3): a 403 confirms the row exists, which is
            // enough to enumerate another account's ids. "Not yours" and "does not exist"
            // must be indistinguishable to a stranger.
            return response()->json(['message' => 'Not found.'], 404);
        }

        $invoice->load('campaign.adsets.ads');

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'client' => $request->user(),
        ]);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}
