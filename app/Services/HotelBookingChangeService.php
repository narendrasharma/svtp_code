<?php

namespace App\Services;

use App\Enums\HotelBookingStatus;
use App\Enums\HotelReservationStatus;
use App\Models\HotelBooking;
use App\Models\HotelBookingCancellation;
use App\Models\HotelBookingChange;
use App\Models\HotelBookingRefund;
use App\Models\HotelReservationNight;
use App\Models\HotelRoomType;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\HotelSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotelBookingChangeService
{
    public function __construct(protected HotelPricingService $pricing, protected HotelAvailabilityService $availability, protected ActivityLogger $activity) {}

    /** @return array<string, mixed> */
    public function cancellationQuote(HotelBooking $booking, ?Carbon $at = null): array
    {
        $at ??= now();
        $policy = $booking->pricing_snapshot['cancellation_policy'] ?? ['mode' => $booking->pricing_snapshot['cancellation_mode'] ?? 'flexible', 'free_until_hours' => 48, 'fee_type' => 'first_night'];
        $eligible = in_array($booking->status, [HotelBookingStatus::Pending, HotelBookingStatus::Confirmed], true);
        $property = $booking->property()->first();
        $timezone = $property?->timezone ?: config('app.timezone', 'UTC');
        $checkIn = Carbon::parse($booking->check_in->toDateString().' '.($property?->check_in_time?->format('H:i:s') ?? '14:00:00'), $timezone);
        $freeUntil = $checkIn->copy()->subHours((int) ($policy['free_until_hours'] ?? 48));
        $fee = '0.00';
        if (($policy['mode'] ?? null) === 'non_refundable') {
            $fee = (string) $booking->total;
        } elseif ($at->copy()->setTimezone($timezone)->gt($freeUntil)) {
            $fee = match ($policy['fee_type'] ?? 'first_night') {
                'percentage' => bcdiv(bcmul((string) $booking->total, (string) ($policy['fee_value'] ?? 100), 2), '100', 2),
                'fixed' => min((float) $booking->total, (float) ($policy['fee_value'] ?? 0)),
                'full_amount' => (string) $booking->total,
                default => (string) ($booking->pricing_snapshot['nightly'][0]['night_total'] ?? $booking->total),
            };
        }
        $paid = (string) ($booking->amount_paid ?? '0.00');
        $fee = number_format(min((float) $booking->total, (float) $fee), 2, '.', '');
        $refundable = bcsub((string) max(0, (float) $paid), $fee, 2);
        if (bccomp($refundable, '0.00', 2) < 0) {
            $refundable = '0.00';
        }
        $already = (string) $booking->hotelRefunds()->whereIn('status', ['pending', 'processing', 'completed'])->sum('amount');
        $remaining = bcsub($refundable, $already, 2);
        if (bccomp($remaining, '0.00', 2) < 0) {
            $remaining = '0.00';
        }

        return ['booking_id' => $booking->id, 'eligible' => $eligible, 'cancellation_fee' => $fee, 'refundable_amount' => $refundable, 'already_refunded' => $already, 'remaining_refundable' => $remaining, 'policy_summary' => $policy, 'calculated_at' => $at->toIso8601String(), 'timezone' => $timezone, 'cutoff_at' => $freeUntil->toIso8601String()];
    }

    public function cancel(HotelBooking $booking, string $key, ?User $actor = null, string $reason = 'customer_request', ?string $note = null): HotelBookingCancellation
    {
        if (! HotelSettings::enabled('hotel.cancellation.enabled')) {
            throw ValidationException::withMessages(['booking' => 'Hotel cancellation is disabled.']);
        }

        return DB::transaction(function () use ($booking, $key, $actor, $reason, $note): HotelBookingCancellation {
            $booking = HotelBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($existing = HotelBookingCancellation::where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $quote = $this->cancellationQuote($booking);
            if (! $quote['eligible']) {
                throw ValidationException::withMessages(['booking' => 'This booking cannot be cancelled.']);
            }
            $cancellation = HotelBookingCancellation::create(['hotel_booking_id' => $booking->id, 'idempotency_key' => $key, 'reason_code' => $reason, 'note' => $note === null ? null : mb_substr(strip_tags($note), 0, 2000), 'currency' => $booking->currency, 'cancellation_fee' => $quote['cancellation_fee'], 'refundable_amount' => $quote['remaining_refundable'], 'policy_snapshot' => $quote['policy_summary'], 'cancelled_at' => now(), 'cancelled_by' => $actor?->id]);
            $booking->reservationNights()->whereIn('hotel_reservation_nights.status', HotelReservationNight::consumingStatuses())->update(['hotel_reservation_nights.status' => HotelReservationStatus::Cancelled]);
            $this->setCancelled($booking, $actor);
            $this->activity->log('hotel_booking.cancelled', 'hotels', "Hotel booking {$booking->booking_number} cancelled.", $booking, null, ['cancellation_id' => $cancellation->id, 'refund' => $quote['remaining_refundable']], $actor);
            $this->notify($booking, 'hotel_booking_cancelled', ['refund' => $quote['remaining_refundable']]);

            return $cancellation;
        });
    }

    public function createRefund(HotelBooking $booking, string $key, ?User $actor = null, ?HotelBookingCancellation $cancellation = null): HotelBookingRefund
    {
        return DB::transaction(function () use ($booking, $key, $actor, $cancellation): HotelBookingRefund {
            $booking = HotelBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($existing = HotelBookingRefund::where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $paid = (string) ($booking->amount_paid ?? '0.00');
            $reserved = (string) $booking->hotelRefunds()->whereIn('status', ['pending', 'processing', 'completed'])->sum('amount');
            $entitlement = $cancellation?->refundable_amount ?? (string) $this->cancellationQuote($booking)['remaining_refundable'];
            $amount = min((float) $entitlement, max(0, (float) $paid - (float) $reserved));
            if ($amount <= 0) {
                throw ValidationException::withMessages(['refund' => 'There is no paid refundable balance.']);
            }

            $refund = HotelBookingRefund::create(['hotel_booking_id' => $booking->id, 'hotel_booking_cancellation_id' => $cancellation?->id, 'refund_number' => app(NumberSeriesService::class)->next('hotel_refund'), 'idempotency_key' => $key, 'amount' => number_format($amount, 2, '.', ''), 'currency' => $booking->currency, 'status' => 'pending', 'reason' => 'hotel_cancellation', 'initiated_by' => $actor?->id, 'requested_at' => now()]);
            $this->activity->log('hotel_booking.refund_created', 'hotels', "Hotel refund {$refund->refund_number} created.", $booking, null, ['refund_number' => $refund->refund_number, 'amount' => $refund->amount], $actor);
            $this->notify($booking, 'hotel_refund_pending', ['refund_number' => $refund->refund_number, 'amount' => $refund->amount]);

            return $refund;
        });
    }

    /** @return array<string, mixed> */
    public function rescheduleQuote(HotelBooking $booking, string $checkIn, string $checkOut): array
    {
        if (! in_array($booking->status, [HotelBookingStatus::Pending, HotelBookingStatus::Confirmed], true) || Carbon::parse($checkIn)->lte(now()->startOfDay())) {
            return ['eligible' => false, 'old_dates' => [$booking->check_in->toDateString(), $booking->check_out->toDateString()]];
        }
        $item = $booking->items()->firstOrFail();
        $quote = $this->pricing->quote($item->ratePlan()->firstOrFail(), $checkIn, $checkOut, $item->quantity, $item->adults, $item->children, false);
        $pricingAvailable = $quote['available'];
        $availability = $this->availability->checkRoomType($item->roomType()->firstOrFail(), $checkIn, $checkOut, $item->quantity, null, $booking->id);
        $quote['available'] = $pricingAvailable && $availability['available'];
        $quote['min_available_rooms'] = $availability['min_available_rooms'];
        $old = (string) $booking->total;
        $difference = bcsub((string) $quote['total'], $old, 2);

        return ['eligible' => $quote['available'], 'old_dates' => [$booking->check_in->toDateString(), $booking->check_out->toDateString()], 'new_dates' => [$checkIn, $checkOut], 'old_total' => $old, 'new_total' => $quote['total'], 'difference' => $difference, 'additional_payment_due' => max(0, (float) $difference), 'refundable_difference' => max(0, (float) -$difference), 'availability' => $quote['available'], 'quote_fingerprint' => HotelBookingService::fingerprint($quote), 'quote' => $quote];
    }

    public function reschedule(HotelBooking $booking, string $key, string $checkIn, string $checkOut, ?User $actor = null, ?string $fingerprint = null, ?string $reason = null): HotelBookingChange
    {
        if (! HotelSettings::enabled('hotel.reschedule.enabled')) {
            throw ValidationException::withMessages(['booking' => 'Hotel rescheduling is disabled.']);
        }

        return DB::transaction(function () use ($booking, $key, $checkIn, $checkOut, $actor, $fingerprint, $reason): HotelBookingChange {
            $booking = HotelBooking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($existing = HotelBookingChange::where('idempotency_key', $key)->first()) {
                return $existing;
            }
            $item = $booking->items()->firstOrFail();
            $roomType = HotelRoomType::query()->whereKey($item->room_type_id)->lockForUpdate()->firstOrFail();
            $quote = $this->rescheduleQuote($booking, $checkIn, $checkOut);
            if (! $quote['eligible'] || $fingerprint !== null && ! hash_equals((string) $quote['quote_fingerprint'], $fingerprint)) {
                throw ValidationException::withMessages(['quote_fingerprint' => 'The stay is no longer eligible or the price changed. Please request a new quote.']);
            }
            $oldSnapshot = $booking->pricing_snapshot ?? [];
            $oldIn = $booking->check_in->toDateString();
            $oldOut = $booking->check_out->toDateString();
            $booking->reservationNights()->update(['hotel_reservation_nights.status' => HotelReservationStatus::Cancelled]);
            foreach ($this->availability->nights($checkIn, $checkOut) as $date) {
                $night = $item->reservationNights()->whereDate('stay_date', $date)->first();
                if ($night) {
                    $night->update(['quantity' => $item->quantity, 'status' => HotelReservationStatus::Confirmed]);
                } else {
                    $item->reservationNights()->create(['room_type_id' => $roomType->id, 'stay_date' => $date, 'quantity' => $item->quantity, 'status' => HotelReservationStatus::Confirmed]);
                }
            }
            $newSnapshot = $quote['quote'];
            $item->update(['check_in' => $checkIn, 'check_out' => $checkOut, 'nights' => $newSnapshot['nights_count'], 'subtotal' => $newSnapshot['subtotal'], 'taxes' => $this->chargeTotal($newSnapshot['taxes']), 'fees' => $this->chargeTotal($newSnapshot['fees']), 'total' => $newSnapshot['total'], 'pricing_snapshot' => $newSnapshot]);
            $booking->update(['check_in' => $checkIn, 'check_out' => $checkOut, 'nights' => $newSnapshot['nights_count'], 'subtotal' => $newSnapshot['subtotal'], 'taxes' => $item->taxes, 'fees' => $item->fees, 'total' => $newSnapshot['total'], 'pricing_snapshot' => $newSnapshot]);
            $change = HotelBookingChange::create(['hotel_booking_id' => $booking->id, 'idempotency_key' => $key, 'old_check_in' => $oldIn, 'old_check_out' => $oldOut, 'new_check_in' => $checkIn, 'new_check_out' => $checkOut, 'old_total' => $booking->getOriginal('total'), 'new_total' => $newSnapshot['total'], 'difference' => $quote['difference'], 'currency' => $booking->currency, 'reason' => $reason, 'old_snapshot' => $oldSnapshot, 'new_snapshot' => $newSnapshot, 'requested_by' => $actor?->id]);
            $this->activity->log('hotel_booking.rescheduled', 'hotels', "Hotel booking {$booking->booking_number} rescheduled.", $booking, ['check_in' => $oldIn, 'check_out' => $oldOut], ['check_in' => $checkIn, 'check_out' => $checkOut, 'difference' => $quote['difference']], $actor);
            $this->notify($booking, 'hotel_booking_rescheduled', ['old_check_in' => $oldIn, 'old_check_out' => $oldOut, 'new_check_in' => $checkIn, 'new_check_out' => $checkOut]);

            return $change;
        });
    }

    protected function setCancelled(HotelBooking $booking, ?User $actor): void
    {
        $booking->update(['status' => HotelBookingStatus::Cancelled, 'cancelled_at' => now()]);
        $booking->items()->update(['status' => HotelBookingStatus::Cancelled->value]);
    }

    /** @param array<int, array{amount: string}> $charges */
    protected function chargeTotal(array $charges): string
    {
        return array_reduce($charges, fn (string $total, array $charge): string => bcadd($total, (string) $charge['amount'], 2), '0.00');
    }

    /** @param array<string, mixed> $extra */
    protected function notify(HotelBooking $booking, string $kind, array $extra = []): void
    {
        $data = array_merge(['hotel_booking_id' => $booking->id, 'booking_number' => $booking->booking_number, 'property' => $booking->property_name_snapshot], $extra);
        $booking->user?->notify(new CrmNotification($kind, $data));
        $booking->vendorProfile?->user?->notify(new CrmNotification($kind, $data));
    }
}
