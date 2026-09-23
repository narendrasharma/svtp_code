<?php

namespace App\Services;

use App\Models\TaxiBooking;
use App\Notifications\CrmNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class TaxiCustomerNotificationService
{
    /**
     * Deliver a Taxi lifecycle notice to an account customer or, for a guest
     * booking, to the supplied booking email only.
     *
     * @param  array<string, mixed>  $extra
     */
    public function send(TaxiBooking $booking, string $kind, array $extra = []): void
    {
        $data = array_merge([
            'taxi_booking_id' => $booking->id,
            'reference' => $booking->reference,
            'pickup_at' => $booking->pickup_at?->toDateTimeString(),
            'pickup_address' => $booking->pickup_address,
            'drop_address' => $booking->drop_address,
            'status' => $booking->status,
            'currency' => $booking->currency,
            'total' => number_format((float) $booking->total_amount, 2),
            'customer_url' => "/account/taxi/changes/{$booking->id}",
            'guest_url' => URL::signedRoute('taxi.confirmation', ['taxiBooking' => $booking]),
        ], $extra);

        if ($booking->customer) {
            $booking->customer->notify(new CrmNotification($kind, $data));

            return;
        }

        if (filled($booking->customer_email)) {
            Notification::route('mail', $booking->customer_email)
                ->notify(new CrmNotification($kind, [...$data, 'guest_recipient' => true]));
        }
    }
}
