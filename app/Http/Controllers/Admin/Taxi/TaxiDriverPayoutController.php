<?php

namespace App\Http\Controllers\Admin\Taxi;

use App\Enums\PaymentMethod;
use App\Enums\TaxiDriverPayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\TaxiDriverEarning;
use App\Models\TaxiDriverPayout;
use App\Services\TaxiDriverPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin driver payouts (Phase 12A.10).
 *
 * Create batches from payable earnings (totals always server-derived),
 * record off-platform settlement with a reference, or cancel
 * draft/processing batches to release allocations. Paid batches are
 * terminal.
 */
class TaxiDriverPayoutController extends Controller
{
    public function __construct(protected TaxiDriverPayoutService $payouts) {}

    public function index(Request $request): Response
    {
        $payouts = TaxiDriverPayout::with([
            'driver:id,first_name,last_name', 'vendorProfile:id,business_name',
        ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('payout_number', 'like', $term)
                    ->orWhere('payment_reference', 'like', $term)
                    ->orWhereHas('driver', fn ($d) => $d->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_profile_id', $request->integer('vendor_id')))
            ->when($request->filled('driver_id'), fn ($q) => $q->where('driver_id', $request->integer('driver_id')))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Taxi/Payouts/Index', [
            'payouts' => $payouts,
            'filters' => $request->only(['search', 'status', 'vendor_id', 'driver_id']),
            'statuses' => collect(TaxiDriverPayoutStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Request $request): Response
    {
        $driverId = $request->integer('driver_id') ?: null;
        $driver = $driverId ? Driver::with('vendorProfile:id,business_name')->find($driverId) : null;

        $eligible = $driver
            ? TaxiDriverEarning::with('booking:id,reference')
                ->where('driver_id', $driver->id)
                ->payable()
                ->whereDoesntHave('payoutItems')
                ->orderBy('earned_at')
                ->limit(200)
                ->get()
            : collect();

        return Inertia::render('Admin/Taxi/Payouts/Create', [
            'driver' => $driver,
            'drivers' => Driver::orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'eligible' => $eligible,
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            // Any browser-sent total is deliberately ignored: the service
            // re-derives the batch total from the selected earnings.
            'earning_ids' => ['required', 'array', 'min:1', 'max:200'],
            'earning_ids.*' => ['integer', 'exists:taxi_driver_earnings,id'],
            'payment_method' => ['nullable', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
        ]);

        $payout = $this->payouts->createPayout(
            (int) $validated['driver_id'],
            $validated['earning_ids'],
            $request->user(),
            $validated['payment_method'] ?? null,
            $validated['notes'] ?? null,
            $validated['period_start'] ?? null,
            $validated['period_end'] ?? null,
        );

        return redirect()->route('admin.taxi.payouts.show', $payout)
            ->with('flash', "Payout {$payout->payout_number} created for {$payout->currency} ".number_format((float) $payout->amount, 2).'.');
    }

    public function show(TaxiDriverPayout $payout): Response
    {
        $payout->load([
            'driver:id,first_name,last_name,phone,vendor_profile_id',
            'vendorProfile:id,business_name',
            'creator:id,name', 'payer:id,name',
            'items.earning.booking:id,reference',
        ]);

        return Inertia::render('Admin/Taxi/Payouts/Show', [
            'payout' => $payout,
            'statuses' => collect(TaxiDriverPayoutStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function markPaid(Request $request, TaxiDriverPayout $payout): RedirectResponse
    {
        $validated = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payout = $this->payouts->markPaid(
            $payout,
            $request->user(),
            $validated['payment_reference'] ?? null,
            $validated['notes'] ?? null,
        );

        return back()->with('flash', "Payout {$payout->payout_number} marked paid. Allocated earnings settled.");
    }

    public function cancel(Request $request, TaxiDriverPayout $payout): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $payout = $this->payouts->cancelPayout($payout, $request->user(), $validated['reason'] ?? null);

        return back()->with('flash', "Payout {$payout->payout_number} cancelled. Earnings released back to payable.");
    }
}
