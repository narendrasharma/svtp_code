<?php

namespace App\Services;

use App\Enums\TaxiBookingStatus;
use App\Enums\TaxiDriverEarningCalculation;
use App\Enums\TaxiDriverEarningStatus;
use App\Models\Driver;
use App\Models\TaxiAssignment;
use App\Models\TaxiBooking;
use App\Models\TaxiDriverCompensationPlan;
use App\Models\TaxiDriverEarning;
use App\Models\TaxiDriverEarningAdjustment;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\TaxiSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Driver earnings accounting (Phase 12A.10).
 *
 * Completed operational trip → server-side plan resolution → immutable
 * earning row. One earning per booking (unique taxi_booking_id) owned by
 * the driver holding the final open assignment. Plan edits never touch
 * historical rows: the calculation_snapshot is the permanent record.
 *
 * Money math stays in bc strings at scale 2, matching taxi pricing.
 */
class TaxiDriverEarningService
{
    // ---- Trigger -----------------------------------------------------

    /**
     * Record the earning for a completed booking exactly once.
     *
     * Lock the booking before resolving its final active assignment.
     * Other booking states do not generate earnings in this phase.
     * The unique booking constraint also prevents duplicate records.
     */
    public function recordForBooking(TaxiBooking $booking, ?User $actor = null): ?TaxiDriverEarning
    {
        return DB::transaction(function () use ($booking, $actor): ?TaxiDriverEarning {
            $booking = TaxiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($booking->status() !== TaxiBookingStatus::Completed) {
                return null;
            }

            $existing = TaxiDriverEarning::where('taxi_booking_id', $booking->id)->first();

            return $existing ?? $this->recordCompleted($booking, $actor);
        });
    }

    protected function recordCompleted(TaxiBooking $booking, ?User $actor = null): ?TaxiDriverEarning
    {
        $assignment = $this->finalAssignment($booking);

        if ($assignment === null) {
            return null;
        }

        $driver = $assignment->driver;

        if (! $driver) {
            return null;
        }

        $plan = $this->resolvePlan($driver, $booking);

        if ($plan === null) {
            return null;
        }

        $currency = strtoupper((string) ($booking->currency ?? 'INR'));
        $inputs = $this->bookingInputs($booking);
        $gross = $this->calculateGross($plan, $inputs);

        return $this->storeEarningOnce(
            booking: $booking,
            assignment: $assignment,
            driver: $driver,
            plan: $plan,
            currency: $currency,
            calculationType: $plan->calculation_type instanceof TaxiDriverEarningCalculation
                ? $plan->calculation_type
                : TaxiDriverEarningCalculation::from((string) $plan->calculation_type),
            gross: $gross,
            inputs: $inputs,
            actor: $actor,
        );
    }

    /**
     * No-show compensation seam: conservative default (none) unless the
     * resolved plan carries an explicit no_show_amount.
     */
    protected function recordNoShow(TaxiBooking $booking, ?User $actor = null): ?TaxiDriverEarning
    {
        $assignment = $this->finalAssignment($booking);

        if ($assignment === null || ! $assignment->driver) {
            return null;
        }

        $plan = $this->resolvePlan($assignment->driver, $booking);

        if ($plan === null) {
            return null;
        }

        $amount = $plan->no_show_amount !== null ? (string) $plan->no_show_amount : '0.00';

        if (bccomp($amount, '0', 2) !== 1) {
            return null;
        }

        $currency = strtoupper((string) ($booking->currency ?? 'INR'));
        $inputs = $this->bookingInputs($booking);

        return $this->storeEarningOnce(
            booking: $booking,
            assignment: $assignment,
            driver: $assignment->driver,
            plan: $plan,
            currency: $currency,
            calculationType: TaxiDriverEarningCalculation::NoShowFixed,
            gross: $this->money($amount),
            inputs: $inputs,
            actor: $actor,
        );
    }

    // ---- Plan resolution ---------------------------------------------

