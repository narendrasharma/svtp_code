<?php

namespace App\Services\Discovery;

use App\Support\Localization;
use App\Support\ModuleManager;

/**
 * Taxi discovery/search adapter (Phase 13C).
 *
 * Taxi stays operationally address/coordinate driven (pickup_address,
 * drop_address, pickup_at, passengers). This adapter only exposes a
 * normalized discovery contract for city/destination/place suggestions
 * plus operational field guidance — it never replaces free/geocoded
 * address input and never rewrites dispatch architecture.
 */
final class TaxiDiscoveryService
{
    public function __construct(
        protected LocationSearchService $locations,
        protected ModuleManager $modules,
    ) {}

    public function available(): bool
    {
        return $this->modules->isEnabled(ModuleManager::TAXI);
    }

    /**
     * Discovery contract for taxi UX: shared geography suggestions plus
     * explicit operational field metadata. Coordinates/addresses remain
     * caller-owned; suggestions are discovery hints only.
     *
     * @return array{available:bool,operational_fields:array<int,array{name:string,type:string,required:bool,note:string}>,suggestions:array<int,array<string,mixed>>,supports_airport_entity:bool}
     */
    public function contract(mixed $raw, ?string $locale = null, ?int $limit = null): array
    {
        $locale ??= Localization::currentLocale();

        return [
            'available' => $this->available(),
            'operational_fields' => [
                ['name' => 'pickup_address', 'type' => 'string', 'required' => true, 'note' => 'Free-form pickup address; discovery suggestions never replace it.'],
                ['name' => 'pickup_lat', 'type' => 'float', 'required' => false, 'note' => 'Optional pickup latitude from geocoding.'],
                ['name' => 'pickup_lng', 'type' => 'float', 'required' => false, 'note' => 'Optional pickup longitude from geocoding.'],
                ['name' => 'drop_address', 'type' => 'string', 'required' => true, 'note' => 'Free-form drop address; discovery suggestions never replace it.'],
                ['name' => 'drop_lat', 'type' => 'float', 'required' => false, 'note' => 'Optional drop latitude from geocoding.'],
                ['name' => 'drop_lng', 'type' => 'float', 'required' => false, 'note' => 'Optional drop longitude from geocoding.'],
                ['name' => 'pickup_at', 'type' => 'datetime', 'required' => true, 'note' => 'Requested pickup date/time (must be future).'],
                ['name' => 'passenger_count', 'type' => 'integer', 'required' => true, 'note' => 'Passenger count for vehicle matching.'],
                ['name' => 'city_id', 'type' => 'integer', 'required' => false, 'note' => 'Optional city context for popular routes/landing pages.'],
            ],
            'suggestions' => $this->locations->autocomplete($raw, $locale, $limit),
            // Future seam only: no airport database ships in 13C, so no
            // airport results are ever exposed.
            'supports_airport_entity' => false,
        ];
    }
}
