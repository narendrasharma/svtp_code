<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Setting;
use App\Support\CurrencyRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * Authoritative shared money conversion (Phase 13B).
 *
 * DISPLAY ONLY: converting a quote never rewrites the authoritative
 * record. Old bookings never recalculate — snapshots stay immutable.
 *
 * Precision: BCMath decimal strings throughout; rounding happens ONCE
 * at the target boundary using the target currency's decimal_digits.
 * Never returns a naked number — always the full result DTO.
 */
class CurrencyConversionService
{
    public const RATE_SCALE = 10;

    /**
     * Canonical platform FX base. Defaults to USD (matches the Hotel
     * pricing default `hotel.default_currency`), overridable via the
     * `currency.fx_base` setting. Cross-rates derive through this base.
     */
    public static function fxBase(): string
    {
        $raw = strtoupper((string) (Setting::getValue('currency.fx_base', null) ?? config('currency.fx_base', 'USD') ?? 'USD'));

        return preg_match('/^[A-Z]{3}$/', $raw) === 1 ? $raw : 'USD';
    }

    public static function maxRateAgeHours(): int
    {
        return min(720, max(1, (int) (Setting::getValue('currency.max_rate_age_hours', null) ?? config('currency.max_rate_age_hours', 24) ?? 24)));
    }