    /**
     * Smallest coherent precedence: driver-specific → vendor default →
     * platform default. Currency must match (no FX in this phase) and
     * only active, effective plans qualify. Never merges fragments.
     */
    public function resolvePlan(Driver $driver, TaxiBooking $booking): ?TaxiDriverCompensationPlan
    {
        $currency = strtoupper((string) ($booking->currency ?? 'INR'));
        $now = now();

        $candidates = TaxiDriverCompensationPlan::query()
            ->active()
            ->effective($now)
            ->where('currency', $currency)
            ->where(fn ($q) => $q->whereNull('vehicle_type_id')->orWhere('vehicle_type_id', $booking->vehicle_type_id))
            ->orderByDesc('id')
            ->get();

        $ranked = $candidates
            ->filter(function (TaxiDriverCompensationPlan $plan) use ($driver): bool {
                if ($plan->driver_id !== null) {
                    return (int) $plan->driver_id === (int) $driver->id
                        && $plan->vendor_profile_id === $driver->vendor_profile_id;
                }

                if ($plan->vendor_profile_id !== null) {
                    return $driver->vendor_profile_id !== null
                        && (int) $plan->vendor_profile_id === (int) $driver->vendor_profile_id;
                }

                return true;
            })
            ->sortBy(function (TaxiDriverCompensationPlan $plan) use ($booking): int {
                // Scope rank dominates; a vehicle-type match only breaks
                // ties inside the same scope (0 = match/better).
                $scope = $plan->driver_id !== null ? 0 : ($plan->vendor_profile_id !== null ? 1 : 2);
                $vehicle = ($plan->vehicle_type_id !== null
                    && $booking->vehicle_type_id !== null
                    && (int) $plan->vehicle_type_id === (int) $booking->vehicle_type_id) ? 0 : 1;

                return $scope * 10 + $vehicle;
            })
            ->values();

        return $ranked->first();
    }

    // ---- Calculation --------------------------------------------------

    /**
     * @return array{total: string, base: string, distance_km: string, duration_hours: string, allowance: string}
     */
    public function bookingInputs(TaxiBooking $booking): array
    {
        $snapshot = is_array($booking->pricing_snapshot) ? $booking->pricing_snapshot : [];
        $breakdown = isset($snapshot['breakdown']) && is_array($snapshot['breakdown']) ? $snapshot['breakdown'] : [];

        $minutes = $booking->quoted_duration_minutes !== null ? (float) $booking->quoted_duration_minutes : 0.0;

        return [
            'total' => $this->money($booking->total_amount ?? 0),
            'base' => $this->money($booking->base_amount ?? 0),
            'distance_km' => $this->money($booking->quoted_distance_km ?? 0),
            'duration_hours' => number_format(max(0.0, $minutes) / 60, 4, '.', ''),
            'allowance' => $this->money($breakdown['driver_allowance'] ?? 0),
        ];
    }

    /**
     * @param  array{total: string, base: string, distance_km: string, duration_hours: string, allowance: string}  $inputs
     */
    public function calculateGross(TaxiDriverCompensationPlan $plan, array $inputs): string
    {
        $type = $plan->calculation_type instanceof TaxiDriverEarningCalculation
            ? $plan->calculation_type
            : TaxiDriverEarningCalculation::from((string) $plan->calculation_type);

        $gross = match ($type) {
            TaxiDriverEarningCalculation::Fixed,
            TaxiDriverEarningCalculation::Manual => $this->money($plan->fixed_amount ?? 0),
            TaxiDriverEarningCalculation::PercentTotal => $this->percentOf($inputs['total'], (string) ($plan->percentage ?? 0)),
            TaxiDriverEarningCalculation::PercentBase => $this->percentOf($inputs['base'], (string) ($plan->percentage ?? 0)),
            TaxiDriverEarningCalculation::PerKm => bcmul($this->money($plan->per_km_amount ?? 0), $inputs['distance_km'], 2),
            TaxiDriverEarningCalculation::PerHour => bcmul($this->money($plan->per_hour_amount ?? 0), $inputs['duration_hours'], 2),
            TaxiDriverEarningCalculation::Hybrid => bcadd(
                $this->money($plan->fixed_amount ?? 0),
                $this->percentOf($inputs['total'], (string) ($plan->percentage ?? 0)),
                2
            ),
            TaxiDriverEarningCalculation::NoShowFixed => $this->money($plan->no_show_amount ?? 0),
        };

        if ($plan->allowance_passthrough) {
            $gross = bcadd($gross, $inputs['allowance'], 2);
        }

        $minimum = $this->money($plan->minimum_earning ?? 0);

        if (bccomp($gross, $minimum, 2) === -1) {
            $gross = $minimum;
        }

        return $this->money($gross);
    }

    // ---- Persistence --------------------------------------------------

