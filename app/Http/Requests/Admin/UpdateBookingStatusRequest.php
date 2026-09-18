<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_status' => ['sometimes', 'required', Rule::enum(BookingStatus::class)],
            'payment_status' => ['sometimes', 'required', Rule::enum(PaymentStatus::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $booking = $this->route('booking');
            $to = $this->input('booking_status');
            if ($booking instanceof Booking && $to !== null) {
                $target = BookingStatus::from($to);
                if ($booking->booking_status !== $target && ! $booking->booking_status->canTransitionTo($target)) {
                    $validator->errors()->add(
                        'booking_status',
                        "Cannot move booking from {$booking->booking_status->value} to {$target->value}."
                    );
                }
            }
        });
    }
}
