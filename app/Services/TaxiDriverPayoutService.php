<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\TaxiDriverEarningStatus;
use App\Enums\TaxiDriverPayoutStatus;
use App\Models\Driver;
use App\Models\TaxiDriverEarning;
use App\Models\TaxiDriverPayout;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Driver payout batches (Phase 12A.10).
 *
 * The user only ever selects eligible earning IDs — totals, currency,
 * ownership and payable state are all re-derived server-side inside a
 * transaction with row locks, so concurrent double-allocation loses to
 * the unique earning_id guard and returns a clean validation error.
 */
class TaxiDriverPayoutService
{
    /**
     * @param  array<int, int>  $earningIds
     */
    public function createPayout(
        int $driverId,
        array $earningIds,
        ?User $actor = null,
        ?string $paymentMethod = null,
        ?string $notes = null,
        ?string $periodStart = null,
        ?string $periodEnd = null,
    ): TaxiDriverPayout {
        $earningIds = array_values(array_unique(array_map('intval', $earningIds)));

        if ($earningIds === []) {
            throw ValidationException::withMessages(['earning_ids' => 'Select at least one earning.']);
        }

        if (count($earningIds) > 200) {
            throw ValidationException::withMessages(['earning_ids' => 'Select at most 200 earnings per payout.']);
        }

        $method = $paymentMethod !== null
            ? PaymentMethod::tryFrom($paymentMethod) ?? PaymentMethod::BankTransfer
            : PaymentMethod::BankTransfer;

        return DB::transaction(function () use ($driverId, $earningIds, $actor, $method, $notes, $periodStart, $periodEnd): TaxiDriverPayout {
            $earnings = TaxiDriverEarning::whereIn('id', $earningIds)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($earnings->count() !== count($earningIds)) {
                throw ValidationException::withMessages(['earning_ids' => 'One or more selected earnings do not exist.']);
            }

            $driver = Driver::findOrFail($driverId);
            $currency = null;
            $total = '0.00';

            foreach ($earnings as $earning) {
                $this->assertEligible($earning, $driverId);

                if ($earning->vendor_profile_id !== $driver->vendor_profile_id) {
                    throw ValidationException::withMessages(['earning_ids' => 'Earnings must belong to the driver’s current vendor or platform ownership.']);
                }

                $currency ??= strtoupper((string) $earning->currency);

                if (strtoupper((string) $earning->currency) !== $currency) {
                    throw ValidationException::withMessages(['earning_ids' => 'All earnings in one payout must share a currency. Split by currency.']);
                }

                $remainder = bcsub((string) $earning->net_earning, (string) $earning->paid_amount, 2);

                if (bccomp($remainder, '0', 2) !== 1) {
                    throw ValidationException::withMessages(['earning_ids' => "Earning {$earning->earning_number} has no unpaid balance."]);
                }

                $total = bcadd($total, $remainder, 2);
            }

            $first = $earnings->firstOrFail();

            $payout = TaxiDriverPayout::create([
                'payout_number' => app(NumberSeriesService::class)->next('taxi_driver_payout'),
                'driver_id' => $driverId,
                'vendor_profile_id' => $first->vendor_profile_id,
                'currency' => $currency,
                'amount' => $total,
                'status' => TaxiDriverPayoutStatus::Draft->value,
                'payment_method' => $method->value,
                'payment_reference' => null,
                'notes' => $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 2000) : null,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'created_by' => $actor?->id,
            ]);

            foreach ($earnings as $earning) {
                $remainder = bcsub((string) $earning->net_earning, (string) $earning->paid_amount, 2);

                try {
                    $payout->items()->create([
                        'earning_id' => $earning->id,
                        'amount' => $remainder,
                    ]);
                } catch (QueryException) {
                    throw ValidationException::withMessages(['earning_ids' => "Earning {$earning->earning_number} was just allocated to another payout."]);
                }
            }

            // Move draft → processing so the batch is visibly in flight
            // while money moves off-platform.
            $payout->forceFill(['status' => TaxiDriverPayoutStatus::Processing->value])->save();

            $this->notifyVendor($payout, 'taxi_driver_payout_created');

            return $payout->refresh();
        });
    }

    public function markPaid(
        TaxiDriverPayout $payout,
        User $actor,
        ?string $paymentReference = null,
        ?string $notes = null,
    ): TaxiDriverPayout {
        return DB::transaction(function () use ($payout, $actor, $paymentReference, $notes): TaxiDriverPayout {
            $locked = TaxiDriverPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $status = $locked->statusEnum();

            if ($status === TaxiDriverPayoutStatus::Paid) {
                return $locked;
            }

            if (! $status->canTransitionTo(TaxiDriverPayoutStatus::Paid)) {
                throw ValidationException::withMessages(['status' => "A {$status->value} payout cannot be marked paid."]);
            }

            $reference = $paymentReference !== null && trim($paymentReference) !== ''
                ? mb_substr(trim($paymentReference), 0, 100)
                : null;

            $locked->forceFill([
                'status' => TaxiDriverPayoutStatus::Paid->value,
                'payment_reference' => $reference ?? $locked->payment_reference,
                'notes' => $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 2000) : $locked->notes,
                'paid_by' => $actor->id,
                'paid_at' => now(),
            ])->save();

            $earningService = app(TaxiDriverEarningService::class);

            foreach ($locked->items()->lockForUpdate()->get() as $item) {
                $earning = TaxiDriverEarning::whereKey($item->earning_id)->lockForUpdate()->firstOrFail();
                $paid = bcadd((string) $earning->paid_amount, (string) $item->amount, 2);

                $earning->forceFill(['paid_amount' => $paid])->save();
                $earningService->refreshFinancialState($earning->refresh());
            }

            $locked->refresh();
            $this->notifyDriver($locked, 'taxi_driver_payout_paid');

            return $locked;
        });
    }

    /**
     * Cancel a draft/processing payout: allocations are deleted so the
     * earnings return to payable. Paid batches are never cancelled —
     * corrections use earning adjustments.
     */
    public function cancelPayout(TaxiDriverPayout $payout, ?User $actor = null, ?string $reason = null): TaxiDriverPayout
    {
        return DB::transaction(function () use ($payout, $reason): TaxiDriverPayout {
            $locked = TaxiDriverPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $status = $locked->statusEnum();

            if ($status === TaxiDriverPayoutStatus::Paid) {
                throw ValidationException::withMessages(['status' => 'Paid payouts cannot be cancelled. Use an earning adjustment.']);
            }

            if (! $status->canTransitionTo(TaxiDriverPayoutStatus::Cancelled)) {
                throw ValidationException::withMessages(['status' => "A {$status->value} payout cannot be cancelled."]);
            }

            $locked->items()->delete();
            $locked->forceFill([
                'status' => TaxiDriverPayoutStatus::Cancelled->value,
                'notes' => trim(($locked->notes ?? '').'\nCancellation: '.($reason ?? 'No reason supplied')),
            ])->save();

            return $locked->refresh();
        });
    }

    // ---- Internals -------------------------------------------------------

    protected function assertEligible(TaxiDriverEarning $earning, int $driverId): void
    {
        if ((int) $earning->driver_id !== (int) $driverId) {
            throw ValidationException::withMessages(['earning_ids' => "Earning {$earning->earning_number} belongs to a different driver."]);
        }

        $status = $earning->status instanceof TaxiDriverEarningStatus
            ? $earning->status
            : TaxiDriverEarningStatus::from((string) $earning->status);

        if ($status === TaxiDriverEarningStatus::Void) {
            throw ValidationException::withMessages(['earning_ids' => "Earning {$earning->earning_number} is void."]);
        }

        if ($status === TaxiDriverEarningStatus::Paid) {
            throw ValidationException::withMessages(['earning_ids' => "Earning {$earning->earning_number} is already paid."]);
        }

        if ($status === TaxiDriverEarningStatus::Pending
            && ($earning->payable_at === null || $earning->payable_at->isFuture())) {
            throw ValidationException::withMessages(['earning_ids' => "Earning {$earning->earning_number} is not payable yet."]);
        }

        if ($earning->payoutItems()->exists()) {
            throw ValidationException::withMessages(['earning_ids' => "Earning {$earning->earning_number} is already allocated to a payout."]);
        }
    }

    protected function notifyDriver(TaxiDriverPayout $payout, string $kind): void
    {
        try {
            $user = $payout->driver?->user;

            if (! $user) {
                return;
            }

            $user->notify(new CrmNotification($kind, [
                'reference' => $payout->payout_number,
                'payout_number' => $payout->payout_number,
                'amount' => number_format((float) $payout->amount, 2),
                'currency' => $payout->currency,
                'payment_reference' => $payout->payment_reference,
            ]));
        } catch (\Throwable) {
        }
    }

    protected function notifyVendor(TaxiDriverPayout $payout, string $kind): void
    {
        try {
            $user = $payout->vendorProfile?->user;

            if (! $user) {
                return;
            }

            $user->notify(new CrmNotification($kind, [
                'reference' => $payout->payout_number,
                'payout_number' => $payout->payout_number,
                'amount' => number_format((float) $payout->amount, 2),
                'currency' => $payout->currency,
                'driver' => $payout->driver?->fullName(),
            ]));
        } catch (\Throwable) {
        }
    }
}
