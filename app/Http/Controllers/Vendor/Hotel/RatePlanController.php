<?php

namespace App\Http\Controllers\Vendor\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelRatePlan;
use App\Models\HotelRateSeason;
use App\Models\HotelRoomType;
use App\Models\Property;
use App\Models\VendorProfile;
use App\Services\HotelPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor rate plan + season management (12B.4). Own properties only:
 * property_id is derived server-side from the room type — never from
 * the browser — and every plan/season lookup is ownership-scoped.
 */
class RatePlanController extends Controller
{
    public function __construct(protected HotelPricingService $pricing) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);

        return $profile;
    }

    protected function scopedPlan(Request $request, int $planId): HotelRatePlan
    {
        $profile = $this->profile($request);
        $plan = HotelRatePlan::findOrFail($planId);
        abort_unless(
            $plan->roomType !== null
            && $plan->roomType->property !== null
            && (int) $plan->roomType->property->vendor_profile_id === (int) $profile->id,
            404
        );

        return $plan;
    }

    protected function scopedRoom(Request $request, int $roomTypeId): HotelRoomType
    {
        $profile = $this->profile($request);
        $roomType = HotelRoomType::findOrFail($roomTypeId);
        abort_unless(
            $roomType->property !== null && (int) $roomType->property->vendor_profile_id === (int) $profile->id,
            404
        );

        return $roomType;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);
        $properties = Property::where('vendor_profile_id', $profile->id)->orderBy('name')->get(['id', 'name', 'status', 'currency']);

        $propertyId = $request->integer('property_id') ?: null;
        $property = $propertyId
            ? Property::where('vendor_profile_id', $profile->id)->find($propertyId)
            : $properties->first();

        $roomTypes = $property
            ? HotelRoomType::where('property_id', $property->id)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'status'])
            : collect();

        $roomType = null;
        $requestedRoom = $request->integer('room_type_id') ?: null;

        if ($requestedRoom) {
            $candidate = HotelRoomType::find($requestedRoom);

            if ($candidate && $property && (int) $candidate->property_id === (int) $property->id) {
                $roomType = $candidate;
            }
        } else {
            $roomType = $roomTypes->first();
        }

        return Inertia::render('Vendor/Hotel/RatePlans/Index', [
            'properties' => $properties,
            'roomTypes' => $roomTypes,
            'propertyId' => $property?->id,
            'roomTypeId' => $roomType?->id,
            'propertyCurrency' => $property ? $this->pricing->effectiveCurrency($property) : null,
            'plans' => $roomType
                ? HotelRatePlan::with('seasons')->where('hotel_room_type_id', $roomType->id)->orderBy('sort_order')->orderBy('id')->get()
                : [],
            'mealPlans' => HotelRatePlan::MEAL_PLANS,
            'cancellationModes' => HotelRatePlan::CANCELLATION_MODES,
            'seasonTypes' => HotelRateSeason::ADJUSTMENT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedPlan($request);
        $roomType = $this->scopedRoom($request, (int) $data['hotel_room_type_id']);
        $data['property_id'] = $roomType->property_id;
        $data['currency'] = $this->pricing->effectiveCurrency($roomType->property);
        $this->assertCodeUnique((int) $data['property_id'], $data['code']);

        HotelRatePlan::create($data);

        return back()->with('flash', 'Rate plan created.');
    }

    public function update(Request $request, HotelRatePlan $plan): RedirectResponse
    {
        $plan = $this->scopedPlan($request, $plan->id);
        $data = $this->validatedPlan($request);

        if ((int) $data['hotel_room_type_id'] !== (int) $plan->hotel_room_type_id) {
            abort(422, 'Rate plan cannot move between rooms.');
        }

        $data['property_id'] = $plan->property_id;
        $this->assertCodeUnique((int) $data['property_id'], $data['code'], $plan->id);
        $plan->update($data);

        return back()->with('flash', 'Rate plan updated.');
    }

    public function toggle(Request $request, HotelRatePlan $plan): RedirectResponse
    {
        $plan = $this->scopedPlan($request, $plan->id);
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('flash', $plan->is_active ? 'Rate plan reactivated.' : 'Rate plan deactivated.');
    }

    public function storeSeason(Request $request): RedirectResponse
    {
        $data = $this->validatedSeason($request);
        $this->scopedPlan($request, (int) $data['hotel_rate_plan_id']);
        HotelRateSeason::create($data);

        return back()->with('flash', 'Seasonal rule created.');
    }

    public function updateSeason(Request $request, HotelRateSeason $season): RedirectResponse
    {
        $plan = $this->scopedPlan($request, (int) $request->input('hotel_rate_plan_id', $season->hotel_rate_plan_id));

        if ((int) $season->hotel_rate_plan_id !== (int) $plan->id) {
            abort(404);
        }

        $season->update($this->validatedSeason($request));

        return back()->with('flash', 'Seasonal rule updated.');
    }

    public function toggleSeason(Request $request, HotelRateSeason $season): RedirectResponse
    {
        $this->scopedPlan($request, $season->hotel_rate_plan_id);
        $season->update(['is_active' => ! $season->is_active]);

        return back()->with('flash', 'Seasonal rule updated.');
    }

    public function destroySeason(Request $request, HotelRateSeason $season): RedirectResponse
    {
        $this->scopedPlan($request, $season->hotel_rate_plan_id);
        $season->delete();

        return back()->with('flash', 'Seasonal rule removed.');
    }

    protected function assertCodeUnique(int $propertyId, string $code, ?int $ignoreId = null): void
    {
        $exists = HotelRatePlan::where('property_id', $propertyId)
            ->where('code', $code)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        abort_if($exists, 422, 'This plan code is already used for the property.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedPlan(Request $request): array
    {
        $data = $request->validate([
            'hotel_room_type_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/'],
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
            'hotel_rate_plan_id' => ['required', 'integer'],
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