    /**
     * Request-efficient rate map: ONE query per request for all
     * canonical-base rows (20 hotel cards ≠ 20 queries).
     *
     * @return array<string, array{rate:string,source:string,fetched_at:?string}>
     */
    public static function rateMap(): array
    {
        try {
            return Cache::remember('currency.rates.map', 600, function (): array {
                $base = static::fxBase();

                $rows = ExchangeRate::query()
                    ->where('base_currency_code', $base)
                    ->get(['quote_currency_code', 'rate', 'source', 'fetched_at']);

                $map = [];
                foreach ($rows as $row) {
                    $map[$row->quote_currency_code] = [
                        'rate' => (string) $row->rate,
                        'source' => $row->source,
                        'fetched_at' => $row->fetched_at?->toIso8601String(),
                    ];
                }

                return $map;
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve rate for 1 $from = X $to.
     *
     * Supports same-currency (1), direct, inverse, and cross-through-base.
     * Returns null when no valid rate exists — NEVER fabricates 1:1.
     *
     * @return array{rate:string,source:string,fetched_at:?string,stale:bool}|null
     */
    public static function resolveRate(string $from, string $to): ?array
    {
        $from = CurrencyRegistry::normalizeCode($from);
        $to = CurrencyRegistry::normalizeCode($to);

        if ($from === '' || $to === '') {
            return null;
        }

        if ($from === $to) {
            return ['rate' => '1', 'source' => 'same', 'fetched_at' => null, 'stale' => false];
        }

        $map = static::rateMap();
        $base = static::fxBase();
        $maxAge = static::maxRateAgeHours();

        $stale = function (?string $fetchedAt): bool {
            if ($fetchedAt === null) {
                return true;
            }

            try {
                return now()->diffInHours(new \DateTimeImmutable($fetchedAt)) > $maxAge;
            } catch (\Throwable) {
                return true;
            }
        };

        // Direct: base row stored as base→X only when $from is the base.
        if ($from === $base && isset($map[$to])) {
            $row = $map[$to];

            return ['rate' => $row['rate'], 'source' => $row['source'], 'fetched_at' => $row['fetched_at'], 'stale' => $stale($row['fetched_at'])];
        }

        // Inverse: stored base→from, need from→base.
        if ($to === $base && isset($map[$from]) && static::isPositiveRate($map[$from]['rate'])) {
            $row = $map[$from];

            return ['rate' => bcdiv('1', $row['rate'], static::RATE_SCALE), 'source' => $row['source'], 'fetched_at' => $row['fetched_at'], 'stale' => $stale($row['fetched_at'])];
        }

        // Cross through canonical base: from→base→to.
        if (isset($map[$from], $map[$to])
            && static::isPositiveRate($map[$from]['rate'])
            && static::isPositiveRate($map[$to]['rate'])) {
            $rate = bcdiv($map[$to]['rate'], $map[$from]['rate'], static::RATE_SCALE);

            return [
                'rate' => $rate,
                'source' => $map[$to]['source'],
                'fetched_at' => $map[$to]['fetched_at'],
                'stale' => $stale($map[$from]['fetched_at']) || $stale($map[$to]['fetched_at']),
            ];
        }

        return null;
    }

    /**
     * Convert an authoritative amount for DISPLAY.
     *
     * Same currency returns unchanged (no double conversion). Missing
     * rate returns available=false — caller must show the authoritative
     * amount, never 0 or silent 1:1.
     *
     * @return array{available:bool,conversion_applied:bool,source_currency:string,target_currency:string,source_amount:string,exchange_rate:?string,converted_amount:?string,rate_source:?string,rate_timestamp:?string,stale:bool}
     */
    public static function convert(int|float|string $amount, string $from, string $to): array
    {
        $from = CurrencyRegistry::normalizeCode($from);
        $to = CurrencyRegistry::normalizeCode($to);
        $sourceAmount = static::toDecimalString($amount);

        $blank = fn (): array => [
            'available' => false,
            'conversion_applied' => false,
            'source_currency' => $from,
            'target_currency' => $to,
            'source_amount' => $sourceAmount,
            'exchange_rate' => null,
            'converted_amount' => null,
            'rate_source' => null,
            'rate_timestamp' => null,
            'stale' => false,
        ];

        if ($from === '' || $to === '' || ! is_numeric($sourceAmount)) {
            return $blank();
        }

        if ($from === $to) {
            return [
                'available' => true,
                'conversion_applied' => false,
                'source_currency' => $from,
                'target_currency' => $to,
                'source_amount' => $sourceAmount,
                'exchange_rate' => '1',
                'converted_amount' => static::roundToCurrency($sourceAmount, $to),
                'rate_source' => 'same',
                'rate_timestamp' => null,
                'stale' => false,
            ];
        }

        $resolved = static::resolveRate($from, $to);

        if ($resolved === null) {
            return $blank();
        }

        $raw = bcmul($sourceAmount, $resolved['rate'], static::RATE_SCALE);

        return [
            'available' => true,
            'conversion_applied' => true,
            'source_currency' => $from,
            'target_currency' => $to,
            'source_amount' => $sourceAmount,
            'exchange_rate' => $resolved['rate'],
            'converted_amount' => static::roundToCurrency($raw, $to),
            'rate_source' => $resolved['source'],
            'rate_timestamp' => $resolved['fetched_at'],
            'stale' => $resolved['stale'],
        ];
    }

    /**
     * Round ONCE at the target boundary (half-up) to the currency's
     * decimal_digits (JPY 0, most 2). No intermediate rounding.
     */
    public static function roundToCurrency(int|float|string $amount, string $code): string
    {
        $digits = CurrencyRegistry::decimalDigits($code);
        $value = (string) $amount;

        if (! is_numeric($value)) {
            $value = '0';
        }

        if (function_exists('bcadd')) {
            $half = bcdiv('5', bcpow('10', (string) ($digits + 1)), $digits + 1);
            $shifted = bcmul($value, bcpow('10', (string) $digits), $digits + 1);

            if (str_starts_with(ltrim($value), '-')) {
                $rounded = bcsub($shifted, $half, 0);
            } else {
                $rounded = bcadd($shifted, $half, 0);
            }

            $result = bcdiv($rounded, bcpow('10', (string) $digits), $digits);
        } else {
            $result = number_format(round((float) $value, $digits, PHP_ROUND_HALF_UP), $digits, '.', '');
        }

        // Normalize: exactly $digits decimals (JPY → "120").
        if ($digits === 0) {
            return (string) (int) $result;
        }

        if (! str_contains($result, '.')) {
            return $result.'.'.str_repeat('0', $digits);
        }

        [$int, $frac] = explode('.', $result, 2);

        return $int.'.'.str_pad(substr($frac, 0, $digits), $digits, '0');
    }

    public static function isPositiveRate(?string $rate): bool
    {
        return is_string($rate) && is_numeric($rate) && bccomp($rate, '0', static::RATE_SCALE) === 1;
    }

    public static function toDecimalString(int|float|string $amount): string
    {
        if (is_int($amount)) {
            return (string) $amount.'.00';
        }

        if (is_float($amount)) {
            return number_format($amount, 10, '.', '');
        }

        $clean = trim((string) $amount);

        return is_numeric($clean) ? $clean : 'NaN';
    }

    /**
     * @return array<int, string>
     */
    public static function knownCurrencyCodes(): array
    {
        try {
            $db = Currency::query()->pluck('code')->all();
        } catch (\Throwable) {
            $db = [];
        }

        return array_values(array_unique(array_merge($db, ['USD', 'INR', 'EUR', 'GBP', 'AED'])));
    }
}
