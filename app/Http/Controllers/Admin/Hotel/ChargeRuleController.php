<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelChargeRule;
use App\Models\Property;
use App\Services\HotelPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin tax/fee rules (12B.4). Property-scoped, single currency per
 * property. Exclusive charges add to the quote; percentage-included
 * rules are extracted for display only.
 */
class ChargeRuleController extends Controller
{
    public function __construct(protected HotelPricingService $pricing) {}

    public function index(Request $request): Response
    {
        $propertyId = $request->integer('property_id') ?: null;
        $properties = Property::orderBy('name')->get(['id', 'name', 'status', 'currency']);
        $property = $propertyId ? Property::find($propertyId) : $properties->first();

        return Inertia::render('Admin/Hotel/Charges/Index', [
            'properties' => $properties,
            'propertyId' => $property?->id,
            'propertyCurrency' => $property ? $this->pricing->effectiveCurrency($property) : null,
            'rules' => $property
                ? HotelChargeRule::where('property_id', $property->id)->orderBy('sort_order')->orderBy('id')->get()
                : [],
            'chargeTypes' => HotelChargeRule::TYPES,
            'calculations' => HotelChargeRule::CALCULATIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        HotelChargeRule::create($this->validated($request));

        return back()->with('flash', 'Charge rule created.');
    }

    public function update(Request $request, HotelChargeRule $rule): RedirectResponse
    {
        $data = $this->validated($request);

        if ((int) $data['property_id'] !== (int) $rule->property_id) {
            abort(422, 'Charge rule cannot move between properties.');
        }

        $rule->update($data);

        return back()->with('flash', 'Charge rule updated.');
    }

    public function toggle(HotelChargeRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return back()->with('flash', $rule->is_active ? 'Charge rule reactivated.' : 'Charge rule deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'name' => ['required', 'string', 'max:100'],
            'charge_type' => ['required', 'string', Rule::in(HotelChargeRule::TYPES)],
            'calculation' => ['required', 'string', Rule::in(HotelChargeRule::CALCULATIONS)],
            'value' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'included_in_price' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);

        $property = Property::findOrFail($data['property_id']);
        $data['currency'] = $this->pricing->effectiveCurrency($property);

        if ($data['calculation'] === HotelChargeRule::CALC_PERCENTAGE && (float) $data['value'] > 100) {
            abort(422, 'Percentage charges cannot exceed 100%.');
        }

        if (! empty($data['included_in_price']) && $data['calculation'] !== HotelChargeRule::CALC_PERCENTAGE) {
            abort(422, 'Only percentage charges may be flagged included in price.');
        }

        return $data;
    }
}
