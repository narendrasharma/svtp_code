<?php

namespace App\Services;

use App\Enums\TaxiBookingStatus;
use App\Enums\TaxiDispatchOfferStatus;
use App\Models\Driver;
use App\Models\TaxiAssignment;
use App\Models\TaxiBooking;
use App\Models\TaxiDispatchOffer;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\CrmNotification;
use App\Support\TaxiSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Controlled auto-dispatch orchestration (Phase 12A.8).
 *
 * Decision support never assigns by itself: offers go to the top
 * ranked candidate, the driver accepts/rejects/times out, and only an
 * explicit acceptance (or a manual operator action) performs the
 * existing assignment flow with full revalidation. Concurrency is
 * guarded by row locks inside transactions; manual assignment always
 * wins and supersedes pending offers.
 */
class TaxiAutoDispatchService
{
    /**
     * @var array<int, string>
     */
    public const REJECT_REASONS = ['unavailable', 'too_far', 'vehicle_issue', 'schedule_conflict', 'other'];

    public function __construct(
        protected TaxiDispatchRecommendationService $recommendations,
        protected TaxiDriverLocationService $tracking,
    ) {}

    public function autoEnabled(): bool
    {
        return TaxiSettings::enabled('taxi.dispatch.auto_enabled');
    }

    public function offerEnabled(): bool
    {
        return TaxiSettings::enabled('taxi.dispatch.offer_enabled');
    }

    public function offerTimeoutSeconds(): int
    {
        return min(600, max(30, (int) (TaxiSettings::get('taxi.dispatch.offer_timeout_seconds') ?? 120)));
    }

    public function maxAttempts(): int
    {
        return min(10, max(1, (int) (TaxiSettings::get('taxi.dispatch.max_offer_attempts') ?? 3)));
    }

    public function requireAcceptance(): bool
    {
        return TaxiSettings::enabled('taxi.dispatch.require_driver_acceptance');
    }

    /**
     * Derived lifecycle state: active (a pending offer exists),
     * exhausted (attempt budget spent, nothing pending), idle.
     */
    public function statusFor(TaxiBooking $booking): string
    {
        if ($this->pendingFor($booking) !== null) {
            return 'active';
        }

        if ($this->attemptsFor($booking) >= $this->maxAttempts()) {
            return 'exhausted';
        }

        return 'idle';
    }

    public function attemptsFor(TaxiBooking $booking): int
    {
        return TaxiDispatchOffer::where('taxi_booking_id', $booking->id)->attempted()->count();
    }

    public function pendingFor(TaxiBooking $booking): ?TaxiDispatchOffer
    {
        return TaxiDispatchOffer::pending()
            ->where('taxi_booking_id', $booking->id)
            ->latest('offered_at')
            ->first();
    }

    /**
     * Explicit operator start. Idempotent while an offer is pending.
     */
    public function startForBooking(TaxiBooking $booking, ?User $actor = null, string $source = 'manual'): TaxiDispatchOffer
    {
        $this->assertStartable($booking);

        return DB::transaction(function () use ($booking, $actor, $source): TaxiDispatchOffer {
            $booking = TaxiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->assertStartable($booking);
            $existing = $this->pendingFor($booking);

            if ($existing !== null) {
                return $existing;
            }

            $offer = $this->offerNext($booking->fresh(), $actor, $source);

            if ($offer === null) {
                throw ValidationException::withMessages([
                    'auto_dispatch' => 'No eligible candidates — the booking stays in the manual queue.',
                ]);
            }

            $this->audit($booking, 'auto_dispatch.started', 'Auto-dispatch started.', $actor);

            return $offer;
        });
    }

    protected function assertStartable(TaxiBooking $booking): void
    {
        if (! $this->autoEnabled()) {
            throw ValidationException::withMessages(['auto_dispatch' => 'Auto-dispatch is disabled.']);
        }

        if (! $this->requireAcceptance()) {
            throw ValidationException::withMessages(['auto_dispatch' => 'Driver acceptance is required in this phase.']);
        }

        if ($booking->status() !== TaxiBookingStatus::Confirmed) {
            throw ValidationException::withMessages(['auto_dispatch' => 'Only confirmed bookings awaiting assignment can start auto-dispatch.']);
        }

        if ($booking->assigned_driver_id !== null
            || $booking->assignments()->open()->exists()) {
            throw ValidationException::withMessages(['auto_dispatch' => 'The booking is already assigned.']);
        }

        if ($this->attemptsFor($booking) >= $this->maxAttempts()) {
            throw ValidationException::withMessages(['auto_dispatch' => 'The attempt budget is spent — assign manually.']);
        }
    }

