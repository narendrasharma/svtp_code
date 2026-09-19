<?php

namespace App\Http\Controllers\Vendor\Taxi;

use App\Enums\TaxiDriverEarningCalculation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taxi\SaveTaxiCompensationPlanRequest;
use App\Models\Driver;
use App\Models\TaxiDriverCompensationPlan;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor compensation plans (Phase 12A.10).
 *
 * Vendors manage vendor-default and own-driver plans only. Platform
 * defaults (no vendor) are visible for reference but never editable
 * here. A driver attached to a plan must belong to this vendor.
 */
class TaxiCompensationPlanController extends Controller
{
    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);

        $plans = TaxiDriverCompensationPlan::with(['driver:id,first_name,last_name', 'vehicleType:id,name'])
            ->where('vendor_profile_id', $profile->id)
            ->when($request->filled('scope'), function ($query) use ($request): void {
                match ($request->string('scope')->toString()) {
                    'platform' => $query->whereNull('vendor_profile_id'),
                    'vendor' => $query->whereNotNull('vendor_profile_id')->whereNull('driver_id'),
                    'driver' => $query->whereNotNull('driver_id'),
                    default => null,
                };
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Vendor/Taxi/CompensationPlans/Index', [
            'plans' => $plans,
            'filters' => $request->only(['scope']),
            'profileId' => $profile->id,
            'calculationTypes' => $this->calculationOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $profile = $this->profile($request);

        return Inertia::render('Vendor/Taxi/CompensationPlans/Form', [
            'plan' => null,
            'drivers' => Driver::where('vendor_profile_id', $profile->id)->where('is_active', true)
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'vehicleTypes' => VehicleType::active()->get(['id', 'name']),
            'calculationTypes' => $this->calculationOptions(),
        ]);
    }

    public function store(SaveTaxiCompensationPlanRequest $request): RedirectResponse
    {
        $profile = $this->profile($request);
        $data = $request->planData();
        $data['vendor_profile_id'] = $profile->id;

        if (! empty($data['driver_id'])) {
            abort_unless(
                Driver::whereKey($data['driver_id'])->where('vendor_profile_id', $profile->id)->exists(),
                422,
                'The driver does not belong to your fleet.'
            );
        }

        $data['created_by'] = $request->user()->id;

        $plan = TaxiDriverCompensationPlan::create($data);

        return redirect()->route('vendor.taxi.plans.edit', $plan)->with('flash', "Compensation plan {$plan->name} created.");
    }

    public function edit(Request $request, TaxiDriverCompensationPlan $plan): Response
    {
        $profile = $this->profile($request);
        $this->scoped($profile, $plan, editable: true);
        $plan->load(['driver:id,first_name,last_name']);

        return Inertia::render('Vendor/Taxi/CompensationPlans/Form', [
            'plan' => $plan,
            'drivers' => Driver::where('vendor_profile_id', $profile->id)->where('is_active', true)
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'vehicleTypes' => VehicleType::active()->get(['id', 'name']),
            'calculationTypes' => $this->calculationOptions(),
        ]);
    }

    public function update(SaveTaxiCompensationPlanRequest $request, TaxiDriverCompensationPlan $plan): RedirectResponse
    {
        $profile = $this->profile($request);
        $this->scoped($profile, $plan, editable: true);

        $data = $request->planData();
        $data['vendor_profile_id'] = $profile->id;

        if (! empty($data['driver_id'])) {
            abort_unless(
                Driver::whereKey($data['driver_id'])->where('vendor_profile_id', $profile->id)->exists(),
                422,
                'The driver does not belong to your fleet.'
            );
        }

        $data['updated_by'] = $request->user()->id;

        $plan->update($data);

        return back()->with('flash', "Compensation plan {$plan->name} updated. Historical earnings are unchanged.");
    }

    public function toggle(Request $request, TaxiDriverCompensationPlan $plan): RedirectResponse
    {
        $profile = $this->profile($request);
        $this->scoped($profile, $plan, editable: true);

        $plan->update(['is_active' => ! $plan->is_active, 'updated_by' => $request->user()->id]);

        return back()->with('flash', $plan->is_active ? 'Plan activated.' : 'Plan deactivated. Future trips skip it.');
    }

    public function destroy(Request $request, TaxiDriverCompensationPlan $plan): RedirectResponse
    {
        $profile = $this->profile($request);
        $this->scoped($profile, $plan, editable: true);

        $plan->delete();

        return redirect()->route('vendor.taxi.plans.index')->with('flash', 'Compensation plan removed. Historical earnings are unchanged.');
    }

    protected function scoped(VendorProfile $profile, TaxiDriverCompensationPlan $plan, bool $editable = false): TaxiDriverCompensationPlan
    {
        // Platform rows leak existence on edit attempts but never their
        // management: view-only at list level, 404 on direct access.
        abort_unless((int) $plan->vendor_profile_id === (int) $profile->id, 404);

        return $plan;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function calculationOptions(): array
    {
        return collect(TaxiDriverEarningCalculation::cases())
            ->filter(fn ($c) => ! in_array($c->value, ['no_show_fixed', 'manual'], true))
            ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values()->all();
    }
}
