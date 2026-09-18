<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StorePayoutAccountRequest;
use App\Models\VendorPayoutAccount;
use App\Services\PayoutAccountService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

/**
 * Vendor payout destinations (Phase 8).
 *
 * Create/replace only — verification is admin-side. Blocked while
 * impersonating: an impersonated vendor may view masked details but never
 * add or replace them.
 */
class PayoutAccountController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected PayoutAccountService $payouts) {}

    public function store(StorePayoutAccountRequest $request): RedirectResponse
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile, 403);

        $this->authorize('save', VendorPayoutAccount::class);

        $this->payouts->save($profile, $request->payoutData(), $request->user());

        return redirect()->route('vendor.finance.index')
            ->with('flash', 'Payout details saved and sent for verification.');
    }
}
