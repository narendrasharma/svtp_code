<?php

namespace App\Http\Requests\Taxi;

use App\Enums\BookingSource;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shared taxi booking payload (12A.1). Used by admin and vendor
 * controllers so both sides validate identically; the service stays
 * the pricing/status authority.
 */
class StoreTaxiBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trip_type' => ['required', Rule::in(TripType::bookable())],
            'pickup_at' => ['required', 'date'],
            'return_at' => ['nullable', 'date', 'after:pickup_at'],
            'pickup_address' => ['required', 'string', 'max:500'],
            'pickup_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'drop_address' => ['required', 'string', 'max:500'],
            'drop_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'drop_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'airport_direction' => ['nullable', Rule::in(['airport_pickup', 'airport_drop'])],
            'flight_number' => ['nullable', 'string', 'max:20'],
            'airline' => ['nullable', 'string', 'max:80'],
            'terminal' => ['nullable', 'string', 'max:40'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:60'],
            'luggage_count' => ['nullable', 'integer', 'min:0', 'max:60'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'rental_package_id' => ['nullable', 'integer', 'exists:taxi_rental_packages,id'],
            'vendor_profile_id' => ['nullable', 'integer', 'exists:vendor_profiles,id'],
            'customer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'special_instructions' => ['nullable', 'string', 'max:2000'],
            'source' => ['sometimes', Rule::in(BookingSource::staffCreatable())],
            'quoted_distance_km' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'quoted_duration_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'waiting_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'toll_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'parking_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'stops' => ['nullable', 'array', 'max:10'],
            'stops.*.address' => ['required_with:stops', 'string', 'max:500'],
            'stops.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'stops.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'stops.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bookingData(): array
    {
        $validated = $this->validated();

        if (($validated['trip_type'] ?? null) === TripType::AirportTransfer->value && empty($validated['airport_direction'])) {
            throw ValidationException::withMessages([
                'airport_direction' => 'Airport pickup or drop is required for airport transfers.',
            ]);
        }

        return $validated;
    }
}
