<?php

namespace App\Support;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Shared currency registry (Phase 13B, module-independent).
 *
 * AUTHORITATIVE vs DISPLAY distinction:
 * - Authoritative currency lives on the priced record itself
 *   (Property.currency, HotelBooking.currency, Tour INR, Taxi currency)
 *   and is NEVER rewritten by visitor display selection.
 * - This registry owns display metadata + the default DISPLAY currency.
 *
 * Domain pricing defaults are separate and preserved:
 * - hotel.default_currency (new Property pricing default, default USD)
 * - TourBookingPricingService::DEFAULT_CURRENCY (INR)
 * - taxi.default_currency (INR)
 */
class CurrencyRegistry
{
    public const SESSION_KEY = 'currency';

    public const COOKIE_KEY = 'currency';

    public const COOKIE_MINUTES = 525600; // 12 months

    public const FALLBACK_CODE = 'USD';

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('currencies');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function normalizeCode(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z]{3}$/', $code) === 1 ? $code : '';
    }

    /**
     * @return array<int, array{code:string,name:string,symbol:string,decimal_digits:int,symbol_position:string,is_default_display:bool,sort_order:int}>
     */
    public static function activeCurrencies(): array
    {
        if (! static::tableReady()) {
            return [static::fallbackCurrency()];
        }

        try {
            return Cache::remember('currency.registry.active', 3600, function (): array {
                $rows = Currency::query()->active()->ordered()->get();

                if ($rows->isEmpty()) {
                    return [static::fallbackCurrency()];
                }

                return $rows->map(fn (Currency $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'decimal_digits' => $currency->decimal_digits,
                    'symbol_position' => $currency->symbol_position,
                    'is_default_display' => $currency->is_default_display,
                    'sort_order' => $currency->sort_order,
                ])->all();
            });
        } catch (\Throwable) {
            return [static::fallbackCurrency()];
        }
    }

    public static function activeCodes(): array
    {
        return array_column(static::activeCurrencies(), 'code');
    }

    public static function isActiveCode(?string $code): bool
    {
        $code = static::normalizeCode($code);

        return $code !== '' && in_array($code, static::activeCodes(), true);
    }

    public static function defaultDisplayCode(): string
    {
        if (! static::tableReady()) {
            return static::FALLBACK_CODE;
        }

        try {
            $default = Cache::remember('currency.registry.default', 3600, function (): ?string {
                return Currency::query()->where('is_default_display', true)->value('code')
                    ?? Currency::query()->active()->ordered()->value('code');
            });

            return is_string($default) && $default !== '' ? $default : static::FALLBACK_CODE;
        } catch (\Throwable) {
            return static::FALLBACK_CODE;
        }
    }

    /**
     * Visitor display currency resolution: explicit session → explicit
     * cookie → default display. Never inferred from browser/IP.
     */
    public static function resolveSelected(?Request $request = null): string
    {
        $request ??= request();
        $candidates = [];

        try {
            if ($request && $request->hasSession()) {
                $candidates[] = (string) $request->session()->get(static::SESSION_KEY, '');
            }
        } catch (\Throwable) {
        }

        try {
            if ($request) {
                $candidates[] = (string) $request->cookie(static::COOKIE_KEY, '');
            }
        } catch (\Throwable) {
        }

        foreach ($candidates as $candidate) {
            $normalized = static::normalizeCode($candidate);

            if ($normalized !== '' && static::isActiveCode($normalized)) {
                return $normalized;
            }
        }

        return static::defaultDisplayCode();
    }

    public static function metaFor(string $code): ?array
    {
        foreach (static::activeCurrencies() as $currency) {
            if ($currency['code'] === $code) {
                return $currency;
            }
        }

        return null;
    }

    public static function decimalDigits(string $code): int
    {
        return static::metaFor($code)['decimal_digits'] ?? 2;
    }

    public static function forgetCache(): void
    {
        Cache::forget('currency.registry.active');
        Cache::forget('currency.registry.default');
        Cache::forget('currency.rates.map');
    }

    /**
     * @return array{code:string,name:string,symbol:string,decimal_digits:int,symbol_position:string,is_default_display:bool,sort_order:int}
     */
    public static function fallbackCurrency(): array
    {
        return [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_digits' => 2,
            'symbol_position' => 'before',
            'is_default_display' => true,
            'sort_order' => 0,
        ];
    }
}
