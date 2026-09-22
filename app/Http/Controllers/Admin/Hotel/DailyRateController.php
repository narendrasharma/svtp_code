<?php

namespace App\Http\Controllers\Admin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\HotelRatePlan;
use App\Services\HotelPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin daily-rate calendar (12B.4). Pricing-only companion to the
 * 12B.3 inventory calendar: overrides, restrictions and plan
 * stop-sell per date. Sparse rows — inherited dates need no row.
 */
class DailyRateController extends Controller
{
    public function __construct(protected HotelPricingService $pricing) {}

    public function index(Request $request): Response
    {
        $planId = $request->integer('rate_plan_id') ?: null;
        $plan = $planId ? HotelRatePlan::with(['roomType:id,name,property_id', 'property:id,name,currency'])->find($planId) : null;

        $start = $request->input('start');
        $end = $request->input('end');

        if (! is_string($start) || ! is_string($end)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $start = now()->format('Y-m-01');
            $end = now()->endOfMonth()->toDateString();
        }

        return Inertia::render('Admin/Hotel/RatePlans/Calendar', [
            'plans' => HotelRatePlan::with(['roomType:id,name,property_id', 'property:id,name'])->orderBy('id')->limit(200)->get(),
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
        $plan = HotelRatePlan::findOrFail($data['hotel_rate_plan_id']);
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

        $plan = HotelRatePlan::findOrFail($data['hotel_rate_plan_id']);
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
            'hotel_rate_plan_id' => ['required', 'integer', 'exists:hotel_rate_plans,id'],
            'rate_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $plan = HotelRatePlan::findOrFail($data['hotel_rate_plan_id']);
        $this->pricing->clearDay($plan, $data['rate_date'], $request->user());

        return back()->with('flash', 'Override cleared; inherited price restored.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function dayRules(): array
    {
        return [
            'hotel_rate_plan_id' => ['required', 'integer', 'exists:hotel_rate_plans,id'],
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
