<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\Comms\ShareService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer-safe signed quotation page. No login required — the
 * unguessable token opens the view, and separate signed URLs authorize
 * the accept/reject decisions (signature + expiry + state re-validated
 * server-side on every attempt).
 */
class PublicQuotationController extends Controller
{
    public function show(string $token): Response
    {
        $quotation = Quotation::where('public_token', $token)->firstOrFail();
        $quotation->load(['items']);

        $decisions = app(ShareService::class)->quotationDecisionUrls($quotation);

        return Inertia::render('Quotations/Public', [
            'quotation' => [
                'reference' => $quotation->displayReference(),
                'status' => $quotation->status,
                'currency' => $quotation->currency,
                'valid_until' => $quotation->valid_until,
                'subtotal' => $quotation->subtotal,
                'discount_amount' => $quotation->discount_amount,
                'tax_amount' => $quotation->tax_amount,
                'total_amount' => $quotation->total_amount,
                'terms' => $quotation->terms,
                'customer_note' => $quotation->customer_note,
                'items' => $quotation->items->map(fn ($item): array => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_amount' => $item->total_amount,
                ]),
                'is_expired' => $quotation->isExpired(),
                // No internal notes, no admin URLs — customer-safe only.
            ],
            'decisionUrls' => $decisions,
            'canDecide' => in_array($quotation->status, ['sent', 'viewed'], true) && ! $quotation->isExpired(),
        ]);
    }
}
