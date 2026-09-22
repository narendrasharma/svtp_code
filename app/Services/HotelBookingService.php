<?php

namespace App\Services;

use App\Enums\HotelBookingStatus;
use App\Enums\HotelPaymentStatus;
use App\Enums\HotelReservationStatus;
use App\Models\HotelBooking;
use App\Models\HotelRatePlan;
use App\Models\HotelRoomType;
use App\Models\User;
use App\Notifications\CrmNotification;
use App\Support\HotelSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotelBookingService
{
    public function __construct(protected HotelPricingService $pricing, protected HotelAvailabilityService $availability, protected ActivityLogger $activity) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $customer = null, ?User $actor = null): HotelBooking
    {
        $this->assertEnabled();
        $this->assertContact($data);

        $key = isset($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null;
        if ($key === '') {
            $key = null;
        }

        $booking = DB::transaction(function () use ($data, $customer, $actor, $key): HotelBooking {
            if ($key !== null && ($existing = HotelBooking::where('idempotency_key', $key)->first())) {
                return $existing->load('items.reservationNights');
            }

            $roomType = HotelRoomType::query()->whereKey((int) $data['room_type_id'])->lockForUpdate()->first();
            if (! $roomType) {
                throw ValidationException::withMessages(['room_type_id' => 'The selected room is no longer available.']);
            }

            $plan = HotelRatePlan::query()->whereKey((int) $data['rate_plan_id'])->first();
            if (! $plan || (int) $plan->hotel_room_type_id !== $roomType->id) {
                throw ValidationException::withMessages(['rate_plan_id' => 'The selected rate plan does not belong to this room.']);
            }

            $rooms = (int) $data['rooms'];
            $this->assertRoomLimit($rooms);
            $property = $plan->property()->firstOrFail();
            if ((string) $data['check_in'] < $this->availability->propertyToday($property)->toDateString()) {
                throw ValidationException::withMessages(['check_in' => 'Check-in cannot be in the past.']);
            }
            $quote = $this->pricing->quote($plan, (string) $data['check_in'], (string) $data['check_out'], $rooms, (int) $data['adults'], (int) ($data['children'] ?? 0), true);
            $fingerprint = self::fingerprint($quote);

            if (! empty($data['quote_fingerprint']) && ! hash_equals((string) $data['quote_fingerprint'], $fingerprint)) {
                throw ValidationException::withMessages(['quote_fingerprint' => 'The price or availability changed. Please review the updated total.']);
            }

            if (! $quote['available']) {
                throw ValidationException::withMessages(['availability' => $quote['unavailable_reason'] ?? 'The selected stay is no longer available.']);
            }

            $vendor = $property->vendorProfile;
            $status = $this->initialStatus();
            $snapshot = $quote + [
                'quote_fingerprint' => $fingerprint,
                'property_name' => $property->name,
                'property_address' => trim(implode(', ', array_filter([$property->address_line_1, $property->city?->name, $property->state?->name]))),
                'room_type_name' => $roomType->name,
                'rate_plan_name' => $plan->name,
                'meal_plan' => $plan->meal_plan,
                'cancellation_mode' => $plan->cancellation_mode,
                'cancellation_policy' => [
                    'mode' => $plan->cancellation_mode,
                    'free_until_hours' => 48,
                    'fee_type' => $plan->cancellation_mode === HotelRatePlan::CANCEL_NON_REFUNDABLE ? 'full_amount' : 'first_night',
                    'fee_value' => $plan->cancellation_mode === HotelRatePlan::CANCEL_NON_REFUNDABLE ? 100 : null,
                    'summary' => $plan->cancellation_note ?: ($plan->cancellation_mode === HotelRatePlan::CANCEL_NON_REFUNDABLE ? 'Non-refundable' : 'Free cancellation until 48 hours before check-in; then first-night charge.'),
                ],
            ];
            $taxes = $this->chargeTotal($quote['taxes']);
            $fees = $this->chargeTotal($quote['fees']);

            $booking = HotelBooking::create([
                'booking_number' => app(NumberSeriesService::class)->next('hotel_reservation'),
                'idempotency_key' => $key,
                'user_id' => $customer?->id,
                'vendor_profile_id' => $property->vendor_profile_id,
                'property_id' => $property->id,
                'property_name_snapshot' => $property->name,
                'vendor_name_snapshot' => $vendor?->business_name,
                'status' => $status,
                'payment_status' => HotelPaymentStatus::Unpaid,
                'currency' => $quote['currency'],
                'check_in' => $quote['check_in'], 'check_out' => $quote['check_out'],
                'nights' => $quote['nights_count'], 'rooms_count' => $rooms,
                'adults' => $quote['adults'], 'children' => $quote['children'],
                'guest_name' => trim((string) $data['guest_name']),
                'guest_email' => trim((string) $data['guest_email']),
                'guest_phone' => trim((string) $data['guest_phone']),
                'special_requests' => $this->cleanRequest($data['special_requests'] ?? null),
                'subtotal' => $quote['subtotal'], 'taxes' => $taxes, 'fees' => $fees, 'total' => $quote['total'],
                'pricing_snapshot' => $snapshot,
                'terms_accepted_at' => ! empty($data['terms_accepted']) ? now() : null,
                'booked_at' => now(), 'confirmed_at' => $status === HotelBookingStatus::Confirmed->value ? now() : null,
            ]);

            $item = $booking->items()->create([
                'room_type_id' => $roomType->id, 'rate_plan_id' => $plan->id,
                'room_type_name_snapshot' => $roomType->name, 'rate_plan_name_snapshot' => $plan->name,
                'meal_plan_snapshot' => $plan->meal_plan, 'cancellation_mode_snapshot' => $plan->cancellation_mode,
                'quantity' => $rooms, 'adults' => $quote['adults'], 'children' => $quote['children'],
                'check_in' => $quote['check_in'], 'check_out' => $quote['check_out'], 'nights' => $quote['nights_count'],
                'currency' => $quote['currency'], 'subtotal' => $quote['subtotal'], 'taxes' => $taxes, 'fees' => $fees,
                'total' => $quote['total'], 'pricing_snapshot' => $snapshot, 'status' => $status,
            ]);

            foreach ($this->availability->nights($quote['check_in'], $quote['check_out']) as $date) {
                $item->reservationNights()->create([
                    'room_type_id' => $roomType->id, 'stay_date' => $date, 'quantity' => $rooms,
                    'status' => $this->reservationStatus($status),
                ]);
            }

            $this->activity->log('hotel_booking.created', 'hotels', "Hotel booking {$booking->booking_number} created.", $booking, null, ['booking_number' => $booking->booking_number, 'status' => $status], $actor ?? $customer);

            return $booking->load('items.reservationNights');
        });

        if ($booking->wasRecentlyCreated) {
            $this->notify($booking);
        }

        return $booking;
    }

    public static function fingerprint(array $quote): string
    {
        $stable = $quote;
        unset($stable['calculated_at'], $stable['available'], $stable['unavailable_reason'], $stable['min_available_rooms']);

        return hash('sha256', json_encode($stable, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function changeStatus(HotelBooking $booking, HotelBookingStatus $to, ?User $actor = null): HotelBooking
    {
        $from = $booking->status;
        $allowed = [
            HotelBookingStatus::Pending->value => [HotelBookingStatus::Confirmed, HotelBookingStatus::Cancelled],
            HotelBookingStatus::Confirmed->value => [HotelBookingStatus::CheckedIn, HotelBookingStatus::Cancelled, HotelBookingStatus::NoShow],
            HotelBookingStatus::CheckedIn->value => [HotelBookingStatus::CheckedOut],
            HotelBookingStatus::CheckedOut->value => [HotelBookingStatus::Completed],
        ];
        if (! in_array($to, $allowed[$from->value] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Cannot change {$from->value} booking to {$to->value}."]);
        }

        return DB::transaction(function () use ($booking, $from, $to, $actor): HotelBooking {
            $booking->update(['status' => $to, 'confirmed_at' => $to === HotelBookingStatus::Confirmed ? now() : $booking->confirmed_at, 'cancelled_at' => $to === HotelBookingStatus::Cancelled ? now() : $booking->cancelled_at, 'completed_at' => $to === HotelBookingStatus::Completed ? now() : $booking->completed_at]);
            $booking->items()->update(['status' => $to->value]);
            if ($to === HotelBookingStatus::Cancelled) {
                $booking->reservationNights()->update(['status' => HotelReservationStatus::Cancelled]);
            } else {
                $booking->reservationNights()->update(['status' => $this->reservationStatus($to->value)]);
            }
            $this->activity->log('hotel_booking.status_changed', 'hotels', "Hotel booking {$booking->booking_number}: {$from->value} → {$to->value}.", $booking, ['status' => $from->value], ['status' => $to->value], $actor);

            return $booking->refresh();
        });
    }

    protected function assertEnabled(): void
    {
        if (! HotelSettings::enabled('hotel.booking.enabled')) {
            throw ValidationException::withMessages(['booking' => 'Hotel booking is currently unavailable.']);
        }
    }

    /** @param array<string, mixed> $data */
    protected function assertContact(array $data): void
    {
        foreach (['room_type_id', 'rate_plan_id', 'check_in', 'check_out', 'rooms', 'adults', 'guest_name', 'guest_email', 'guest_phone'] as $field) {
            if (! array_key_exists($field, $data) || trim((string) $data[$field]) === '') {
                throw ValidationException::withMessages([$field => 'This field is required.']);
            }
        }
        if (HotelSettings::enabled('hotel.booking.require_terms_acceptance') && empty($data['terms_accepted'])) {
            throw ValidationException::withMessages(['terms_accepted' => 'Please accept the booking terms.']);
        }
    }

    protected function assertRoomLimit(int $rooms): void
    {
        $max = max(1, (int) HotelSettings::get('hotel.booking.max_rooms_per_booking'));
        if ($rooms < 1 || $rooms > $max) {
            throw ValidationException::withMessages(['rooms' => "You can book between 1 and {$max} rooms."]);
        }
    }

    protected function initialStatus(): string
    {
        $status = (string) HotelSettings::get('hotel.booking.default_status');

        return in_array($status, [HotelBookingStatus::Pending->value, HotelBookingStatus::Confirmed->value], true) ? $status : HotelBookingStatus::Confirmed->value;
    }

    protected function reservationStatus(string $bookingStatus): string
    {
        if ($bookingStatus === HotelBookingStatus::NoShow->value) {
            return HotelReservationStatus::Cancelled->value;
        }

        return $bookingStatus === HotelBookingStatus::Pending->value ? HotelReservationStatus::Pending->value : HotelReservationStatus::Confirmed->value;
    }

    protected function chargeTotal(array $charges): string
    {
        return array_reduce($charges, fn (string $total, array $charge): string => bcadd($total, (string) $charge['amount'], 2), '0.00');
    }

    protected function cleanRequest(mixed $value): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, 2000);
    }

    protected function notify(HotelBooking $booking): void
    {
        $data = ['hotel_booking_id' => $booking->id, 'booking_number' => $booking->booking_number, 'property' => $booking->property_name_snapshot, 'total' => $booking->total, 'currency' => $booking->currency];
        $booking->user?->notify(new CrmNotification('hotel_booking_created', $data));
        $booking->vendorProfile?->user?->notify(new CrmNotification('hotel_booking_received', $data));
    }
}
