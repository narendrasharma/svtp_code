<?php

namespace App\Services\AdminSearch;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\ModuleManager;

/**
 * Vehicle search by reference, name or registration number.
 */
class TaxiVehicleSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'taxi_vehicles';
    }

    public function label(): string
    {
        return 'Taxi Vehicles';
    }

    public function isAvailable(?User $user): bool
    {
        return $user !== null
            && app(ModuleManager::class)->isEnabled(ModuleManager::TAXI)
            && $user->can('taxi.vehicles.view');
    }

    public function search(?User $user, string $query, int $limit): array
    {
        if (! $this->isAvailable($user)) {
            return [];
        }

        $term = '%'.$query.'%';

        return Vehicle::query()
            ->with('vehicleType:id,name')
            ->where(function ($q) use ($term): void {
                $q->where('reference', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('registration_number', 'like', $term);
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Vehicle $vehicle): array => [
                'type' => 'taxi_vehicle',
                'label' => $vehicle->reference.' · '.$vehicle->registration_number,
                'subtitle' => trim($vehicle->name.' · '.($vehicle->vehicleType?->name ?? ''), ' ·'),
                'url' => route('admin.taxi.vehicles.show', $vehicle, absolute: false),
                'icon' => 'bi-truck-front',
            ])
            ->all();
    }
}
