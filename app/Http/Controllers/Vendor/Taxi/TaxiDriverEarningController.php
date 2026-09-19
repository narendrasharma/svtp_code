<?php

namespace App\Http\Controllers\Vendor\Taxi;

use App\Enums\TaxiDriverEarningStatus;
use App\Http\Controllers\Controller;
use App\Models\TaxiDriverEarning;
use App\Models\TaxiDriverEarningAdjustment;
use App\Models\VendorProfile;
use App\Services\TaxiDriverEarningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor driver earnings (Phase 12A.10).
 *
 * Own drivers' earnings only — every query and every direct access is
 * scoped to the vendor profile, with cross-vendor rows 404ing per
 * project convention.
 */
class TaxiDriverEarningController extends Controller
{
    public function __construct(protected TaxiDriverEarningService $earnings) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    protected function scoped(Request $request, TaxiDriverEarning $earning): TaxiDriverEarning
    {
        abort_unless((int) $earning->vendor_profile_id === (int) $this->profile($request)->id, 404);

        return $earning;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);

        $earnings = TaxiDriverEarning::with(['driver:id,first_name,last_name', 'booking:id,reference'])
            ->where('vendor_profile_id', $profile->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('earning_number', 'like', $term)
                    ->orWhereHas('booking', fn ($b) => $b->where('reference', 'like', $term))
                    ->orWhereHas('driver', fn ($d) => $d->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('driver_id'), fn ($q) => $q->where('driver_id', $request->integer('driver_id')))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', strtoupper($request->string('currency')->toString())))
            ->latest('earned_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Vendor/Taxi/Earnings/Index', [
            'earnings' => $earnings,
            'filters' => $request->only(['search', 'status', 'driver_id', 'currency']),
            'statuses' => collect(TaxiDriverEarningStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function show(Request $request, TaxiDriverEarning $earning): Response
    {
        $earning = $this->scoped($request, $earning);
        $earning->load([
            'driver:id,first_name,last_name,phone',
            'booking:id,reference,status,total_amount,currency,pickup_at',
            'assignment:id,assigned_at,unassigned_at',
            'adjustments.creator:id,name',
            'payoutItems.payout:id,payout_number,status,paid_at',
        ]);

        return Inertia::render('Vendor/Taxi/Earnings/Show', [
            'earning' => $earning,
            'unpaidRemainder' => $earning->unpaidRemainder(),
        ]);
    }

    public function markPayable(Request $request, TaxiDriverEarning $earning): RedirectResponse
    {
        $earning = $this->scoped($request, $earning);

        $this->earnings->markPayable($earning, $request->user());

        return back()->with('flash', "Earning {$earning->earning_number} is now payable.");
    }

    public function storeAdjustment(Request $request, TaxiDriverEarning $earning): RedirectResponse
    {
        $earning = $this->scoped($request, $earning);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:-999999999.99', 'max:999999999.99', 'not_in:0'],
            'kind' => ['required', Rule::in(TaxiDriverEarningAdjustment::KINDS)],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->earnings->applyAdjustment(
            $earning,
            (float) $validated['amount'],
            $validated['kind'],
            $validated['reason'],
            $request->user(),
        );

        return back()->with('flash', 'Adjustment posted. Net earning updated.');
    }
}