    /**
     * Create an offer for the next unattempted ranked candidate. At most
     * one offer per invocation; returns null when nobody is available.
     */
    public function offerNext(TaxiBooking $booking, ?User $actor = null, string $source = 'auto'): ?TaxiDispatchOffer
    {
        if ($booking->status() !== TaxiBookingStatus::Confirmed
            || $booking->assigned_driver_id !== null
            || $this->pendingFor($booking) !== null
            || $this->attemptsFor($booking) >= $this->maxAttempts()) {
            return null;
        }

        $attempted = TaxiDispatchOffer::where('taxi_booking_id', $booking->id)
            ->attempted()->pluck('driver_id')->all();

        $result = $this->recommendations->recommend($booking->fresh() ?? $booking);

        foreach ($result['recommendations'] as $candidate) {
            if (in_array($candidate['driver_id'], $attempted, true)) {
                continue;
            }

            $driver = Driver::find($candidate['driver_id']);
            $vehicle = Vehicle::find($candidate['vehicle_id']);

            if ($driver === null || $vehicle === null) {
                continue;
            }

            // Fresh pair check at creation time — the cached ranking may
            // be up to 60s old.
            $pair = app(TaxiAvailabilityService::class)->checkPair($driver, $vehicle, $booking->fresh() ?? $booking);

            if (! $pair['eligible']) {
                continue;
            }

            return DB::transaction(function () use ($booking, $driver, $vehicle, $candidate, $actor, $source): ?TaxiDispatchOffer {
                $lockedBooking = TaxiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
                if ($lockedBooking->status() !== TaxiBookingStatus::Confirmed || $lockedBooking->assigned_driver_id !== null || ! $lockedBooking->pickup_at->eq($booking->pickup_at)) {
                    return null;
                }
                if ($pending = $this->pendingFor($lockedBooking)) {
                    return $pending;
                }
                $offer = TaxiDispatchOffer::create([
                    'taxi_booking_id' => $booking->id,
                    'driver_id' => $driver->id,
                    'vehicle_id' => $vehicle->id,
                    'rank' => $candidate['rank'],
                    'status' => TaxiDispatchOfferStatus::Pending->value,
                    'offered_at' => now(),
                    'expires_at' => now()->addSeconds($this->offerTimeoutSeconds()),
                    'source' => mb_substr($source, 0, 20),
                    'created_by' => $actor?->id,
                ]);

                $this->notifyDriver($driver, 'taxi_dispatch_offer', $booking);
                $this->audit($booking, 'auto_dispatch.offer_created', "Offer #{$offer->id} to {$driver->fullName()} (rank {$candidate['rank']}).", $actor);

                return $offer->fresh();
            });
        }

        return null;
    }

    /**
     * Driver acceptance with full revalidation under row locks.
     */
    public function acceptOffer(TaxiDispatchOffer $offer, Driver $driver): TaxiAssignment
    {
        return DB::transaction(function () use ($offer, $driver): TaxiAssignment {
            TaxiBooking::whereKey($offer->taxi_booking_id)->lockForUpdate()->firstOrFail();
            $locked = TaxiDispatchOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();

            $this->assertRespondable($locked, $driver);

            $booking = TaxiBooking::whereKey($locked->taxi_booking_id)->lockForUpdate()->firstOrFail();

            if ($booking->status() !== TaxiBookingStatus::Confirmed || $booking->assigned_driver_id !== null) {
                throw ValidationException::withMessages(['offer' => 'The booking is no longer available for assignment.']);
            }

            $vehicle = Vehicle::findOrFail($locked->vehicle_id);

            $assignment = app(TaxiBookingService::class)->assign(
                $booking->fresh(), $driver, $vehicle, null, null, $locked->id,
            );

            $locked->forceFill([
                'status' => TaxiDispatchOfferStatus::Accepted->value,
                'responded_at' => now(),
                'accepted_at' => now(),
                'taxi_assignment_id' => $assignment->id,
            ])->save();

            $this->audit($booking, 'auto_dispatch.offer_accepted', "Offer #{$locked->id} accepted by {$driver->fullName()}.", $driver->user);

            return $assignment;
        });
    }

