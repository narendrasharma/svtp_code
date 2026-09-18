<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\VendorProfile;
use App\Models\VendorWithdrawalRequest;
use App\Services\Payouts\PayoutProcessor;
use App\Services\VendorLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin withdrawal review (Phase 7, manual settlement).
 *
 * No GET route mutates state. Approve keeps the hold; reject/cancel release
 * it; mark-paid converts the hold into a settlement (release + settlement
 * pair, never a double debit). All money movement is idempotent in
 * VendorLedgerService.
 */
class WithdrawalController extends Controller
{
    public function __construct(protected VendorLedgerService $ledger, protected PayoutProcessor $payouts) {}

    public function index(Request $request): Response
    {
        $withdrawals = VendorWithdrawalRequest::with('vendorProfile:id,business_name,email,vendor_verification_id')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->integer('vendor'), fn ($query) => $query->where('vendor_profile_id', $request->integer('vendor')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('requested_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('requested_at', '<=', $request->date('date_to')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $withdrawals->through(function (VendorWithdrawalRequest $withdrawal): VendorWithdrawalRequest {
            $withdrawal->setAttribute('kyc_verified', $withdrawal->vendorProfile?->isKycVerified() ?? false);

            return $withdrawal;
        });

        $pendingCount = VendorWithdrawalRequest::where('status', WithdrawalStatus::Pending->value)->count();

        return Inertia::render('Admin/Withdrawals/Index', [
            'withdrawals' => $withdrawals,
            'filters' => $request->only(['status', 'vendor', 'date_from', 'date_to']),
            'vendors' => VendorProfile::whereHas('withdrawalRequests')->orderBy('business_name')->get(['id', 'business_name']),
            'statuses' => collect(WithdrawalStatus::cases())->map(fn (WithdrawalStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
            'pendingCount' => $pendingCount,
        ]);
    }

    public function show(VendorWithdrawalRequest $withdrawal): Response
    {
        $withdrawal->load('vendorProfile:id,business_name,email,phone,vendor_verification_id', 'vendorProfile.verification', 'reviewer:id,name', 'ledgerEntries');

        $profile = $withdrawal->vendorProfile;

        return Inertia::render('Admin/Withdrawals/Show', [
            'withdrawal' => $withdrawal,
            'vendor' => $profile,
            'kyc' => [
                'verified' => $profile?->isKycVerified() ?? false,
                'status' => $profile?->verification?->status?->value,
            ],
            'balances' => $profile ? $this->ledger->balances($profile->id) : null,
            'statuses' => collect(WithdrawalStatus::cases())->map(fn (WithdrawalStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function approve(Request $request, VendorWithdrawalRequest $withdrawal): RedirectResponse
    {
        $request->validate(['admin_note' => ['nullable', 'string', 'max:255']]);

        $this->ledger->approveWithdrawal($withdrawal, $request->user(), $request->input('admin_note'));

        return back()->with('flash', "Withdrawal request #{$withdrawal->id} approved; funds stay held until payout.");
    }

    public function reject(Request $request, VendorWithdrawalRequest $withdrawal): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ledger->rejectWithdrawal($withdrawal, $request->user(), $request->input('rejection_reason'), $request->input('admin_note'));

        return back()->with('flash', "Withdrawal request #{$withdrawal->id} rejected; held funds released.");
    }

    public function markPaid(Request $request, VendorWithdrawalRequest $withdrawal): RedirectResponse
    {
        $request->validate([
            'payout_reference' => ['nullable', 'string', 'max:100'],
            'admin_note' => ['nullable', 'string', 'max:255'],
        ]);

        // Phase 8: settlement flows through the payout provider seam
        // (manual processor today). Idempotency preserved in the ledger.
        $this->payouts->settle($withdrawal, $request->user(), $request->input('payout_reference'), $request->input('admin_note'));

        return back()->with('flash', "Withdrawal request #{$withdrawal->id} marked as paid via {$this->payouts->label()}.");
    }
}
