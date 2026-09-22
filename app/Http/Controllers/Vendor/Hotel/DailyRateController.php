<?php

namespace App\Http\Controllers\Vendor\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelRatePlan;
use App\Services\HotelPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor daily-rate calendar (12B.4). Every plan lookup resolves
 * ownership through Room Type → Property → vendor profile first.
 */
class DailyRateController extends Controller
{
    public function __construct(protected HotelPricingService $pricing) {}

    protected function scopedPlan(Request $request, int $planId): HotelRatePlan
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);

        $plan = HotelRatePlan::with('roomType.property')->findOrFail($planId);
        abort_unless(
            $plan->roomType !== null
            && $plan->roomType->property !== null
            && (int) $plan->roomType->property->vendor_profile_id === (int) $profile->id,
            404
        );

        return $plan;
    }

    public function index(Request $request): Response
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403);

        $plans = HotelRatePlan::with(['roomType:id,name,property_id', 'property:id,name'])
            ->whereHas('roomType.property', fn ($query) => $query->where('vendor_profile_id', $profile->id))
            ->orderBy('id')
            ->limit(200)
            ->get();

        $requested = $request->integer('rate_plan_id') ?: null;
        $plan = $requested ? $plans->firstWhere('id', $requested) : $plans->first();

        $start = $request->input('start');
        $end = $request->input('end');

        if (! is_string($start) || ! is_string($end)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $start = now()->format('Y-m-01');
            $end = now()->endOfMonth()->toDateString();
        }

        return Inertia::render('Vendor/Hotel/RatePlans/Calendar', [
            'plans' => $plans,
            'plan' => $plan,
            'planId' => $plan?->id,
            'start' => $start,
            'end' => $end,
            'rows' => $plan ? $this->pricing->calendar($plan, $start, $end) : [],
            'maxBulkDays' => $this->pricing->maxBulkDays(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->dayRules());
        $plan = $this->scopedPlan($request, (int) $data['hotel_rate_plan_id']);
        $this->pricing->setDay($plan, $data['rate_date'], $this->ops($data), $request->user());

        return back()->with('flash', 'Daily rate updated.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate($this->dayRules() + [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'clear' => ['sometimes', 'boolean'],
            'weekdays' => ['nullable', 'array', 'max:7'],
            'weekdays.*' => ['integer', 'min:0', 'max:6', 'distinct'],
        ]);

        $plan = $this->scopedPlan($request, (int) $data['hotel_rate_plan_id']);
        $weekdays = $data['weekdays'] ?? null;

        if ($request->boolean('clear')) {
            $deleted = $this->pricing->clearRange($plan, $data['start_date'], $data['end_date'], $request->user(), $weekdays);

            return back()->with('flash', "Overrides cleared ({$deleted} rows removed).");
        }

        $summary = $this->pricing->applyRange($plan, $data['start_date'], $data['end_date'], $this->ops($data), $request->user(), $weekdays);

        return back()->with('flash', "Rates updated for {$summary['dates_affected']} dates.");
    }

    public function clear(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hotel_rate_plan_id' => ['required', 'integer'],
            'rate_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $plan = $this->scopedPlan($request, (int) $data['hotel_rate_plan_id']);
        $this->pricing->clearDay($plan, $data['rate_date'], $request->user());

        return back()->with('flash', 'Override cleared; inherited price restored.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function dayRules(): array
    {
        return [
            'hotel_rate_plan_id' => ['required', 'integer'],
            'rate_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'amount_override' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'extra_adult_override' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'extra_child_override' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'minimum_stay_override' => ['nullable', 'integer', 'min:1', 'max:365'],
            'maximum_stay_override' => ['nullable', 'integer', 'min:1', 'max:365'],
            'stop_sell' => ['sometimes', 'boolean'],
            'closed_to_arrival' => ['sometimes', 'boolean'],
            'closed_to_departure' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{amount_override: ?string, extra_adult_override: ?string, extra_child_override: ?string, minimum_stay_override: ?int, maximum_stay_override: ?int, stop_sell: bool, closed_to_arrival: bool, closed_to_departure: bool, note: ?string}
     */
    protected function ops(array $data): array
    {
        return [
            'amount_override' => isset($data['amount_override']) ? (string) $data['amount_override'] : null,
            'extra_adult_override' => isset($data['extra_adult_override']) ? (string) $data['extra_adult_override'] : null,
            'extra_child_override' => isset($data['extra_child_override']) ? (string) $data['extra_child_override'] : null,
            'minimum_stay_override' => $data['minimum_stay_override'] ?? null,
            'maximum_stay_override' => $data['maximum_stay_override'] ?? null,
            'stop_sell' => (bool) ($data['stop_sell'] ?? false),
            'closed_to_arrival' => (bool) ($data['closed_to_arrival'] ?? false),
            'closed_to_departure' => (bool) ($data['closed_to_departure'] ?? false),
            'note' => $data['note'] ?? null,
        ];
    }
}
