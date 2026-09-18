<?php

namespace App\Services\AdminSearch;

use App\Models\Booking;
use App\Models\User;

/**
 * Booking search by human reference, customer name, email or phone.
 * Only id/reference/customer descriptors + URL leave this provider —
 * never amounts, commissions or gateway data.
 */
class BookingSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'bookings';
    }

    public function label(): string
    {
        return 'Bookings';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null && $user->can('bookings.view');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return Booking::query()
            ->with('package:id,title')
            ->where(function ($q) use ($term): void {
                $q->where('booking_reference_id', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term);
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Booking $booking): array => [
                'type' => 'booking',
                'label' => $booking->booking_reference_id,
                'subtitle' => trim(($booking->customer_name ?? 'Guest').' · '.($booking->package?->title ?? 'Tour booking')),
                'url' => route('admin.bookings.show', $booking, absolute: false),
                'icon' => 'bi-calendar-check',
            ])
            ->all();
    }
}