    protected function storeEarningOnce(
        TaxiBooking $booking,
        TaxiAssignment $assignment,
        Driver $driver,
        TaxiDriverCompensationPlan $plan,
        string $currency,
        TaxiDriverEarningCalculation $calculationType,
        string $gross,
        array $inputs,
        ?User $actor,
    ): TaxiDriverEarning {
        return DB::transaction(function () use ($booking, $assignment, $driver, $plan, $currency, $calculationType, $gross, $inputs, $actor): TaxiDriverEarning {
            $existing = TaxiDriverEarning::where('taxi_booking_id', $booking->id)->first();

            if ($existing) {
                return $existing;
            }

            $earnedAt = now();
            $payableAt = TaxiSettings::earningsAutoPayable()
                ? $earnedAt->copy()->addDays(TaxiSettings::earningsHoldDays())
                : null;

            $attributes = [
                'earning_number' => app(NumberSeriesService::class)->next('taxi_driver_earning'),
                'driver_id' => $driver->id,
                'vendor_profile_id' => $driver->vendor_profile_id,
                'taxi_booking_id' => $booking->id,
                'taxi_assignment_id' => $assignment->id,
                'currency' => $currency,
                'calculation_type' => $calculationType->value,
                'calculation_snapshot' => [
                    'version' => 1,
                    'calculated_at' => $earnedAt->toISOString(),
                    'plan' => [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'vendor_profile_id' => $plan->vendor_profile_id,
                        'driver_id' => $plan->driver_id,
                        'scope' => $plan->isDriverSpecific() ? 'driver' : ($plan->isVendorDefault() ? 'vendor' : 'platform'),
                        'calculation_type' => $calculationType->value,
                    ],
                    'rates' => [
                        'fixed_amount' => $this->money($plan->fixed_amount ?? 0),
                        'percentage' => (string) ($plan->percentage ?? 0),
                        'percentage_base' => $plan->percentage_base ?? 'total',
                        'per_km_amount' => $this->money($plan->per_km_amount ?? 0),
                        'per_hour_amount' => $this->money($plan->per_hour_amount ?? 0),
                        'minimum_earning' => $this->money($plan->minimum_earning ?? 0),
                        'allowance_passthrough' => (bool) $plan->allowance_passthrough,
                    ],
                    'booking' => [
                        'id' => $booking->id,
                        'reference' => $booking->reference,
                        'currency' => $currency,
                        'total_amount' => $inputs['total'],
                        'base_amount' => $inputs['base'],
                        'distance_km' => $inputs['distance_km'],
                        'duration_hours' => $inputs['duration_hours'],
                        'driver_allowance' => $inputs['allowance'],
                    ],
                    'result' => [
                        'minimum_applied' => bccomp($this->calculateGross((clone $plan)->fill(['minimum_earning' => 0]), $inputs), (string) $plan->minimum_earning, 2) < 0,
                        'gross_earning' => $this->money($gross),
                        'adjustments_total' => '0.00',
                        'net_earning' => $this->money($gross),
                        'currency' => $currency,
                    ],
                ],
                'gross_earning' => $this->money($gross),
                'adjustments_total' => '0.00',
                'net_earning' => $this->money($gross),
                'paid_amount' => '0.00',
                'status' => $payableAt === null
                    ? TaxiDriverEarningStatus::Pending->value
                    : ($payableAt->lte($earnedAt)
                        ? TaxiDriverEarningStatus::Payable->value
                        : TaxiDriverEarningStatus::Pending->value),
                'earned_at' => $earnedAt,
                'payable_at' => $payableAt,
                'paid_at' => null,
                'created_by' => $actor?->id,
            ];

            try {
                $earning = TaxiDriverEarning::create($attributes);
            } catch (QueryException) {
                // Lost a creation race: the unique booking guard already
                // holds the winner — return it instead of duplicating.
                $existing = TaxiDriverEarning::where('taxi_booking_id', $booking->id)->first();

                if ($existing) {
                    return $existing;
                }

                throw new \RuntimeException('Driver earning could not be recorded.');
            }

            $this->notifyDriver($earning, 'taxi_driver_earning');

            return $earning;
        });
    }

    // ---- Adjustments ---------------------------------------------------

    public function applyAdjustment(
        TaxiDriverEarning $earning,
        float|string $amount,
        string $kind,
        string $reason,
        ?User $actor = null,
    ): TaxiDriverEarningAdjustment {
        if (! in_array($kind, TaxiDriverEarningAdjustment::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => 'Unknown adjustment kind.']);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for adjustments.']);
        }

        $signed = number_format((float) $amount, 2, '.', '');

        if (bccomp($signed, '0', 2) === 0) {
            throw ValidationException::withMessages(['amount' => 'Adjustment amount cannot be zero.']);
        }

