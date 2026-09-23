<?php

namespace App\Services;

use App\Contracts\ExchangeRateProvider;
use App\Models\ExchangeRate;
use App\Support\CurrencyRegistry;

/**
 * Manual provider (Phase 13B): returns currently stored manual rates.
 *
 * Works with zero external APIs — the CodeCanyon buyer is never forced
 * to obtain an FX key. Remote adapters implement the same contract.
 */
class ManualExchangeRateProvider implements ExchangeRateProvider
{
    public function key(): string
    {
        return ExchangeRate::SOURCE_MANUAL;
    }

    public function fetchRates(string $base, array $quotes): array
    {
        $base = CurrencyRegistry::normalizeCode($base);

        if ($base === '') {
            throw new \RuntimeException('Invalid FX base currency.');
        }

        $quotes = array_values(array_unique(array_filter(array_map(
            fn ($code): string => CurrencyRegistry::normalizeCode((string) $code),
            $quotes
        ))));

        if ($quotes === []) {
            return [];
        }

        return ExchangeRate::query()
            ->where('base_currency_code', $base)
            ->whereIn('quote_currency_code', $quotes)
            ->where('source', ExchangeRate::SOURCE_MANUAL)
            ->pluck('rate', 'quote_currency_code')
            ->map(fn ($rate): string => (string) $rate)
            ->all();
    }
}
