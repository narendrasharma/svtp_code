<?php

namespace App\Support;

use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative locale resolution (Phase 13A).
 *
 * Resolution order for PUBLIC/CUSTOMER frontend:
 *   1. explicit session selection
 *   2. explicit first-party cookie selection
 *   3. configured platform default language
 *
 * Browser Accept-Language is intentionally NOT used: it must never
 * silently override an explicit user choice, and guessing hurts SEO
 * determinism. It can assist later behind an explicit opt-in.
 *
 * URL strategy (13A decision): NO locale-prefixed public URLs yet.
 * Introducing /{locale} across the legacy frontend before the fresh
 * marketplace frontend rebuild would churn every route with no SEO
 * benefit. Resolver + content + SEO contracts land now; prefixed
 * routes arrive with the new frontend.
 */
class Localization
{
    public const SESSION_KEY = 'locale';

    public const COOKIE_KEY = 'locale';

    public const COOKIE_MINUTES = 525600; // 12 months

    /**
     * Normalize a user-supplied code to a safe lowercase token.
     * Accepts `en`, `hi`, `ar`, `pt-BR` → `pt-br`.
     */
    public static function normalizeCode(?string $code): string
    {
        $code = strtolower(trim((string) $code));

        if (! preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $code)) {
            return '';
        }

        return $code;
    }

    public static function tableReady(): bool
    {
        try {
            return Schema::hasTable('languages');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array{code:string,locale:string,name:string,native_name:string,is_rtl:bool,is_default:bool,sort_order:int,direction:string}>
     */
    public static function activeLanguages(): array
    {
        if (! static::tableReady()) {
            return [static::fallbackLanguage()];
        }

        try {
            return Cache::remember('localization.languages.active', 3600, function (): array {
                $rows = Language::query()->active()->ordered()->get();

                if ($rows->isEmpty()) {
                    return [static::fallbackLanguage()];
                }

                return $rows->map(fn (Language $language): array => [
                    'code' => $language->code,
                    'locale' => $language->locale,
                    'name' => $language->name,
                    'native_name' => $language->native_name,
                    'is_rtl' => $language->is_rtl,
                    'is_default' => $language->is_default,
                    'sort_order' => $language->sort_order,
                    'direction' => $language->direction(),
                ])->all();
            });
        } catch (\Throwable) {
            return [static::fallbackLanguage()];
        }
    }

    public static function activeLocales(): array
    {
        return array_column(static::activeLanguages(), 'locale');
    }

    public static function isActiveLocale(?string $locale): bool
    {
        $locale = static::normalizeCode($locale);

        if ($locale === '') {
            return false;
        }

        return in_array($locale, static::activeLocales(), true);
    }

    public static function defaultLocale(): string
    {
        if (! static::tableReady()) {
            return (string) config('app.locale', 'en');
        }

        try {
            $default = Cache::remember('localization.languages.default', 3600, function (): ?string {
                return Language::query()->where('is_default', true)->value('locale')
                    ?? Language::query()->active()->ordered()->value('locale');
            });

            return is_string($default) && $default !== '' ? $default : (string) config('app.locale', 'en');
        } catch (\Throwable) {
            return (string) config('app.locale', 'en');
        }
    }

    public static function defaultLanguage(): array
    {
        foreach (static::activeLanguages() as $language) {
            if (! empty($language['is_default'])) {
                return $language;
            }
        }

        $all = static::activeLanguages();

        return $all[0] ?? static::fallbackLanguage();
    }

    /**
     * Resolve the current request locale. Explicit session/cookie only;
     * inactive or invalid selections fall back to the default safely.
     */
    public static function resolveLocale(?Request $request = null): string
    {
        $request ??= request();

        $candidates = [];

        try {
            if ($request && $request->hasSession()) {
                $candidates[] = (string) $request->session()->get(static::SESSION_KEY, '');
            }
        } catch (\Throwable) {
            // Session unavailable (e.g. middleware ordering) — cookie/default still apply.
        }

        try {
            if ($request) {
                $candidates[] = (string) $request->cookie(static::COOKIE_KEY, '');
            }
        } catch (\Throwable) {
            // Ignore unreadable cookies; default still applies.
        }

        foreach ($candidates as $candidate) {
            $normalized = static::normalizeCode($candidate);

            if ($normalized !== '' && static::isActiveLocale($normalized)) {
                return $normalized;
            }
        }

        return static::defaultLocale();
    }

    public static function currentLocale(): string
    {
        try {
            return (string) app()->getLocale();
        } catch (\Throwable) {
            return static::defaultLocale();
        }
    }

    public static function direction(?string $locale = null): string
    {
        $locale ??= static::currentLocale();

        foreach (static::activeLanguages() as $language) {
            if ($language['locale'] === $locale) {
                return $language['direction'];
            }
        }

        return 'ltr';
    }

    public static function isRtl(?string $locale = null): bool
    {
        return static::direction($locale) === 'rtl';
    }

    public static function forgetCache(): void
    {
        Cache::forget('localization.languages.active');
        Cache::forget('localization.languages.default');
    }

    /**
     * Hardcoded safe fallback when the languages table is unavailable
     * (fresh install before migration, installer, tests bootstrapping).
     *
     * @return array{code:string,locale:string,name:string,native_name:string,is_rtl:bool,is_default:bool,sort_order:int,direction:string}
     */
    public static function fallbackLanguage(): array
    {
        return [
            'code' => 'en',
            'locale' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'is_rtl' => false,
            'is_default' => true,
            'sort_order' => 0,
            'direction' => 'ltr',
        ];
    }
}
