<?php

namespace App\Services\Discovery;

use App\Support\Localization;

/**
 * Normalized public discovery result builder (Phase 13C).
 *
 * Stable machine types: city, destination, place, property, tour.
 * Human labels are translated separately and never used as identifiers,
 * so RTL locale switches never alter entity identity.
 */
final class DiscoveryResult
{
    public const TYPE_CITY = 'city';

    public const TYPE_DESTINATION = 'destination';

    public const TYPE_PLACE = 'place';

    public const TYPE_PROPERTY = 'property';

    public const TYPE_TOUR = 'tour';

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_CITY,
            self::TYPE_DESTINATION,
            self::TYPE_PLACE,
            self::TYPE_PROPERTY,
            self::TYPE_TOUR,
        ];
    }

    public static function label(string $type): string
    {
        $labels = [
            self::TYPE_CITY => __('common.city', [], Localization::currentLocale()) ?: 'City',
            self::TYPE_DESTINATION => __('common.destination', [], Localization::currentLocale()) ?: 'Destination',
            self::TYPE_PLACE => __('common.attraction', [], Localization::currentLocale()) ?: 'Attraction',
            self::TYPE_PROPERTY => __('common.hotel', [], Localization::currentLocale()) ?: 'Hotel',
            self::TYPE_TOUR => __('common.tour', [], Localization::currentLocale()) ?: 'Tour',
        ];

        // Fall back to English titles when the locale file has no key yet.
        $fallbacks = [
            self::TYPE_CITY => 'City',
            self::TYPE_DESTINATION => 'Destination',
            self::TYPE_PLACE => 'Attraction',
            self::TYPE_PROPERTY => 'Hotel',
            self::TYPE_TOUR => 'Tour',
        ];

        $label = $labels[$type] ?? $fallbacks[$type] ?? $type;

        // Laravel returns the key itself when missing (e.g. "common.city").
        if (str_starts_with($label, 'common.')) {
            return $fallbacks[$type] ?? $type;
        }

        return $label;
    }

    /**
     * Resolve a display name with Phase 13A fallback:
     * requested locale → default locale → original column. Never blank
     * when the model holds a usable original value.
     */
    public static function displayName(object $model, string $field, ?string $locale = null): ?string
    {
        $locale ??= Localization::currentLocale();

        if (method_exists($model, 'translated')) {
            $translated = $model->translated($field, $locale);

            if (is_string($translated) && trim($translated) !== '') {
                return $translated;
            }
        }

        $raw = $model->{$field} ?? null;

        return is_string($raw) && trim($raw) !== '' ? $raw : null;
    }

    /**
     * @param  array{lat:?float,lng:?float}|null  $coordinates
     * @return array{type:string,id:int,name:string,subtitle:?string,slug:string,image:?string,url:string,coordinates:?array{lat:?float,lng:?float},label:string}
     */
    public static function make(
        string $type,
        int $id,
        ?string $name,
        ?string $subtitle,
        string $slug,
        ?string $image,
        string $url,
        ?array $coordinates = null
    ): array {
        return [
            'type' => $type,
            'id' => $id,
            'name' => $name ?? '',
            'subtitle' => $subtitle,
            'slug' => $slug,
            'image' => $image,
            'url' => $url,
            'coordinates' => $coordinates,
            'label' => self::label($type),
        ];
    }
}
