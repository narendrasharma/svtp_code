<?php

namespace App\Http\Controllers\Admin\Taxi;

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
 * Admin compensation plans (Phase 12A.10).
 *
 * Admin owns platform defaults (no vendor/driver) and may manage any
 * vendor/driver plan. Historical earnings are untouched by plan edits —
 * the snapshot on each earning is the permanent record.
 */
class TaxiCompensationPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $plans = TaxiDriverCompensationPlan::with([
            'vendorProfile:id,business_name', 'driver:id,first_name,last_name', 'vehicleType:id,name',
        ])
            ->when($request->filled('scope'), function ($query) use ($request): void {
                match ($request->string('scope')->toString()) {
                    'platform' => $query->whereNull('vendor_profile_id')->whereNull('driver_id'),
                    'vendor' => $query->whereNotNull('vendor_profile_id')->whereNull('driver_id'),
                    'driver' => $query->whereNotNull('driver_id'),
                    default => null,
                };
            })
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_id')))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', strtoupper($request->string('currency')->toString())))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Taxi/CompensationPlans/Index', [
            'plans' => $plans,
            'filters' => $request->only(['scope', 'vendor_id', 'currency']),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
            'calculationTypes' => collect(TaxiDriverEarningCalculation::cases())
                ->filter(fn ($c) => ! in_array($c->value, ['no_show_fixed', 'manual'], true))
                ->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Taxi/CompensationPlans/Form', [
            'plan' => null,
            'vendors' => VendorProfile::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name']),
            'drivers' => Driver::where('is_active', true)->orderBy('first_name')->limit(200)->get(['id', 'first_name', 'last_name', 'vendor_profile_id']),
            'vehicleTypes' => VehicleType::active()->get(['id', 'name']),
            'calculationTypes' => $this->calculationOptions(),
        ]);
    }

    public function store(SaveTaxiCompensationPlanRequest $request): RedirectResponse
    {
        $data = $request->planData();
        $data['vendor_profile_id'] = $request->validated('vendor_profile_id');
        $data = $this->assertConsistentScope($data);

        $data['created_by'] = $request->user()->id;

        $plan = TaxiDriverCompensationPlan::create($data);

        return redirect()->route('admin.taxi.plans.edit', $plan)->with('flash', "Compensation plan {$plan->name} created.");
    }

    public function edit(TaxiDriverCompensationPlan $plan): Response
    {
        $plan->load(['vendorProfile:id,business_name', 'driver:id,first_name,last_name']);

        return Inertia::render('Admin/Taxi/CompensationPlans/Form', [
            'plan' => $plan,
            'vendors' => VendorProfile::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name']),
            'drivers' => Driver::where('is_active', true)->orderBy('first_name')->limit(200)->get(['id', 'first_name', 'last_name', 'vendor_profile_id']),
            'vehicleTypes' => VehicleType::active()->get(['id', 'name']),
            'calculationTypes' => $this->calculationOptions(),
        ]);
    }

    public function update(SaveTaxiCompensationPlanRequest $request, TaxiDriverCompensationPlan $plan): RedirectResponse
    {
        $data = $request->planData();
        $data['vendor_profile_id'] = $request->validated('vendor_profile_id');
        $data = $this->assertConsistentScope($data);

        $data['updated_by'] = $request->user()->id;

        $plan->update($data);

        return back()->with('flash', "Compensation plan {$plan->name} updated. Historical earnings are unchanged.");
    }

    public function toggle(Request $request, TaxiDriverCompensationPlan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active, 'updated_by' => $request->user()->id]);

        return back()->with('flash', $plan->is_active ? 'Plan activated.' : 'Plan deactivated. Future trips skip it.');
    }

    public function destroy(TaxiDriverCompensationPlan $plan): RedirectResponse
    {
        $plan->delete();

        return redirect()->route('admin.taxi.plans.index')->with('flash', 'Compensation plan removed. Historical earnings are unchanged.');
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

    /**
     * A driver-specific plan implicitly inherits the driver's vendor so
     * resolution stays consistent; returns the normalized data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function assertConsistentScope(array $data): array
    {
        if (! empty($data['driver_id']) && ! empty($data['vendor_profile_id'])) {
            $driver = Driver::find($data['driver_id']);

            if ($driver && (int) $driver->vendor_profile_id !== (int) $data['vendor_profile_id']) {
                abort(422, 'The driver does not belong to the selected vendor.');
            }
        }

        if (! empty($data['driver_id']) && empty($data['vendor_profile_id'])) {
            $driver = Driver::find($data['driver_id']);
            $data['vendor_profile_id'] = $driver?->vendor_profile_id;
        }

        return $data;
    }
}