        return DB::transaction(function () use ($earning, $signed, $kind, $reason, $actor): TaxiDriverEarningAdjustment {
            $locked = TaxiDriverEarning::whereKey($earning->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === TaxiDriverEarningStatus::Void) {
                throw ValidationException::withMessages(['earning' => 'Void earnings cannot be adjusted.']);
            }

            if ($locked->status === TaxiDriverEarningStatus::Paid) {
                throw ValidationException::withMessages(['earning' => 'Fully paid earnings cannot be adjusted. Use a new correction entry.']);
            }

            if ($locked->payoutItems()->exists()) {
                throw ValidationException::withMessages(['earning' => 'Cancel the unpaid payout before adjusting this earning.']);
            }

            $net = bcadd((string) $locked->net_earning, $signed, 2);

            if (bccomp($net, (string) $locked->paid_amount, 2) < 0) {
                throw ValidationException::withMessages(['amount' => 'An adjustment cannot reduce net earnings below the amount already paid.']);
            }

            $adjustment = $locked->adjustments()->create([
                'amount' => $signed,
                'kind' => $kind,
                'reason' => mb_substr(trim($reason), 0, 500),
                'created_by' => $actor?->id,
            ]);

            $this->refreshFinancialState($locked->refresh());

            return $adjustment;
        });
    }

    /**
     * Recompute cached totals + status from gross/adjustments/paid.
     * Historical rows are never recalculated from live plans — only
     * from their own stored amounts.
     */
    public function refreshFinancialState(TaxiDriverEarning $earning): TaxiDriverEarning
    {
        return DB::transaction(function () use ($earning): TaxiDriverEarning {
            $locked = TaxiDriverEarning::whereKey($earning->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === TaxiDriverEarningStatus::Void) {
                return $locked;
            }

            $adjustments = number_format((float) $locked->adjustments()->sum('amount'), 2, '.', '');
            $net = bcadd((string) $locked->gross_earning, $adjustments, 2);
            $paid = $this->money($locked->paid_amount);

            $status = $locked->status;

            if (! $status instanceof TaxiDriverEarningStatus) {
                $status = TaxiDriverEarningStatus::from((string) $status);
            }

            if (bccomp($net, '0', 2) !== 1) {
                // Zero/negative nets stay payable-side; they simply carry
                // no unpaid remainder for payout selection.
                $status = $locked->payable_at !== null && $locked->payable_at->lte(now())
                    ? TaxiDriverEarningStatus::Payable
                    : TaxiDriverEarningStatus::Pending;
            } elseif (bccomp($paid, $net, 2) >= 0) {
                $status = TaxiDriverEarningStatus::Paid;
            } elseif (bccomp($paid, '0', 2) === 1) {
                $status = TaxiDriverEarningStatus::PartiallyPaid;
            } else {
                $status = $locked->payable_at !== null && $locked->payable_at->lte(now())
                    ? TaxiDriverEarningStatus::Payable
                    : TaxiDriverEarningStatus::Pending;
            }

            $locked->forceFill([
                'adjustments_total' => $adjustments,
                'net_earning' => $net,
                'paid_amount' => $paid,
                'status' => $status->value,
                'paid_at' => $status === TaxiDriverEarningStatus::Paid ? ($locked->paid_at ?? now()) : null,
            ])->save();

            return $locked->refresh();
        });
    }

    public function markPayable(TaxiDriverEarning $earning, ?User $actor = null): TaxiDriverEarning
    {
        return DB::transaction(function () use ($earning): TaxiDriverEarning {
            $locked = TaxiDriverEarning::whereKey($earning->id)->lockForUpdate()->firstOrFail();

            $status = $locked->status instanceof TaxiDriverEarningStatus
                ? $locked->status
                : TaxiDriverEarningStatus::from((string) $locked->status);

            if (! in_array($status, [TaxiDriverEarningStatus::Pending], true)) {
                throw ValidationException::withMessages(['earning' => 'Only pending earnings can be marked payable.']);
            }

            $locked->forceFill([
                'payable_at' => now(),
                'status' => TaxiDriverEarningStatus::Payable->value,
            ])->save();

            return $locked->refresh();
        });
    }

    public function voidEarning(TaxiDriverEarning $earning, ?User $actor = null, ?string $reason = null): TaxiDriverEarning
    {
        return DB::transaction(function () use ($earning): TaxiDriverEarning {
            $locked = TaxiDriverEarning::whereKey($earning->id)->lockForUpdate()->firstOrFail();

            $status = $locked->status instanceof TaxiDriverEarningStatus
                ? $locked->status
                : TaxiDriverEarningStatus::from((string) $locked->status);

            if ($status === TaxiDriverEarningStatus::Paid || $status === TaxiDriverEarningStatus::PartiallyPaid) {
                throw ValidationException::withMessages(['earning' => 'Earnings with payments cannot be voided.']);
            }

            if ($locked->payoutItems()->exists()) {
                throw ValidationException::withMessages(['earning' => 'Earnings allocated to a payout cannot be voided. Release the payout first.']);
            }

            if (bccomp((string) $locked->paid_amount, '0', 2) === 1) {
                throw ValidationException::withMessages(['earning' => 'Earnings with payments cannot be voided.']);
            }

            $locked->forceFill(['status' => TaxiDriverEarningStatus::Void->value])->save();

            return $locked->refresh();
        });
    }

    // ---- Summaries ------------------------------------------------------

    /**
     * @return array{payable_balance: string, paid_this_month: string, earned_today: string, currency: string}
     */
    public function summaryForDriver(Driver $driver, ?string $currency = null): array
    {
        $query = TaxiDriverEarning::where('driver_id', $driver->id);

        if ($currency !== null) {
            $query->where('currency', $currency);
        }

        $payable = '0.00';
        $paidMonth = '0.00';
        $today = '0.00';
        $resolvedCurrency = $currency ?? (string) ($query->clone()->orderByDesc('id')->value('currency') ?? 'INR');
        $query->where('currency', $resolvedCurrency);
        $monthStart = now()->startOfMonth();
        $dayStart = today();

        $query->orderBy('id')->chunkById(500, function ($rows) use (&$payable, &$paidMonth, &$today, $monthStart, $dayStart): void {
            foreach ($rows as $row) {
                $status = $row->status instanceof TaxiDriverEarningStatus
                    ? $row->status
                    : TaxiDriverEarningStatus::from((string) $row->status);

                if (in_array($status, [TaxiDriverEarningStatus::Payable, TaxiDriverEarningStatus::PartiallyPaid], true)
                    || ($status === TaxiDriverEarningStatus::Pending && $row->payable_at?->lte(now()))) {
                    $payable = bcadd($payable, bcsub((string) $row->net_earning, (string) $row->paid_amount, 2), 2);
                }

                if ($status === TaxiDriverEarningStatus::Paid && $row->paid_at !== null && $row->paid_at->gte($monthStart)) {
                    $paidMonth = bcadd($paidMonth, (string) $row->net_earning, 2);
                }

                if ($row->earned_at !== null && $row->earned_at->gte($dayStart) && $status !== TaxiDriverEarningStatus::Void) {
                    $today = bcadd($today, (string) $row->net_earning, 2);
                }
            }
        });

        return [
            'payable_balance' => $this->money($payable),
            'paid_this_month' => $this->money($paidMonth),
            'earned_today' => $this->money($today),
            'currency' => $resolvedCurrency,
        ];
    }

    // ---- Internals -------------------------------------------------------

    /**
     * Only the final active assignment receives an earning. Closed
     * assignments and stale booking pointers never receive compensation.
     */
    protected function finalAssignment(TaxiBooking $booking): ?TaxiAssignment
    {
        $open = $booking->assignments()->open()->latest('assigned_at')->latest('id')->first();

        if ($open) {
            return $open;
        }

        return null;
    }

    protected function notifyDriver(TaxiDriverEarning $earning, string $kind): void
    {
        try {
            $user = $earning->driver?->user;

            if (! $user) {
                return;
            }

            $user->notify(new CrmNotification($kind, [
                'taxi_booking_id' => $earning->taxi_booking_id,
                'reference' => $earning->booking?->reference ?? $earning->earning_number,
                'earning_number' => $earning->earning_number,
                'amount' => number_format((float) $earning->net_earning, 2),
                'currency' => $earning->currency,
            ]));
        } catch (\Throwable) {
            // Notifications must never break financial writes.
        }
    }

    protected function percentOf(string $base, string $percentage): string
    {
        if (bccomp($this->money($percentage), '0', 2) !== 1) {
            return '0.00';
        }

        return bcdiv(bcmul($base, $this->money($percentage), 4), '100', 2);
    }

    protected function money(float|string|null $value): string
    {
        return number_format(max(0.0, (float) ($value ?? 0)), 2, '.', '');
    }
}
