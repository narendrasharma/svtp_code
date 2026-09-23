<?php

namespace App\Support;

/**
 * Locale-aware display formatting foundation (Phase 13A dates/numbers,
 * Phase 13B money formatting).
 *
 * FORMATTING ≠ CONVERSION: money() formats an amount in its OWN
 * currency only. Conversion lives in CurrencyConversionService;
 * the combined DTO lives in MoneyPresenter::present().
 */
class LocalizedFormat
{
    public static function date(\DateTimeInterface|string|null $value, ?string $locale = null, ?string $dateFormat = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $locale ??= Localization::currentLocale();

        try {
            $date = $value instanceof \DateTimeInterface ? $value : new \DateTimeImmutable((string) $value);

            if (class_exists(\IntlDateFormatter::class)) {
                $formatter = new \IntlDateFormatter(
                    $locale,
                    \IntlDateFormatter::MEDIUM,
                    \IntlDateFormatter::NONE
                );

                if ($dateFormat !== null && $dateFormat !== '') {
                    $formatter->setPattern($dateFormat);
                }

                $formatted = $formatter->format($date);

                if (is_string($formatted) && $formatted !== '') {
                    return $formatted;
                }
            }

            return $date->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function number(int|float|null $value, ?string $locale = null, int $decimals = 0): ?string
    {
        if ($value === null) {
            return null;
        }

        $locale ??= Localization::currentLocale();

        try {
            if (class_exists(\NumberFormatter::class)) {
                $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
                $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);

                $formatted = $formatter->format((float) $value);

                if (is_string($formatted) && $formatted !== '') {
                    return $formatted;
                }
            }

            return number_format((float) $value, $decimals);
        } catch (\Throwable) {
            return number_format((float) $value, $decimals);
        }
    }

    /**
     * Locale-aware currency FORMATTING (no conversion). Uses the
     * Intl currency formatter so RTL locales render correctly; never
     * hand-reverse currency strings. Falls back to "CODE amount".
     */
    public static function money(int|float|string|null $value, ?string $code = null, ?string $locale = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $code = CurrencyRegistry::normalizeCode($code);
        $locale ??= Localization::currentLocale();

        if ($code === '') {
            return null;
        }

        try {
            if (class_exists(\NumberFormatter::class)) {
                $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
                $formatted = $formatter->formatCurrency((float) $value, $code);

                if (is_string($formatted) && $formatted !== '') {
                    return $formatted;
                }
            }
        } catch (\Throwable) {
        }

        $digits = CurrencyRegistry::decimalDigits($code);

        return trim($code.' '.number_format((float) $value, $digits));
    }
}
