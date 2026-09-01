<?php

namespace App\Services;

use App\Models\Booking;
use Razorpay\Api\Api;

/**
 * Thin wrapper so controllers never talk to a gateway SDK directly.
 * Swap/extend gateways here without touching booking logic.
 */
class PaymentService
{
    public function createRazorpayOrder(Booking $booking): array
    {
        $api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));

        $order = $api->order->create([
            'receipt' => $booking->booking_reference_id,
            'amount' => (int) round($booking->total_amount * 100), // paise
            'currency' => 'INR',
            'notes' => ['booking_id' => $booking->id],
        ]);

        return [
            'order_id' => $order['id'],
            'key' => config('services.razorpay.key'),
            'amount' => $order['amount'],
            'currency' => $order['currency'],
        ];
    }

    public function verifyRazorpaySignature(array $payload): bool
    {
        $api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));

        try {
            $api->utility->verifyPaymentSignature($payload);
            return true;
        } catch (\Exception $e) {
            report($e);
            return false;
        }
    }

    // TODO: implement Paytm order creation + checksum verification the same way,
    // following Paytm's server-side checksum generation flow.
    public function createPaytmOrder(Booking $booking): array
    {
        return [
            'mid' => config('services.paytm.merchant_id'),
            'order_id' => $booking->booking_reference_id,
            'amount' => number_format($booking->total_amount, 2, '.', ''),
            // checksum + callback URL generation goes here
        ];
    }
}
