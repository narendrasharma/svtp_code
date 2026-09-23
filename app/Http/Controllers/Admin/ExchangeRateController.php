<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\CurrencyConversionService;
use App\Support\CurrencyRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manual exchange-rate management (Phase 13B).
 *
 * Manual mode is mandatory and fully functional with zero external
 * APIs. Rates store against the canonical FX base only.
 */
class ExchangeRateController extends Controller
{
    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate([
            'quote_currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'rate' => ['required', 'numeric', 'gt:0', 'lt:1000000000'],
        ]);

        $base = CurrencyConversionService::fxBase();
        $quote = CurrencyRegistry::normalizeCode($validated['quote_currency_code']);

        if ($quote === $base) {
            return back()->withErrors(['quote_currency_code' => 'The FX base currency always converts 1:1 and needs no stored rate.']);
        }

        $rate = ExchangeRate::updateOrCreate(
            ['base_currency_code' => $base, 'quote_currency_code' => $quote],
            [
                'rate' => number_format((float) $validated['rate'], 10, '.', ''),
                'source' => ExchangeRate::SOURCE_MANUAL,
                'fetched_at' => now(),
            ]
        );

        CurrencyRegistry::forgetCache();

        $activity->log('currency.rate_updated', 'system', "Manual rate {$base}→{$quote} set to {$rate->rate}.", null, null, ['base' => $base, 'quote' => $quote, 'rate' => (string) $rate->rate]);

        return back()->with('flash', 'Exchange rate saved.');
    }

    public function destroy(ExchangeRate $exchangeRate): RedirectResponse
    {
        $exchangeRate->delete();
        CurrencyRegistry::forgetCache();

        return back()->with('flash', 'Exchange rate removed. Affected displays fall back to authoritative amounts.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fx_base' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'max_rate_age_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        Setting::setValue('currency.fx_base', strtoupper($validated['fx_base']));
        Setting::setValue('currency.max_rate_age_hours', (string) $validated['max_rate_age_hours']);
        CurrencyRegistry::forgetCache();

        return back()->with('flash', 'FX settings updated. Existing stored rates keep their original base until refreshed.');
    }
}
