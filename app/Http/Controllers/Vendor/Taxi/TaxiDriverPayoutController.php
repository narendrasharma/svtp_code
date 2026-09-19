<?php

namespace App\Http\Controllers\Vendor\Taxi;

use App\Enums\PaymentMethod;
use App\Enums\TaxiDriverPayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\TaxiDriverEarning;
use App\Models\TaxiDriverPayout;
use App\Models\VendorProfile;
use App\Services\TaxiDriverPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor driver payouts (Phase 12A.10).
 *
 * Vendors settle their OWN drivers only: driver ownership, earning
 * ownership and currency are all re-validated server-side. Platform and
 * foreign-vendor rows 404.
 */
class TaxiDriverPayoutController extends Controller
{
    public function __construct(protected TaxiDriverPayoutService $payouts) {}

    protected function profile(Request $request): VendorProfile
    {
        $profile = $request->user()->vendorProfile;
        abort_unless($profile && $profile->is_active, 403, 'Vendor account not eligible for taxi operations.');

        return $profile;
    }

    protected function scopedDriver(Request $request, Driver $driver): Driver
    {
        abort_unless((int) $driver->vendor_profile_id === (int) $this->profile($request)->id, 404);

        return $driver;
    }

    protected function scopedPayout(Request $request, TaxiDriverPayout $payout): TaxiDriverPayout
    {
        abort_unless((int) $payout->vendor_profile_id === (int) $this->profile($request)->id, 404);

        return $payout;
    }

    public function index(Request $request): Response
    {
        $profile = $this->profile($request);

        $payouts = TaxiDriverPayout::with(['driver:id,first_name,last_name'])
            ->where('vendor_profile_id', $profile->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('payout_number', 'like', $term)
                    ->orWhere('payment_reference', 'like', $term)
                    ->orWhereHas('driver', fn ($d) => $d->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Vendor/Taxi/Payouts/Index', [
            'payouts' => $payouts,
            'filters' => $request->only(['search', 'status']),
            'statuses' => collect(TaxiDriverPayoutStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'drivers' => Driver::where('vendor_profile_id', $profile->id)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function create(Request $request): Response
    {
        $profile = $this->profile($request);
        $driver = null;
        $eligible = collect();

        if ($request->filled('driver_id')) {
            $driver = $this->scopedDriver($request, Driver::findOrFail($request->integer('driver_id')));

            $eligible = TaxiDriverEarning::with('booking:id,reference')
                ->where('driver_id', $driver->id)
                ->where('vendor_profile_id', $profile->id)
                ->payable()
                ->whereDoesntHave('payoutItems')
                ->orderBy('earned_at')
                ->limit(200)
                ->get();
        }

        return Inertia::render('Vendor/Taxi/Payouts/Create', [
            'driver' => $driver,
            'eligible' => $eligible,
            'drivers' => Driver::where('vendor_profile_id', $profile->id)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'paymentMethods' => collect(PaymentMethod::cases())->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = $this->profile($request);

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'earning_ids' => ['required', 'array', 'min:1', 'max:200'],
            'earning_ids.*' => ['integer', 'exists:taxi_driver_earnings,id'],
            'payment_method' => ['nullable', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
        ]);

        $driver = $this->scopedDriver($request, Driver::findOrFail($validated['driver_id']));

        // Belt and braces: the service re-validates ownership per earning,
        // but foreign earnings are rejected here with a 404 first.
        $foreign = TaxiDriverEarning::whereIn('id', $validated['earning_ids'])
            ->where(fn ($q) => $q->where('driver_id', '!=', $driver->id)->orWhereNull('vendor_profile_id')->orWhere('vendor_profile_id', '!=', $profile->id))
            ->exists();
        abort_if($foreign, 404);

        $payout = $this->payouts->createPayout(
            $driver->id,
            $validated['earning_ids'],
            $request->user(),
            $validated['payment_method'] ?? null,
            $validated['notes'] ?? null,
            $validated['period_start'] ?? null,
            $validated['period_end'] ?? null,
        );

        return redirect()->route('vendor.taxi.payouts.show', $payout)
            ->with('flash', "Payout {$payout->payout_number} created for {$payout->currency} ".number_format((float) $payout->amount, 2).'.');
    }

    public function show(Request $request, TaxiDriverPayout $payout): Response
    {
        $payout = $this->scopedPayout($request, $payout);
        $payout->load([
            'driver:id,first_name,last_name,phone',
            'creator:id,name', 'payer:id,name',
            'items.earning.booking:id,reference',
        ]);

        return Inertia::render('Vendor/Taxi/Payouts/Show', [
            'payout' => $payout,
            'statuses' => collect(TaxiDriverPayoutStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function markPaid(Request $request, TaxiDriverPayout $payout): RedirectResponse
    {
        $payout = $this->scopedPayout($request, $payout);

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
        $payout = $this->scopedPayout($request, $payout);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $payout = $this->payouts->cancelPayout($payout, $request->user(), $validated['reason'] ?? null);

        return back()->with('flash', "Payout {$payout->payout_number} cancelled. Earnings released back to payable.");
    }
}
