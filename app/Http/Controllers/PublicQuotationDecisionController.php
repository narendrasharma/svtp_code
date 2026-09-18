<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Public quotation decisions behind signed URLs (signature + expiry via
 * the `signed` middleware). No login required. Accepting never creates
 * a booking by itself — staff convert after a final availability check.
 */
class PublicQuotationDecisionController extends Controller
{
    public function __construct(protected QuotationService $quotations) {}

    public function accept(Request $request, string $token): RedirectResponse
    {
        $quotation = Quotation::where('public_token', $token)->firstOrFail();

        $validated = $request->validate(['customer_note' => ['nullable', 'string', 'max:2000']]);

        try {
            if (! empty($validated['customer_note'])) {
                $quotation->update(['customer_note' => $validated['customer_note']]);
            }

            $this->quotations->accept($quotation->refresh());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('flash', 'Quotation accepted. Our team will confirm your booking shortly.');
    }

    public function reject(Request $request, string $token): RedirectResponse
    {
        $quotation = Quotation::where('public_token', $token)->firstOrFail();

        try {
            $this->quotations->reject($quotation->refresh());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('flash', 'Quotation rejected. Thank you for letting us know.');
    }
}
