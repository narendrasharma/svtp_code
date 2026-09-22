<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelRatePlan;
use App\Models\HotelRateSeason;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Services\HotelPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin rate plan + season management (12B.4). Platform-wide access;
 * plan ownership always derives Room Type → Property server-side.
 * No hard delete for plans (historical config); seasons may be
 * removed as replaceable rules.
 */
class RatePlanController extends Controller
{
    public function __construct(protected HotelPricingService $pricing) {}

    public function index(Request $request): Response
    {
        $propertyId = $request->integer('property_id') ?: null;
        $roomTypeId = $request->integer('room_type_id') ?: null;

        $properties = Property::orderBy('name')->get(['id', 'name', 'status', 'currency']);
        $property = $propertyId ? Property::find($propertyId) : $properties->first();

        $roomTypes = $property
            ? HotelRoomType::where('property_id', $property->id)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'status'])
            : collect();

        $roomType = $roomTypeId ? HotelRoomType::find($roomTypeId) : $roomTypes->first();

        if ($roomType && $property && (int) $roomType->property_id !== (int) $property->id) {
            $roomType = null;
        }

        $plans = $roomType
            ? HotelRatePlan::with('seasons')->where('hotel_room_type_id', $roomType->id)->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        return Inertia::render('Admin/Hotel/RatePlans/Index', [
            'properties' => $properties,
            'roomTypes' => $roomTypes,
            'propertyId' => $property?->id,
            'roomTypeId' => $roomType?->id,
            'propertyCurrency' => $property ? $this->pricing->effectiveCurrency($property) : null,
            'plans' => $plans,
            'mealPlans' => HotelRatePlan::MEAL_PLANS,
            'cancellationModes' => HotelRatePlan::CANCELLATION_MODES,
            'seasonTypes' => HotelRateSeason::ADJUSTMENT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPlan($request);
        $roomType = HotelRoomType::findOrFail($data['hotel_room_type_id']);
        abort_unless((int) $roomType->property_id === (int) $data['property_id'], 422, 'Room type does not belong to the property.');

        $property = Property::findOrFail($data['property_id']);
        $data['currency'] = $this->pricing->effectiveCurrency($property);

        HotelRatePlan::create($data);

        return back()->with('flash', 'Rate plan created.');
    }

    public function update(Request $request, HotelRatePlan $plan): RedirectResponse
    {
        $data = $this->validatedPlan($request, $plan);

        if ((int) $data['property_id'] !== (int) $plan->property_id
            || (int) $data['hotel_room_type_id'] !== (int) $plan->hotel_room_type_id) {
            abort(422, 'Rate plan cannot move between rooms.');
        }

        $plan->update($data);

        return back()->with('flash', 'Rate plan updated.');
    }

    public function toggle(HotelRatePlan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('flash', $plan->is_active ? 'Rate plan reactivated.' : 'Rate plan deactivated; past quotes unaffected.');
    }

    public function storeSeason(Request $request): RedirectResponse
    {
        $data = $this->validatedSeason($request);
        HotelRateSeason::create($data);

        return back()->with('flash', 'Seasonal rule created.');
    }

    public function updateSeason(Request $request, HotelRateSeason $season): RedirectResponse
    {
        $data = $this->validatedSeason($request);

        if ((int) $data['hotel_rate_plan_id'] !== (int) $season->hotel_rate_plan_id) {
            abort(422, 'Season cannot move between rate plans.');
        }

        $season->update($data);

        return back()->with('flash', 'Seasonal rule updated.');
    }

    public function toggleSeason(HotelRateSeason $season): RedirectResponse
    {
        $season->update(['is_active' => ! $season->is_active]);

        return back()->with('flash', $season->is_active ? 'Seasonal rule reactivated.' : 'Seasonal rule deactivated.');
    }

    public function destroySeason(HotelRateSeason $season): RedirectResponse
    {
        $season->delete();

        return back()->with('flash', 'Seasonal rule removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedPlan(Request $request, ?HotelRatePlan $plan = null): array
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'hotel_room_type_id' => ['required', 'integer', 'exists:hotel_room_types,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/',
                Rule::unique('hotel_rate_plans', 'code')
                    ->where('property_id', (int) $request->input('property_id'))
                    ->ignore($plan?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'meal_plan' => ['required', 'string', Rule::in(HotelRatePlan::MEAL_PLANS)],
            'cancellation_mode' => ['required', 'string', Rule::in(HotelRatePlan::CANCELLATION_MODES)],
            'cancellation_note' => ['nullable', 'string', 'max:500'],
            'base_adults' => ['required', 'integer', 'min:1', 'max:20'],
            'base_children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'base_rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'extra_adult_rate' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'extra_child_rate' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:365'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
        ]);

        if (isset($data['minimum_stay'], $data['maximum_stay']) && $data['maximum_stay'] < $data['minimum_stay']) {
            abort(422, 'Maximum stay must cover the minimum stay.');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedSeason(Request $request): array
    {
        $data = $request->validate([
            'hotel_rate_plan_id' => ['required', 'integer', 'exists:hotel_rate_plans,id'],
            'name' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'adjustment_type' => ['required', 'string', Rule::in(HotelRateSeason::ADJUSTMENT_TYPES)],
            'adjustment_value' => ['required', 'numeric'],
            'priority' => ['sometimes', 'integer', 'min:-1000', 'max:1000'],
            'applicable_weekdays' => ['nullable', 'array', 'max:7'],
            'applicable_weekdays.*' => ['integer', 'min:0', 'max:6', 'distinct'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $value = (float) $data['adjustment_value'];

        $bounds = match ($data['adjustment_type']) {
            HotelRateSeason::ADJUST_PERCENTAGE => [-100, 500],
            HotelRateSeason::ADJUST_FIXED_PRICE => [0, 99999999.99],
            default => [-1000000, 1000000],
        };

        if ($value < $bounds[0] || $value > $bounds[1]) {
            abort(422, 'Adjustment value is outside the allowed range for its type.');
        }

        return $data;
    }
}
