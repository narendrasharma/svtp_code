<?php

namespace App\Services\AdminSearch;

use App\Models\TaxiBooking;
use App\Models\User;
use App\Support\ModuleManager;

/**
 * Taxi booking search by reference, customer name/phone, pickup/drop.
 * Never amounts or payment internals.
 */
class TaxiBookingSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'taxi_bookings';
    }

    public function label(): string
    {
        return 'Taxi Bookings';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null
            && app(ModuleManager::class)->isEnabled(ModuleManager::TAXI)
            && $user->can('taxi.bookings.view');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return TaxiBooking::query()
            ->where(function ($q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term)
                    ->orWhere('pickup_address', 'like', $term)
                    ->orWhere('drop_address', 'like', $term);
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (TaxiBooking $booking): array => [
                'type' => 'taxi_booking',
                'label' => $booking->reference,
                'subtitle' => trim(($booking->customer_name ?? 'Guest').' · '.$booking->pickup_address.' → '.$booking->drop_address),
                'url' => route('admin.taxi.bookings.show', $booking, absolute: false),
                'icon' => 'bi-taxi-front',
            ])
            ->all();
    }
}
