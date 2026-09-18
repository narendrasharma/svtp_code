<?php

namespace App\Services\AdminSearch;

use App\Models\Driver;
use App\Models\User;
use App\Support\ModuleManager;

/**
 * Driver search by reference, name or phone. No document numbers,
 * addresses or emergency contacts in results.
 */
class TaxiDriverSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'taxi_drivers';
    }

    public function label(): string
    {
        return 'Taxi Drivers';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null
            && app(ModuleManager::class)->isEnabled(ModuleManager::TAXI)
            && $user->can('taxi.drivers.view');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return Driver::query()
            ->with('vendorProfile:id,business_name')
            ->where(function ($q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Driver $driver): array => [
                'type' => 'taxi_driver',
                'label' => $driver->reference.' · '.$driver->fullName(),
                'subtitle' => trim($driver->phone.' · '.($driver->vendorProfile?->business_name ?? ''), ' ·'),
                'url' => route('admin.taxi.drivers.show', $driver, absolute: false),
                'icon' => 'bi-person-badge',
            ])
            ->all();
    }
}
