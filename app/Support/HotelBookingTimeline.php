<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\HotelBooking;

class HotelBookingTimeline
{
    /** @return array<int, array{event: string, description: string, created_at: ?string}> */
    public static function for(HotelBooking $booking): array
    {
        return ActivityLog::query()
            ->where('subject_type', HotelBooking::class)
            ->where('subject_id', $booking->id)
            ->whereIn('event', ['hotel_booking.created', 'hotel_booking.status_changed', 'hotel_booking.cancelled', 'hotel_booking.refund_created', 'hotel_booking.rescheduled'])
            ->latest()
            ->limit(20)
            ->get(['event', 'description', 'created_at'])
            ->map(fn (ActivityLog $log): array => [
                'event' => self::label($log->event),
                'description' => (string) ($log->description ?: self::label($log->event)),
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    protected static function label(string $event): string
    {
        return match ($event) {
            'hotel_booking.created' => 'Booking created',
            'hotel_booking.status_changed' => 'Booking status updated',
            'hotel_booking.cancelled' => 'Booking cancelled',
            'hotel_booking.refund_created' => 'Refund accounting record created',
            'hotel_booking.rescheduled' => 'Booking dates rescheduled',
            default => 'Booking activity',
        };
    }
}
