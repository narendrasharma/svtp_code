<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\CurrencyConversionService;
use App\Support\CurrencyRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared platform currency management (Phase 13B).
 *
 * Module-independent: available regardless of Hotels/Tours/Taxi state.
 * Only DISPLAY metadata lives here — domain authoritative prices are
 * never touched by these actions.
 */
class CurrencyController extends Controller
{
    public function index(): Response
    {
        $currencies = Currency::query()->ordered()->get();
        $base = CurrencyConversionService::fxBase();
        $rates = ExchangeRate::query()->where('base_currency_code', $base)->orderBy('quote_currency_code')->get();

        return Inertia::render('Admin/Currencies/Index', [
            'currencies' => $currencies,
            'fxBase' => $base,
            'rates' => $rates,
            'maxRateAgeHours' => CurrencyConversionService::maxRateAgeHours(),
            'provider' => (string) (Setting::getValue('currency.provider', 'manual') ?? 'manual'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Currencies/Form', ['currency' => null]);
    }

    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $validated = $this->validateCurrency($request);

        if (($validated['is_default_display'] ?? false)) {
            $validated['is_active'] = true;
            Currency::query()->where('is_default_display', true)->update(['is_default_display' => false]);
        }

        $currency = Currency::create($validated);
        CurrencyRegistry::forgetCache();

        if (Currency::query()->where('is_default_display', true)->count() === 0) {
            $first = Currency::query()->orderBy('id')->first();
            $first?->forceFill(['is_default_display' => true, 'is_active' => true])->save();
            CurrencyRegistry::forgetCache();
        }

        $activity->log('currency.created', 'system', "Currency {$currency->code} created.", $currency, null, $currency->only(['code', 'name', 'is_active', 'is_default_display']));

        return redirect()->route('admin.currencies.index')->with('flash', 'Currency saved.');
    }

    public function edit(Currency $currency): Response
    {
        return Inertia::render('Admin/Currencies/Form', ['currency' => $currency]);
    }

    public function update(Request $request, Currency $currency, ActivityLogger $activity): RedirectResponse
    {
        $validated = $this->validateCurrency($request, $currency);
        $old = $currency->only(['code', 'name', 'is_active', 'is_default_display']);

        $wantsDefault = (bool) ($validated['is_default_display'] ?? false);
        $wantsInactive = ! (bool) ($validated['is_active'] ?? true);

        if ($wantsDefault) {
            $validated['is_active'] = true;
        }

        if ($currency->is_default_display && $wantsInactive && ! $wantsDefault) {
            $other = Currency::query()->whereKeyNot($currency->id)->where('is_default_display', true)->exists();

            if (! $other) {
                return back()->withErrors(['is_active' => 'The default display currency must stay active. Set another default first.']);
            }
        }

        if ($currency->is_default_display && ! $wantsDefault) {
            $other = Currency::query()->whereKeyNot($currency->id)->where('is_default_display', true)->exists();

            if (! $other) {
                $validated['is_default_display'] = true;
                $validated['is_active'] = true;
            }
        }

        if (! empty($validated['is_default_display'])) {
            Currency::query()->whereKeyNot($currency->id)->where('is_default_display', true)->update(['is_default_display' => false]);
        }

        $currency->update($validated);
        CurrencyRegistry::forgetCache();

        $activity->log('currency.updated', 'system', "Currency {$currency->code} updated.", $currency, $old, $currency->only(['code', 'name', 'is_active', 'is_default_display']));

        return redirect()->route('admin.currencies.index')->with('flash', 'Currency updated.');
    }

    public function setDefault(Currency $currency, ActivityLogger $activity): RedirectResponse
    {
        if (! $currency->is_active) {
            return back()->withErrors(['is_default_display' => 'Only an active currency can be the default display currency.']);
        }

        Currency::query()->where('is_default_display', true)->update(['is_default_display' => false]);
        $currency->forceFill(['is_default_display' => true, 'is_active' => true])->save();
        CurrencyRegistry::forgetCache();

        $activity->log('currency.default_changed', 'system', "Default display currency set to {$currency->code}.", $currency);

        return back()->with('flash', 'Default display currency updated.');
    }

    public function toggle(Currency $currency): RedirectResponse
    {
        if ($currency->is_default_display && $currency->is_active) {
            return back()->withErrors(['is_active' => 'The default display currency cannot be deactivated. Set another default first.']);
        }

        $currency->forceFill(['is_active' => ! $currency->is_active])->save();
        CurrencyRegistry::forgetCache();

        return back()->with('flash', 'Currency status updated.');
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->is_default_display) {
            return back()->withErrors(['currency' => 'The default display currency cannot be deleted. Set another default first.']);
        }

        if (Currency::query()->count() <= 1) {
            return back()->withErrors(['currency' => 'At least one currency must remain.']);
        }

        // Historical records keep their stored currency code strings —
        // deleting a registry row never rewrites old bookings.
        $currency->delete();
        CurrencyRegistry::forgetCache();

        return back()->with('flash', 'Currency removed. Historical records using it remain readable.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateCurrency(Request $request, ?Currency $currency = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/', Rule::unique('currencies', 'code')->ignore($currency?->id)],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:12'],
            'decimal_digits' => ['sometimes', 'integer', 'min:0', 'max:4'],
            'symbol_position' => ['sometimes', 'in:before,after'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default_display' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);
    }
}
