<?php

namespace App\Http\Controllers\Vendor;

use App\Enums\PayoutMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StoreWithdrawalRequest;
use App\Models\VendorLedgerEntry;
use App\Models\VendorWithdrawalRequest;
use App\Services\VendorLedgerService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor finance — earnings, ledger and withdrawals (Phase 7).
 *
 * Read views are available to every approved vendor (KYC included or not).
 * Money movement (request/cancel withdrawal) is blocked while impersonating
 * via the block.impersonated.sensitive route middleware — an impersonated
 * vendor may VIEW finance but never move money.
 */
class FinanceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected VendorLedgerService $ledger) {}

    public function index(Request $request): Response
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);

        $this->authorize('viewAny', VendorLedgerEntry::class);

        $entries = $profile->ledgerEntries()
            ->with(['booking:id,booking_reference_id', 'creator:id,name'])
            ->paginate(15, ['*'], 'ledger_page')
            ->withQueryString();

        $withdrawals = $profile->withdrawalRequests()
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'withdrawals_page')
            ->withQueryString();

        $verification = $profile->verification;

        return Inertia::render('Vendor/Finance/Index', [
            'balances' => $this->ledger->balances($profile->id),
            'ledger' => $entries,
            'withdrawals' => $withdrawals,
            // Phase 8: masked payout destination only — secrets never leave.
            'payoutAccount' => $profile->payoutAccount?->toSafeArray(),
            'payoutMethods' => collect(PayoutMethod::cases())->map(fn (PayoutMethod $method): array => ['value' => $method->value, 'label' => $method->label()]),
            'kyc' => [
                'verified' => $profile->isKycVerified(),
                'status' => $verification?->status?->value,
                'message' => $profile->isKycVerified()
                    ? null
                    : 'Complete KYC verification to request payouts.',
            ],
            'minimumWithdrawal' => $this->ledger->minimumWithdrawalAmount(),
            'eligibility' => $this->ledger->withdrawalEligibility($profile),
        ]);
    }

    public function store(StoreWithdrawalRequest $request): RedirectResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);

        $this->authorize('create', VendorWithdrawalRequest::class);

        $withdrawal = $this->ledger->requestWithdrawal(
            $profile,
            $request->validated('amount'),
            $request->user(),
            $request->validated('vendor_note')
        );

        return redirect()->route('vendor.finance.index')
            ->with('flash', "Withdrawal request #{$withdrawal->id} submitted; funds are held until review.");
    }

    public function cancel(Request $request, VendorWithdrawalRequest $withdrawal): RedirectResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && (int) $withdrawal->vendor_profile_id === (int) $profile->id, 403);

        $this->authorize('cancel', $withdrawal);

        $this->ledger->cancelWithdrawal($withdrawal, $request->user());

        return back()->with('flash', "Withdrawal request #{$withdrawal->id} cancelled; held funds released.");
    }
}
