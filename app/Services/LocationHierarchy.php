<?php

namespace App\Services;

use App\Models\City;
use App\Models\Destination;
use App\Models\State;
use Illuminate\Validation\ValidationException;

/**
 * Shared geography hierarchy validation (12B.4.1).
 *
 * One seam guards every writer (admin CRUD, property service, future
 * tour/vendor flows) so a City can never point at a State from another
 * Country, a Destination can never mix incompatible geography, and a
 * Property can never combine a City with a foreign State/Destination.
 *
 * Rules are permissive about NULLs: legacy rows without a verified
 * country keep working; only explicit contradictions are rejected.
 */
class LocationHierarchy
{
    /**
     * @throws ValidationException
     */
    public static function validateCity(?int $countryId, ?int $stateId): void
    {
        if ($countryId === null || $stateId === null) {
            return;
        }

        $state = State::find($stateId);

        if ($state && $state->country_id !== null && (int) $state->country_id !== (int) $countryId) {
            throw ValidationException::withMessages([
                'state_id' => 'The selected state belongs to a different country.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function validateDestination(
        ?int $countryId,
        ?int $stateId,
        ?int $cityId,
        ?int $parentId = null,
        ?int $ignoreId = null
    ): void {
        $city = $cityId !== null ? City::find($cityId) : null;
        $state = $stateId !== null ? State::find($stateId) : null;

        if ($city && $stateId !== null) {
            $cityStateId = $city->state_id !== null ? (int) $city->state_id : null;

            if ($cityStateId !== null && $cityStateId !== (int) $stateId) {
                throw ValidationException::withMessages([
                    'city_id' => 'The selected city belongs to a different state.',
                ]);
            }

            if ($state && $state->country_id !== null && $city->country_id !== null
                && (int) $state->country_id !== (int) $city->country_id) {
                throw ValidationException::withMessages([
                    'city_id' => 'The selected city belongs to a different country than the state.',
                ]);
            }
        }

        $effectiveCountry = $countryId;

        if ($effectiveCountry === null && $city && $city->country_id !== null) {
            $effectiveCountry = (int) $city->country_id;
        }

        if ($effectiveCountry === null && $state && $state->country_id !== null) {
            $effectiveCountry = (int) $state->country_id;
        }

        if ($countryId !== null && $city && $city->country_id !== null
            && (int) $city->country_id !== (int) $countryId) {
            throw ValidationException::withMessages([
                'city_id' => 'The selected city belongs to a different country.',
            ]);
        }

        if ($countryId !== null && $state && $state->country_id !== null
            && (int) $state->country_id !== (int) $countryId) {
            throw ValidationException::withMessages([
                'state_id' => 'The selected state belongs to a different country.',
            ]);
        }

        if ($parentId !== null) {
            static::validateDestinationParent($parentId, $ignoreId, $effectiveCountry);
        }
    }

    /**
     * @throws ValidationException
     */
    protected static function validateDestinationParent(int $parentId, ?int $ignoreId, ?int $countryId): void
    {
        if ($ignoreId !== null && $parentId === $ignoreId) {
            throw ValidationException::withMessages([
                'parent_id' => 'A destination cannot be its own parent.',
            ]);
        }

        $parent = Destination::find($parentId);

        if (! $parent) {
            return;
        }

        if ($countryId !== null && $parent->country_id !== null
            && (int) $parent->country_id !== (int) $countryId) {
            throw ValidationException::withMessages([
                'parent_id' => 'The parent destination belongs to a different country.',
            ]);
        }

        // Walk up the chain to reject cycles (bounded depth — hierarchy
        // is merchandising depth, never a deep taxonomy).
        $seen = [$parentId];
        $current = $parent;

        for ($depth = 0; $depth < 25; $depth++) {
            if ($current->parent_id === null) {
                return;
            }

            $nextId = (int) $current->parent_id;

            if ($ignoreId !== null && $nextId === $ignoreId) {
                throw ValidationException::withMessages([
                    'parent_id' => 'This parent would create a circular hierarchy.',
                ]);
            }

            if (in_array($nextId, $seen, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'This parent would create a circular hierarchy.',
                ]);
            }

            $seen[] = $nextId;
            $current = Destination::find($nextId);

            if (! $current) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'parent_id' => 'The destination hierarchy is too deep.',
        ]);
    }

    /**
     * @throws ValidationException
     */
    public static function validatePropertyGeography(
        ?int $countryId,
        ?int $stateId,
        ?int $cityId,
        ?int $destinationId
    ): void {
        static::validateCity($countryId, $stateId);

        $city = $cityId !== null ? City::find($cityId) : null;
        $state = $stateId !== null ? State::find($stateId) : null;

        if ($city && $stateId !== null && $city->state_id !== null
            && (int) $city->state_id !== (int) $stateId) {
            throw ValidationException::withMessages([
                'city_id' => 'The selected city belongs to a different state.',
            ]);
        }

        $cityCountry = $city && $city->country_id !== null ? (int) $city->country_id : null;
        $propertyCountry = $countryId ?? $cityCountry;

        if ($countryId !== null && $cityCountry !== null && $cityCountry !== (int) $countryId) {
            throw ValidationException::withMessages([
                'city_id' => 'The selected city belongs to a different country.',
            ]);
        }

        // A state from one country combined with a city/country from
        // another is contradictory even when the city itself is stateless.
        if ($state && $state->country_id !== null && $propertyCountry !== null
            && (int) $state->country_id !== $propertyCountry) {
            throw ValidationException::withMessages([
                'state_id' => 'The selected state belongs to a different country.',
            ]);
        }

        if ($destinationId === null) {
            return;
        }

        $destination = Destination::find($destinationId);

        if (! $destination) {
            return;
        }

        if ($city && $destination->city_id !== null
            && (int) $destination->city_id !== (int) $city->id) {
            throw ValidationException::withMessages([
                'destination_id' => 'The selected destination belongs to a different city.',
            ]);
        }

        $propertyCountry = $countryId ?? ($city?->country_id !== null ? (int) $city->country_id : null);

        if ($propertyCountry === null && $state && $state->country_id !== null) {
            $propertyCountry = (int) $state->country_id;
        }

        if ($propertyCountry !== null && $destination->country_id !== null
            && (int) $destination->country_id !== $propertyCountry) {
            throw ValidationException::withMessages([
                'destination_id' => 'The selected destination belongs to a different country.',
            ]);
        }

        if (! $destination->is_active) {
            throw ValidationException::withMessages([
                'destination_id' => 'The selected destination is not active.',
            ]);
        }
    }

    /**
     * Normalized "City, State, Country" label. Missing levels are
     * skipped — states are optional in several countries.
     */
    public static function displayName(City|Destination|null $location): ?string
    {
        if ($location === null) {
            return null;
        }

        $parts = [$location->name];

        $city = $location instanceof City ? $location : $location->city;
        $state = $location instanceof City ? $location->state : ($location->state ?? $location->city?->state);
        $country = $location->country ?? $city?->country ?? $state?->country;

        if ($location instanceof Destination && $city && $city->name !== $location->name) {
            $parts[] = $city->name;
        }

        if ($state && ! in_array($state->name, $parts, true)) {
            $parts[] = $state->name;
        }

        if ($country && ! in_array($country->name, $parts, true)) {
            $parts[] = $country->name;
        }

        return implode(', ', array_filter($parts));
    }

    /**
     * Fill a missing country from the selected state/city chain.
     * Explicit input always wins; nothing is guessed by name.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function inheritCountry(array $attributes): array
    {
        if (! empty($attributes['country_id'])) {
            return $attributes;
        }

        if (! empty($attributes['city_id']) && ($city = City::find($attributes['city_id'])) && $city->country_id) {
            $attributes['country_id'] = $city->country_id;

            return $attributes;
        }

        if (! empty($attributes['state_id']) && ($state = State::find($attributes['state_id'])) && $state->country_id) {
            $attributes['country_id'] = $state->country_id;
        }

        return $attributes;
    }
}
