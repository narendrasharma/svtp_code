<?php

namespace App\Support;

/**
 * Immutable FX snapshot foundation (Phase 13B).
 *
 * Rule: persist conversion metadata ONLY when conversion is actually
 * part of the financial transaction. Merely VIEWING a booking in a
 * display currency must never write FX into booking history.
 *
 * When a future flow settles in a converted currency, embed
 * FxSnapshot::make(...) into the booking's pricing_snapshot — the
 * authoritative currency/amount columns stay the source of truth and
 * old snapshots never recalculate.
 */
class FxSnapshot
{
    /**
     * @return array{source_currency:string,transaction_currency:string,source_amount:string,transaction_amount:string,rate:string,source:string,rate_timestamp:?string,stale:bool,captured_at:string}
     */
    public static function make(
        string $sourceCurrency,
        string $transactionCurrency,
        int|float|string $sourceAmount,
        int|float|string $transactionAmount,
        string $rate,
        string $source,
        ?string $rateTimestamp = null,
        bool $stale = false,
    ): array {
        return [
            'source_currency' => CurrencyRegistry::normalizeCode($sourceCurrency),
            'transaction_currency' => CurrencyRegistry::normalizeCode($transactionCurrency),
            'source_amount' => (string) $sourceAmount,
            'transaction_amount' => (string) $transactionAmount,
            'rate' => (string) $rate,
            'source' => mb_substr($source, 0, 20),
            'rate_timestamp' => $rateTimestamp,
            'stale' => $stale,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Build a snapshot from a conversion DTO. Returns null when the
     * conversion was not applied (same currency / missing rate) — i.e.
     * there is nothing to snapshot.
     *
     * @param  array<string, mixed>  $conversion  CurrencyConversionService::convert() output
     */
    public static function fromConversion(array $conversion): ?array
    {
        if (empty($conversion['available']) || empty($conversion['conversion_applied'])) {
            return null;
        }

        return static::make(
            (string) ($conversion['source_currency'] ?? ''),
            (string) ($conversion['target_currency'] ?? ''),
            (string) ($conversion['source_amount'] ?? '0'),
            (string) ($conversion['converted_amount'] ?? '0'),
            (string) ($conversion['exchange_rate'] ?? ''),
            (string) ($conversion['rate_source'] ?? 'manual'),
            isset($conversion['rate_timestamp']) ? (string) $conversion['rate_timestamp'] : null,
            (bool) ($conversion['stale'] ?? false)
        );
    }
}
