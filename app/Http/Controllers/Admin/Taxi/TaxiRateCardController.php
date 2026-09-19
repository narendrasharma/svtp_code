<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Enums\TaxiRateCalculationType;
use App\Enums\TaxiRateRuleCode;
use App\Enums\TripType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taxi\SaveTaxiRateCardRequest;
use App\Models\TaxiRateCard;
use App\Models\VehicleType;
use App\Models\VendorProfile;
use App\Services\TaxiRateCardService;
use App\Support\TaxiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxiRateCardController extends Controller
{
    public function __construct(protected TaxiRateCardService $rateCards) {}

    public function index(Request $request): Response
    {
        $cards = TaxiRateCard::with(['vendorProfile:id,business_name', 'vehicleType:id,name'])
            ->withCount(['rules', 'rentalPackages'])
            ->when($request->filled('trip_type'), fn ($query) => $query->where('trip_type', $request->string('trip_type')->toString()))
            ->when($request->filled('vendor_id'), fn ($query) => $query->where('vendor_profile_id', $request->integer('vendor_id')))
            ->when($request->boolean('platform_only'), fn ($query) => $query->whereNull('vendor_profile_id'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Taxi/Pricing/Index', [
            'rateCards' => $cards,
            'filters' => $request->only(['trip_type', 'vendor_id', 'platform_only']),
            'tripTypes' => $this->tripTypes(),
            'vendors' => VendorProfile::orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Taxi/Pricing/Form', $this->formProps());
    }

    public function store(SaveTaxiRateCardRequest $request): RedirectResponse
    {
        $card = $this->rateCards->save(null, $request->validated());

        return redirect()->route('admin.taxi.pricing.edit', $card)->with('flash', 'Taxi rate card created.');
    }

    public function edit(TaxiRateCard $rateCard): Response
    {
        return Inertia::render('Admin/Taxi/Pricing/Form', [
            ...$this->formProps(),
            'rateCard' => $rateCard->load(['rules', 'rentalPackages']),
        ]);
    }

    public function update(SaveTaxiRateCardRequest $request, TaxiRateCard $rateCard): RedirectResponse
    {
        $this->rateCards->save($rateCard, $request->validated());

        return back()->with('flash', 'Taxi rate card updated.');
    }

    public function toggle(TaxiRateCard $rateCard): RedirectResponse
    {
        $rateCard = $this->rateCards->toggle($rateCard);

        return back()->with('flash', $rateCard->is_active ? 'Taxi rate card activated.' : 'Taxi rate card deactivated.');
    }

    public function destroy(TaxiRateCard $rateCard): RedirectResponse
    {
        $rateCard->delete();

        return redirect()->route('admin.taxi.pricing.index')->with('flash', 'Taxi rate card removed. Historical bookings retain their snapshots.');
    }

    /** @return array<string, mixed> */
    private function formProps(): array
    {
        return [
            'rateCard' => null,
            'vendors' => VendorProfile::where('is_active', true)->orderBy('business_name')->get(['id', 'business_name']),
            'vehicleTypes' => VehicleType::active()->get(['id', 'name']),
            'tripTypes' => $this->tripTypes(),
            'ruleCodes' => collect(TaxiRateRuleCode::cases())->map(fn ($rule) => ['value' => $rule->value, 'label' => $rule->label()]),
            'calculationTypes' => collect(TaxiRateCalculationType::cases())->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()]),
            'defaultCurrency' => TaxiSettings::get('taxi.default_currency') ?? 'INR',
            'fixedVendor' => false,
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    private function tripTypes(): array
    {
        return collect(TripType::cases())->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()])->all();
    }
}
