<?php

namespace App\Http\Controllers\Vendor\Taxi;

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
        $profile = $this->profile($request);

        return Inertia::render('Vendor/Taxi/Pricing/Index', [
            'rateCards' => TaxiRateCard::with('vehicleType:id,name')
                ->withCount(['rules', 'rentalPackages'])
                ->where('vendor_profile_id', $profile->id)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->profile($request);

        return Inertia::render('Vendor/Taxi/Pricing/Form', $this->formProps());
    }

    public function store(SaveTaxiRateCardRequest $request): RedirectResponse
    {
        $profile = $this->profile($request);
        $card = $this->rateCards->save(null, [
            ...$request->validated(),
            'vendor_profile_id' => $profile->id,
        ]);

        return redirect()->route('vendor.taxi.pricing.edit', $card)->with('flash', 'Taxi rate card created.');
    }

    public function edit(Request $request, TaxiRateCard $rateCard): Response
    {
        $rateCard = $this->scoped($request, $rateCard);

        return Inertia::render('Vendor/Taxi/Pricing/Form', [
            ...$this->formProps(),
            'rateCard' => $rateCard->load(['rules', 'rentalPackages']),
        ]);
    }

    public function update(SaveTaxiRateCardRequest $request, TaxiRateCard $rateCard): RedirectResponse
    {
        $rateCard = $this->scoped($request, $rateCard);
        $this->rateCards->save($rateCard, [
            ...$request->validated(),
            'vendor_profile_id' => $rateCard->vendor_profile_id,
        ]);

        return back()->with('flash', 'Taxi rate card updated.');
    }

    public function toggle(Request $request, TaxiRateCard $rateCard): RedirectResponse
    {
        $rateCard = $this->scoped($request, $rateCard);
        $rateCard = $this->rateCards->toggle($rateCard);

        return back()->with('flash', $rateCard->is_active ? 'Taxi rate card activated.' : 'Taxi rate card deactivated.');
    }

    public function destroy(Request $request, TaxiRateCard $rateCard): RedirectResponse
    {
        $this->scoped($request, $rateCard)->delete();

        return redirect()->route('vendor.taxi.pricing.index')->with('flash', 'Taxi rate card removed. Historical bookings retain their snapshots.');
    }

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    protected function scoped(Request $request, TaxiRateCard $rateCard): TaxiRateCard
    {
        abort_unless((int) $rateCard->vendor_profile_id === (int) $this->profile($request)->id, 404);

        return $rateCard;
    }

    /** @return array<string, mixed> */
    private function formProps(): array
    {
        return [
            'rateCard' => null,
            'vendors' => [],
            'vehicleTypes' => VehicleType::active()->get(['id', 'name']),
            'tripTypes' => collect(TripType::cases())->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()]),
            'ruleCodes' => collect(TaxiRateRuleCode::cases())->map(fn ($rule) => ['value' => $rule->value, 'label' => $rule->label()]),
            'calculationTypes' => collect(TaxiRateCalculationType::cases())->map(fn ($type) => ['value' => $type->value, 'label' => $type->label()]),
            'defaultCurrency' => TaxiSettings::get('taxi.default_currency') ?? 'INR',
            'fixedVendor' => true,
        ];
    }
}
