<?php

namespace App\Services\Discovery;

use Illuminate\Support\Str;

/**
 * Shared search-term normalization (Phase 13C).
 *
 * Unicode-safe: trims, collapses whitespace, enforces a safe maximum
 * length. Never transliterates — Arabic/Hindi terms pass through intact.
 */
final class SearchTerm
{
    public static function normalize(mixed $raw, ?int $maxLength = null): string
    {
        $maxLength ??= (int) config('search.max_query_length', 80);

        if (! is_string($raw)) {
            return '';
        }

        $term = Str::of($raw)->squish()->toString();

        if ($term === '') {
            return '';
        }

        return (string) Str::of($term)->limit($maxLength, '')->toString();
    }

    public static function meetsMinimum(string $term, ?int $minimum = null): bool
    {
        $minimum ??= (int) config('search.minimum_query_length', 2);

        return mb_strlen($term) >= max(1, $minimum);
    }

    /**
     * Deterministic database ranking tier for one candidate name.
     * 0 = exact, 1 = prefix, 2 = contains, 3 = no direct hit.
     */
    public static function rankTier(string $candidate, string $term): int
    {
        $candidate = mb_strtolower(trim($candidate));
        $needle = mb_strtolower(trim($term));

        if ($candidate === '' || $needle === '') {
            return 3;
        }

        if ($candidate === $needle) {
            return 0;
        }

        if (str_starts_with($candidate, $needle)) {
            return 1;
        }

        if (str_contains($candidate, $needle)) {
            return 2;
        }

        return 3;
    }

    public static function like(string $term): string
    {
        return '%'.$term.'%';
    }
}
