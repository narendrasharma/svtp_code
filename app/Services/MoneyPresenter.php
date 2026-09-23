<?php

namespace App\Services;

use App\Support\CurrencyRegistry;
use App\Support\Localization;
use App\Support\LocalizedFormat;

/**
 * Frontend-safe display-money DTO (Phase 13B).
 *
 * FORMATTING ≠ CONVERSION, enforced by API shape:
 * - formatMoney(): format an amount in its OWN currency (no FX).
 * - present(): authoritative amount + optional DISPLAY conversion.
 *
 * The authoritative `amount/currency` are ALWAYS present; display
 * fields fall back to authoritative values when no rate exists.
 */
class MoneyPresenter
{
    /**
     * Format only — never converts.
     */
    public static function formatMoney(int|float|string $amount, string $code, ?string $locale = null): string
    {
        return (string) (LocalizedFormat::money($amount, $code, $locale) ?? trim($code.' '.(string) $amount));
    }

    /**
     * @return array{amount:string,currency:string,formatted:string,display_amount:string,display_currency:string,display_formatted:string,conversion_applied:bool,exchange_rate:?string,rate_source:?string,rate_timestamp:?string,stale:bool}
     */
    public static function present(int|float|string $amount, string $sourceCode, ?string $displayCode = null, ?string $locale = null): array
    {
        $sourceCode = CurrencyRegistry::normalizeCode($sourceCode);
        $locale ??= Localization::currentLocale();

        if ($sourceCode === '') {
            $sourceCode = CurrencyRegistry::defaultDisplayCode();
        }

        $sourceAmount = CurrencyConversionService::roundToCurrency(
            CurrencyConversionService::toDecimalString($amount),
            $sourceCode
        );

        $displayCode = $displayCode !== null && $displayCode !== ''
            ? CurrencyRegistry::normalizeCode($displayCode)
            : CurrencyRegistry::resolveSelected();

        if ($displayCode === '' || ! CurrencyRegistry::isActiveCode($displayCode)) {
            $displayCode = CurrencyRegistry::defaultDisplayCode();
        }

        $conversion = CurrencyConversionService::convert($sourceAmount, $sourceCode, $displayCode);

        $displayAmount = ($conversion['available'] && $conversion['converted_amount'] !== null)
            ? $conversion['converted_amount']
            : $sourceAmount;
        $displayCurrency = ($conversion['available'] && $conversion['target_currency'] !== '')
            ? $conversion['target_currency']
            : $sourceCode;

        return [
            'amount' => $sourceAmount,
            'currency' => $sourceCode,
            'formatted' => static::formatMoney($sourceAmount, $sourceCode, $locale),
            'display_amount' => $displayAmount,
            'display_currency' => $displayCurrency,
            'display_formatted' => static::formatMoney($displayAmount, $displayCurrency, $locale),
            'conversion_applied' => $conversion['conversion_applied'],
            'exchange_rate' => $conversion['exchange_rate'],
            'rate_source' => $conversion['rate_source'],
            'rate_timestamp' => $conversion['rate_timestamp'],
            'stale' => $conversion['stale'],
        ];
    }
}
