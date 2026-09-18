<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LedgerEntryType;
use App\Http\Controllers\Controller;
use App\Models\VendorProfile;
use App\Services\BookingRefundService;
use App\Services\VendorLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin per-vendor finance detail (Phase 8).
 *
 * Ledger-derived balances, full append-only timeline with actors, and the
 * manual adjustment form. Adjustments are append-only: no edit/delete
 * exists. A debit may drive available negative for genuine corrections —
 * the UI shows the projected balance and warns; withdrawals stay blocked
 * while available <= 0 (enforced in VendorLedgerService).
 */
class VendorFinanceController extends Controller
{
    public function __construct(
        protected VendorLedgerService $ledger,
        protected BookingRefundService $refunds,
    ) {}

    public function show(Request $request, VendorProfile $vendorProfile): Response
    {
        $vendorProfile->load('user:id,name,email', 'verification');

        $entries = $vendorProfile->ledgerEntries()
            ->with(['booking:id,booking_reference_id', 'creator:id,name'])
            ->paginate(20)
            ->withQueryString();

        $withdrawals = $vendorProfile->withdrawalRequests()->orderByDesc('id')->limit(10)->get();

        return Inertia::render('Admin/VendorFinances/Show', [
            'vendor' => [
                'id' => $vendorProfile->id,
                'business_name' => $vendorProfile->business_name,
                'email' => $vendorProfile->email,
                'user' => $vendorProfile->user ? ['id' => $vendorProfile->user->id, 'name' => $vendorProfile->user->name] : null,
            ],
            'kyc' => [
                'verified' => $vendorProfile->isKycVerified(),
                'status' => $vendorProfile->verification?->status?->value,
            ],
            'payoutAccount' => $vendorProfile->payoutAccount?->toSafeArray(),
            'balances' => $this->ledger->balances($vendorProfile->id),
            'ledger' => $entries,
            'withdrawals' => $withdrawals,
            'adjustmentTypes' => [
                ['value' => LedgerEntryType::AdjustmentCredit->value, 'label' => 'Credit'],
                ['value' => LedgerEntryType::AdjustmentDebit->value, 'label' => 'Debit'],
            ],
        ]);
    }

    public function storeAdjustment(Request $request, VendorProfile $vendorProfile): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([LedgerEntryType::AdjustmentCredit->value, LedgerEntryType::AdjustmentDebit->value])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'reason' => ['required', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $request->boolean('confirm')) {
            return back()->withErrors(['confirm' => 'Please confirm this adjustment.'])->withInput();
        }

        $entry = $this->ledger->adjust(
            $vendorProfile,
            LedgerEntryType::from($validated['type']),
            $validated['amount'],
            $validated['reason'],
            $request->user(),
            $validated['external_reference'] ?? null,
        );

        $balances = $this->ledger->balances($vendorProfile->id);
        $warning = ((float) $balances['available_balance'] < 0)
            ? " Warning: available balance is now ₹{$balances['available_balance']} — withdrawals stay blocked until it recovers."
            : '';

        return back()->with('flash', "Adjustment recorded (₹{$entry->amount} {$entry->type->value}).".$warning);
    }
}