    public function rejectOffer(TaxiDispatchOffer $offer, Driver $driver, ?string $reason = null): TaxiDispatchOffer
    {
        return DB::transaction(function () use ($offer, $driver, $reason): TaxiDispatchOffer {
            TaxiBooking::whereKey($offer->taxi_booking_id)->lockForUpdate()->firstOrFail();
            $locked = TaxiDispatchOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();

            $this->assertRespondable($locked, $driver);

            if ($reason !== null && ! in_array($reason, self::REJECT_REASONS, true)) {
                throw ValidationException::withMessages(['reason' => 'Unknown rejection reason.']);
            }

            $locked->forceFill([
                'status' => TaxiDispatchOfferStatus::Rejected->value,
                'responded_at' => now(),
                'rejected_at' => now(),
                'response_reason' => $reason,
            ])->save();

            $booking = $locked->booking()->firstOrFail();
            $this->notifyVendor($booking, 'taxi_dispatch_updated');
            $this->audit($booking, 'auto_dispatch.offer_rejected', "Offer #{$locked->id} rejected by {$driver->fullName()}.", $driver->user);

            $this->offerNext($booking->fresh() ?? $booking, null, 'auto');

            return $locked->fresh();
        });
    }

    protected function assertRespondable(TaxiDispatchOffer $offer, Driver $driver): void
    {
        if (! $this->offerEnabled()) {
            throw ValidationException::withMessages(['offer' => 'Driver offers are disabled.']);
        }

        if ((int) $offer->driver_id !== (int) $driver->id) {
            throw ValidationException::withMessages(['offer' => 'This offer belongs to another driver.']);
        }

        if ($offer->status() !== TaxiDispatchOfferStatus::Pending) {
            throw ValidationException::withMessages(['offer' => 'This offer is no longer pending.']);
        }

        if ($offer->expires_at !== null && $offer->expires_at->isPast()) {
            throw ValidationException::withMessages(['offer' => 'This offer has expired.']);
        }

        if (! $driver->is_active || $driver->employment_status !== 'active') {
            throw ValidationException::withMessages(['offer' => 'This driver account is not active.']);
        }
    }

    /**
     * Scheduler entry point: expire due offers (chunked, idempotent) and
     * advance each affected booking by one candidate.
     *
     * @return array{expired: int, advanced: int}
     */
    public function expireDueOffers(int $limit = 100): array
    {
        $expired = 0;
        $advanced = 0;

        TaxiDispatchOffer::pending()
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(100, function ($offers) use (&$expired, &$advanced, $limit): bool {
                foreach ($offers as $offer) {
                    if ($expired >= $limit) {
                        return false;
                    }

                    if ($this->expireOne($offer)) {
                        $expired++;
                    }

                    $booking = $offer->booking()->first();

                    if ($booking !== null && $this->offerNext($booking, null, 'auto') !== null) {
                        $advanced++;
                    }
                }

                return true;
            });

        return ['expired' => $expired, 'advanced' => $advanced];
    }

    protected function expireOne(TaxiDispatchOffer $offer): bool
    {
        return DB::transaction(function () use ($offer): bool {
            TaxiBooking::whereKey($offer->taxi_booking_id)->lockForUpdate()->first();
            $locked = TaxiDispatchOffer::whereKey($offer->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status() !== TaxiDispatchOfferStatus::Pending) {
                return false;
            }

            if ($locked->expires_at !== null && ! $locked->expires_at->isPast()) {
                return false;
            }

            $locked->forceFill([
                'status' => TaxiDispatchOfferStatus::Expired->value,
                'responded_at' => now(),
                'expired_at' => now(),
            ])->save();

            $booking = $locked->booking()->first();

            if ($booking !== null) {
                $driver = $locked->driver()->first();

                if ($driver?->user) {
                    $driver->user->notify(new CrmNotification('taxi_dispatch_offer_cancelled', [
                        'taxi_booking_id' => $booking->id,
                        'reference' => $booking->reference,
                    ]));
                }

                $this->audit($booking, 'auto_dispatch.offer_expired', "Offer #{$locked->id} expired.", null);
            }

            return true;
        });
    }

