<?php

namespace App\Console\Commands;

use App\Contracts\ExchangeRateProvider;
use App\Models\ExchangeRate;
use App\Services\ActivityLogger;
use App\Services\CurrencyConversionService;
use App\Support\CurrencyRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Future scheduled FX refresh seam (Phase 13B).
 *
 * Uses the configured provider (default: manual = stored rates only,
 * always succeeds with zero external APIs). Validates every rate,
 * stores atomically, and preserves last-known-good rates on provider
 * failure. Display-only: never touches bookings or prices.
 */
class RefreshExchangeRatesCommand extends Command
{
    protected $signature = 'currency:refresh-rates';

    protected $description = 'Refresh platform exchange rates via the configured provider (manual by default).';

    public function handle(ActivityLogger $activity): int
    {
        $base = CurrencyConversionService::fxBase();
        $quotes = array_values(array_filter(
            CurrencyRegistry::activeCodes(),
            fn (string $code): bool => $code !== $base
        ));

        if ($quotes === []) {
            $this->info('No quote currencies enabled; nothing to refresh.');

            return self::SUCCESS;
        }

        try {
            $provider = app(ExchangeRateProvider::class);
            $rates = $provider->fetchRates($base, $quotes);
        } catch (\Throwable $e) {
            // Provider failure: keep last-known-good, log, never erase.
            Log::error('currency.refresh.failed', ['provider' => config('currency.provider', 'manual'), 'error' => $e->getMessage()]);
            $activity->log('currency.refresh_failed', 'system', 'Exchange-rate refresh failed; last-known-good rates preserved.');

            $this->error('Provider failed; last-known-good rates preserved.');

            return self::FAILURE;
        }

        $stored = 0;

        DB::transaction(function () use ($base, $rates, $provider, &$stored): void {
            foreach ($rates as $quote => $rate) {
                $quote = CurrencyRegistry::normalizeCode((string) $quote);

                if ($quote === '' || $quote === $base) {
                    continue;
                }

                if (! is_numeric($rate) || (float) $rate <= 0) {
                    continue; // Invalid zero/negative rates are rejected, never stored.
                }

                ExchangeRate::updateOrCreate(
                    ['base_currency_code' => $base, 'quote_currency_code' => $quote],
                    [
                        'rate' => number_format((float) $rate, 10, '.', ''),
                        'source' => $provider->key(),
                        'fetched_at' => now(),
                    ]
                );

                $stored++;
            }
        });

        CurrencyRegistry::forgetCache();
        $activity->log('currency.refresh_completed', 'system', "Exchange-rate refresh completed via {$provider->key()}: {$stored} rate(s) stored (base {$base}).");

        $this->info("Refreshed {$stored} rate(s) via {$provider->key()} (base {$base}).");

        return self::SUCCESS;
    }
}
