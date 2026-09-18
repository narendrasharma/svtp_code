<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\VendorPayoutAccount;
use App\Services\PayoutAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin payout destination review (Phase 8).
 *
 * Masked identifiers only — there is deliberately no "reveal" action.
 * Manual verification uses the uploaded KYC proof set (cancelled cheque is
 * already a KYC document type), so full secrets never enter page source.
 */
class PayoutAccountController extends Controller
{
    public function __construct(protected PayoutAccountService $payouts) {}

    public function index(Request $request): Response
    {
        $accounts = VendorPayoutAccount::with('vendorProfile:id,business_name,email')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->whereHas('vendorProfile', fn ($q) => $q->where('business_name', 'like', $term));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $accounts->through(fn (VendorPayoutAccount $account): array => $account->toSafeArray() + [
            'business_name' => $account->vendorProfile?->business_name,
            'kyc_verified' => $account->vendorProfile?->isKycVerified() ?? false,
        ]);

        return Inertia::render('Admin/PayoutAccounts/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['status', 'search']),
            'statuses' => collect(PayoutAccountStatus::cases())->map(fn (PayoutAccountStatus $status): array => ['value' => $status->value, 'label' => $status->label()]),
            'pendingCount' => VendorPayoutAccount::where('status', PayoutAccountStatus::Pending->value)->count(),
        ]);
    }

    public function show(VendorPayoutAccount $payoutAccount): Response
    {
        $payoutAccount->load('vendorProfile:id,business_name,email,phone', 'vendorProfile.verification', 'verifier:id,name');

        $profile = $payoutAccount->vendorProfile;

        return Inertia::render('Admin/PayoutAccounts/Show', [
            'account' => $payoutAccount->toSafeArray(),
            'vendor' => $profile ? [
                'id' => $profile->id,
                'business_name' => $profile->business_name,
                'email' => $profile->email,
                'phone' => $profile->phone,
            ] : null,
            'kyc' => [
                'verified' => $profile?->isKycVerified() ?? false,
                'status' => $profile?->verification?->status?->value,
            ],
            'reviewer' => $payoutAccount->verifier ? ['id' => $payoutAccount->verifier->id, 'name' => $payoutAccount->verifier->name] : null,
        ]);
    }

    public function verify(Request $request, VendorPayoutAccount $payoutAccount): RedirectResponse
    {
        $this->payouts->verify($payoutAccount, $request->user());

        return back()->with('flash', 'Payout account verified; the vendor can now request withdrawals.');
    }

    public function reject(Request $request, VendorPayoutAccount $payoutAccount): RedirectResponse
    {
        $request->validate(['rejection_reason' => ['required', 'string', 'max:255']]);

        $this->payouts->reject($payoutAccount, $request->user(), $request->input('rejection_reason'));

        return back()->with('flash', 'Payout account rejected with reason.');
    }
}