    /**
     * Manual operator stop: cancel pending offers, audit, notify.
     */
    public function stopForBooking(TaxiBooking $booking, ?User $actor = null): void
    {
        DB::transaction(function () use ($booking, $actor): void {
            TaxiBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $pending = TaxiDispatchOffer::pending()->where('taxi_booking_id', $booking->id)->get();

            foreach ($pending as $offer) {
                $offer->forceFill([
                    'status' => TaxiDispatchOfferStatus::Cancelled->value,
                    'responded_at' => now(),
                ])->save();

                $driver = $offer->driver()->first();

                if ($driver?->user) {
                    $driver->user->notify(new CrmNotification('taxi_dispatch_offer_cancelled', [
                        'taxi_booking_id' => $booking->id,
                        'reference' => $booking->reference,
                    ]));
                }
            }

            if ($pending->isNotEmpty()) {
                $this->audit($booking, 'auto_dispatch.stopped', "Auto-dispatch stopped ({$pending->count()} pending offer(s) cancelled).", $actor);
            }
        });
    }

    /**
     * Called after any successful assignment: pending offers for other
     * drivers are superseded (manual authority always wins).
     */
    public function handleAssignedBooking(TaxiBooking $booking, ?int $acceptedOfferId = null): void
    {
        $pending = TaxiDispatchOffer::pending()->where('taxi_booking_id', $booking->id)->get();

        foreach ($pending as $offer) {
            if ($acceptedOfferId !== null && (int) $offer->id === $acceptedOfferId) {
                continue;
            }

            $offer->forceFill([
                'status' => TaxiDispatchOfferStatus::Cancelled->value,
                'responded_at' => now(),
            ])->save();

            $driver = $offer->driver()->first();

            if ($driver?->user) {
                $driver->user->notify(new CrmNotification('taxi_dispatch_offer_cancelled', [
                    'taxi_booking_id' => $booking->id,
                    'reference' => $booking->reference,
                ]));
            }
        }

        if ($pending->isNotEmpty()) {
            $this->audit($booking, 'auto_dispatch.superseded', "Booking assigned — {$pending->count()} pending offer(s) superseded.", null);
        }
    }

    /**
     * Called when a booking is cancelled: pending offers end with it.
     */
    public function handleCancelledBooking(TaxiBooking $booking): void
    {
        $pending = TaxiDispatchOffer::pending()->where('taxi_booking_id', $booking->id)->get();

        foreach ($pending as $offer) {
            $offer->forceFill([
                'status' => TaxiDispatchOfferStatus::Cancelled->value,
                'responded_at' => now(),
            ])->save();
        }

        if ($pending->isNotEmpty()) {
            $this->audit($booking, 'auto_dispatch.superseded', "Booking cancelled — {$pending->count()} pending offer(s) cancelled.", null);
        }
    }

    protected function notifyDriver(Driver $driver, string $kind, TaxiBooking $booking): void
    {
        $user = $driver->user;

        if (! $user) {
            return;
        }

        $user->notify(new CrmNotification($kind, [
            'taxi_booking_id' => $booking->id,
            'reference' => $booking->reference,
            'pickup_at' => $booking->pickup_at->toDateTimeString(),
            'pickup_address' => $booking->pickup_address,
            'drop_address' => $booking->drop_address,
        ]));
    }

    protected function notifyVendor(TaxiBooking $booking, string $kind): void
    {
        $user = $booking->vendorProfile?->user;

        if (! $user) {
            return;
        }

        $user->notify(new CrmNotification($kind, [
            'taxi_booking_id' => $booking->id,
            'reference' => $booking->reference,
            'status' => $booking->status,
        ]));
    }

    protected function audit(TaxiBooking $booking, string $event, string $description, ?User $actor): void
    {
        try {
            app(ActivityLogger::class)->log($event, 'taxi', $description, $booking, null, null, $actor);
        } catch (\Throwable) {
            // Audit must never break dispatch writes.
        }
    }
}
