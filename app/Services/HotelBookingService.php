<?php

namespace App\Services;

use App\Enums\HotelBookingStatus;
use App\Enums\HotelPaymentStatus;
use App\Enums\HotelReservationStatus;
use App\Models\HotelBooking;
use App\Models\HotelRatePlan;
use App\Models\HotelRoomType;
use App\Models\Property;
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
            $items = $this->selectionItems($data);
            $propertyId = (int) ($data['property_id'] ?? HotelRatePlan::findOrFail((int) $items[0]['rate_plan_id'])->property_id);
            if ($key !== null && ($existing = HotelBooking::where('idempotency_key', $key)->first())) {
                if ((int) $existing->property_id !== $propertyId || (int) $existing->user_id !== (int) $customer?->id || $existing->guest_email !== trim((string) $data['guest_email'])) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This booking request was already used for a different reservation.']);
                }

                return $existing->load('items.reservationNights');
            }

            HotelRoomType::query()->whereIn('id', array_column($items, 'room_type_id'))->orderBy('id')->lockForUpdate()->get();
            $property = Property::findOrFail($propertyId);
            $selection = $this->quoteSelection($property, $data);
            $validFingerprints = [$selection['quote_fingerprint']];
            if (count($selection['items']) === 1) {
                $validFingerprints[] = self::fingerprint($selection['items'][0]['quote']);
            }
            if (! empty($data['quote_fingerprint']) && ! in_array((string) $data['quote_fingerprint'], $validFingerprints, true)) {
                throw ValidationException::withMessages(['quote_fingerprint' => 'The price or availability changed. Please review the updated total.']);
            }

            $vendor = $property->vendorProfile;
            $status = $this->initialStatus();
            $snapshot = [
                'quote_fingerprint' => $selection['quote_fingerprint'],
                'property_name' => $property->name,
                'property_address' => trim(implode(', ', array_filter([$property->address_line_1, $property->city?->name, $property->state?->name]))),
                'items' => array_map(fn (array $line): array => $line['snapshot'], $selection['items']),
                'nights_count' => $selection['nights_count'],
                'nightly' => $selection['nightly'],
            ];
            if (count($selection['items']) === 1) {
                $snapshot = $selection['items'][0]['snapshot'] + $snapshot;
            } else {
                $snapshot['cancellation_policy'] = ['mode' => 'mixed', 'summary' => 'Cancellation terms vary by room rate.'];
            }

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
                'currency' => $selection['currency'],
                'check_in' => $data['check_in'], 'check_out' => $data['check_out'],
                'nights' => $selection['nights_count'], 'rooms_count' => $selection['rooms_count'],
                'adults' => (int) $data['adults'], 'children' => (int) ($data['children'] ?? 0),
                'guest_name' => trim((string) $data['guest_name']),
                'guest_email' => trim((string) $data['guest_email']),
                'guest_phone' => trim((string) $data['guest_phone']),
                'special_requests' => $this->cleanRequest($data['special_requests'] ?? null),
                'subtotal' => $selection['subtotal'], 'taxes' => $selection['taxes'], 'fees' => $selection['fees'], 'total' => $selection['total'],
                'pricing_snapshot' => $snapshot,
                'terms_accepted_at' => ! empty($data['terms_accepted']) ? now() : null,
                'booked_at' => now(), 'confirmed_at' => $status === HotelBookingStatus::Confirmed->value ? now() : null,
            ]);

            foreach ($selection['items'] as $line) {
                $quote = $line['quote'];
                $item = $booking->items()->create([
                    'room_type_id' => $line['room_type_id'], 'rate_plan_id' => $line['rate_plan_id'],
                    'room_type_name_snapshot' => $line['room_name'], 'rate_plan_name_snapshot' => $line['rate_name'],
                    'meal_plan_snapshot' => $line['meal_plan'], 'cancellation_mode_snapshot' => $line['cancellation_mode'],
                    'quantity' => $line['quantity'], 'adults' => $quote['adults'], 'children' => $quote['children'],
                    'check_in' => $quote['check_in'], 'check_out' => $quote['check_out'], 'nights' => $quote['nights_count'],
                    'currency' => $quote['currency'], 'subtotal' => $quote['subtotal'], 'taxes' => $this->chargeTotal($quote['taxes']), 'fees' => $this->chargeTotal($quote['fees']),
                    'total' => $quote['total'], 'pricing_snapshot' => $line['snapshot'], 'status' => $status,
                ]);

                foreach ($this->availability->nights($quote['check_in'], $quote['check_out']) as $date) {
                    $item->reservationNights()->create([
                        'room_type_id' => $line['room_type_id'], 'stay_date' => $date, 'quantity' => $line['quantity'],
                        'status' => $this->reservationStatus($status),
                    ]);
                }
            }

            $this->activity->log('hotel_booking.created', 'hotels', "Hotel booking {$booking->booking_number} created.", $booking, null, ['booking_number' => $booking->booking_number, 'status' => $status], $actor ?? $customer);

            return $booking->load('items.reservationNights');
        });

        if ($booking->wasRecentlyCreated) {
            $this->notify($booking);
        }

        return $booking;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function quoteSelection(Property $property, array $data): array
    {
        $items = $this->selectionItems($data);
        $roomsCount = array_sum(array_column($items, 'quantity'));
        $this->assertRoomLimit($roomsCount);

        if ((string) $data['check_in'] < $this->availability->propertyToday($property)->toDateString()) {
            throw ValidationException::withMessages(['check_in' => 'Check-in cannot be in the past.']);
        }

        $adultsRemaining = (int) $data['adults'];
        $childrenRemaining = (int) ($data['children'] ?? 0);
        if ($adultsRemaining < count($items)) {
            throw ValidationException::withMessages(['adults' => 'Select at least one adult for each room type.']);
        }

        $selected = [];
        foreach ($items as $item) {
            $room = $property->roomTypes()->active()->find((int) $item['room_type_id']);
            $plan = $room?->ratePlans()->active()->find((int) $item['rate_plan_id']);
            if (! $room || ! $plan || (int) $plan->property_id !== $property->id) {
                throw ValidationException::withMessages(['items' => 'A selected room or rate is no longer available at this hotel.']);
            }
            $selected[] = ['room' => $room, 'plan' => $plan, 'quantity' => (int) $item['quantity']];
        }

        foreach ($selected as $index => $line) {
            $remainingTypes = count($selected) - $index - 1;
            $adults = min((int) $line['room']->max_adults * $line['quantity'], $adultsRemaining - $remainingTypes);
            if ($adults < 1) {
                throw ValidationException::withMessages(['adults' => 'The selected rooms cannot accommodate these adults.']);
            }
            $selected[$index]['adults'] = $adults;
            $adultsRemaining -= $adults;
        }
        if ($adultsRemaining > 0) {
            throw ValidationException::withMessages(['adults' => 'The selected rooms cannot accommodate these adults.']);
        }

        $lines = [];
        $subtotal = $taxes = $fees = $total = '0.00';
        $nightly = [];
        foreach ($selected as $line) {
            $room = $line['room'];
            $plan = $line['plan'];
            $quantity = $line['quantity'];
            $children = min($childrenRemaining, (int) $room->max_children * $quantity, (int) $room->max_occupancy * $quantity - $line['adults']);
            $childrenRemaining -= $children;
            $quote = $this->pricing->quote($plan, (string) $data['check_in'], (string) $data['check_out'], $quantity, $line['adults'], $children, true);
            if (! $quote['available']) {
                throw ValidationException::withMessages(['availability' => $room->name.': '.($quote['unavailable_reason'] ?? 'No longer available for these dates.')]);
            }
            $policy = [
                'mode' => $plan->cancellation_mode,
                'free_until_hours' => 48,
                'fee_type' => $plan->cancellation_mode === HotelRatePlan::CANCEL_NON_REFUNDABLE ? 'full_amount' : 'first_night',
                'fee_value' => $plan->cancellation_mode === HotelRatePlan::CANCEL_NON_REFUNDABLE ? 100 : null,
                'summary' => $plan->cancellation_note ?: ($plan->cancellation_mode === HotelRatePlan::CANCEL_NON_REFUNDABLE ? 'Non-refundable' : 'Free cancellation until 48 hours before check-in; then first-night charge.'),
            ];
            $snapshot = $quote + [
                'quote_fingerprint' => self::fingerprint($quote),
                'room_type_name' => $room->name,
                'rate_plan_name' => $plan->name,
                'meal_plan' => $plan->meal_plan,
                'cancellation_mode' => $plan->cancellation_mode,
                'cancellation_policy' => $policy,
            ];
            $lines[] = [
                'room_type_id' => $room->id, 'rate_plan_id' => $plan->id, 'quantity' => $quantity,
                'room_name' => $room->name, 'rate_name' => $plan->name, 'meal_plan' => $plan->meal_plan,
                'cancellation_mode' => $plan->cancellation_mode, 'cancellation_note' => $plan->cancellation_note,
                'quote' => $quote, 'snapshot' => $snapshot,
            ];
            $subtotal = bcadd($subtotal, $quote['subtotal'], 2);
            $taxes = bcadd($taxes, $this->chargeTotal($quote['taxes']), 2);
            $fees = bcadd($fees, $this->chargeTotal($quote['fees']), 2);
            $total = bcadd($total, $quote['total'], 2);
            foreach ($quote['nightly'] as $night) {
                $date = $night['date'];
                $nightly[$date] = ['date' => $date, 'night_total' => bcadd($nightly[$date]['night_total'] ?? '0.00', $night['night_total'], 2)];
            }
        }
        if ($childrenRemaining > 0) {
            throw ValidationException::withMessages(['children' => 'The selected rooms cannot accommodate these children.']);
        }

        return [
            'items' => $lines, 'rooms_count' => $roomsCount, 'nights_count' => count($nightly),
            'currency' => $lines[0]['quote']['currency'], 'subtotal' => $subtotal, 'taxes' => $taxes, 'fees' => $fees, 'total' => $total,
            'nightly' => array_values($nightly),
            'quote_fingerprint' => hash('sha256', json_encode(array_map(fn (array $line): string => self::fingerprint($line['quote']), $lines))),
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<int, array{room_type_id: int, rate_plan_id: int, quantity: int}>
     */
    protected function selectionItems(array $data): array
    {
        $items = $data['items'] ?? [[
            'room_type_id' => $data['room_type_id'] ?? null,
            'rate_plan_id' => $data['rate_plan_id'] ?? null,
            'quantity' => $data['rooms'] ?? null,
        ]];
        if (! is_array($items) || $items === [] || count($items) > 10) {
            throw ValidationException::withMessages(['items' => 'Select between one and ten room types.']);
        }
        $seen = [];
        foreach ($items as &$item) {
            if (! is_array($item) || filter_var($item['room_type_id'] ?? null, FILTER_VALIDATE_INT) === false || filter_var($item['rate_plan_id'] ?? null, FILTER_VALIDATE_INT) === false || filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT) === false || (int) $item['quantity'] < 1) {
                throw ValidationException::withMessages(['items' => 'Choose a valid room, rate, and positive quantity.']);
            }
            $item = ['room_type_id' => (int) $item['room_type_id'], 'rate_plan_id' => (int) $item['rate_plan_id'], 'quantity' => (int) $item['quantity']];
            if (isset($seen[$item['room_type_id']])) {
                throw ValidationException::withMessages(['items' => 'Select each room type only once.']);
            }
            $seen[$item['room_type_id']] = true;
        }
        unset($item);

        return array_values($items);
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
        foreach (['check_in', 'check_out', 'adults', 'guest_name', 'guest_email', 'guest_phone'] as $field) {
            if (! array_key_exists($field, $data) || trim((string) $data[$field]) === '') {
                throw ValidationException::withMessages([$field => 'This field is required.']);
            }
        }
        if (! isset($data['items'])) {
            foreach (['room_type_id', 'rate_plan_id', 'rooms'] as $field) {
                if (! isset($data[$field])) {
                    throw ValidationException::withMessages([$field => 'This field is required.']);
                }
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
