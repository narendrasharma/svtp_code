<?php

namespace App\Contracts;

/**
 * Exchange-rate provider contract (Phase 13B).
 *
 * Manual mode is the mandatory fallback and always works with zero
 * external APIs. Remote providers are optional adapters; a failed
 * provider must NEVER erase last-known-good rates (the refresh
 * command enforces that, not the provider).
 */
interface ExchangeRateProvider
{
    public function key(): string;

    /**
     * Fetch validated rates quoted as: 1 $base = X $quote.
     *
     * @param  array<int, string>  $quotes  ISO codes, base excluded
     * @return array<string, string> quote code => decimal rate string
     *
     * @throws \RuntimeException on provider failure (no partial writes)
     */
    public function fetchRates(string $base, array $quotes): array;
}
