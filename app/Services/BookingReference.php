<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * Server-side booking reference generator.
 *
 * Delegates to the central NumberSeriesService (booking series, e.g.
 * BK-2026-000001). References are never accepted from clients; the
 * database unique constraint is the final guard and this retry loop
 * makes collisions practically impossible. Historical references keep
 * whatever value they were issued with — series changes only affect
 * future bookings.
 */
class BookingReference
{
    public static function generate(): string
    {
        $service = app(NumberSeriesService::class);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $reference = $service->next('booking');

            if (! Booking::where('booking_reference_id', $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique booking reference.');
    }

    /**
     * Persist a booking with a freshly issued reference, retrying if a
     * concurrent request won the same sequence value. The reference is
     * assigned directly (never mass-assigned) so client input can never
     * set it and no sequence value is wasted.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createBooking(array $attributes): Booking
    {
        unset($attributes['booking_reference_id']);

        $lastException = null;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $booking = new Booking($attributes);
            $booking->booking_reference_id = static::generate();

            try {
                $booking->save();

                return $booking;
            } catch (QueryException $exception) {
                $lastException = $exception;

                if (! static::isDuplicateReference($exception)) {
                    throw $exception;
                }
            }
        }

        throw $lastException ?? new RuntimeException('Could not generate a unique booking reference.');
    }

    protected static function isDuplicateReference(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'booking_reference_id')
            && (str_contains($message, 'duplicate') || str_contains($message, 'unique'));
    }
}
