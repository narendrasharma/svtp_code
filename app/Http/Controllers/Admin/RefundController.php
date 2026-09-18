<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manual accounting refunds (Phase 8).
 *
 * Recording is an accounting entry only — no money moves electronically.
 * Each processed refund owns exactly one proportional vendor reversal;
 * booking snapshots stay immutable. No GET route mutates state.
 */
class RefundController extends Controller
{
    public function __construct(protected BookingRefundService $refunds) {}

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'reason' => ['required', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:64'],
        ]);

        $refund = $this->refunds->recordRefund(
            $booking,
            $validated['amount'],
            $validated['reason'],
            $request->user(),
            $validated['external_reference'] ?? null,
            $validated['reference'] ?? null,
        );

        return back()->with('flash', "Refund of ₹{$refund->amount} recorded (manual accounting — vendor reversal ₹{$refund->vendor_reversal_amount}).");
    }
}
