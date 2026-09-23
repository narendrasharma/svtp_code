<?php

namespace App\Support;

/**
 * Locale-aware SEO contract (Phase 13A).
 *
 * Exposes: current locale, localized title/description with
 * default-locale → original fallback, canonical, and hreflang
 * alternates. hreflang entries are ONLY emitted for URLs that
 * actually exist: since 13A defers locale-prefixed public routes,
 * alternates default to [] (contract ready, no invalid links).
 */
class SeoLocalization
{
    /**
     * @return array{locale:string,direction:string,title:?string,description:?string,canonical:?string,hreflang:array<int,array{locale:string,url:string}>}
     */
    public static function forModel(
        object $model,
        string $titleField,
        string $descriptionField,
        ?string $canonical = null,
        ?string $locale = null,
    ): array {
        $locale ??= Localization::currentLocale();

        $title = method_exists($model, 'translated')
            ? $model->translated($titleField, $locale)
            : ($model->{$titleField} ?? null);

        $description = method_exists($model, 'translated')
            ? $model->translated($descriptionField, $locale)
            : ($model->{$descriptionField} ?? null);

        return [
            'locale' => $locale,
            'direction' => Localization::direction($locale),
            'title' => is_string($title) && trim($title) !== '' ? $title : null,
            'description' => is_string($description) && trim($description) !== '' ? $description : null,
            'canonical' => $canonical,
            // Deferred: locale-prefixed alternates arrive with the new
            // frontend. Never emit hreflang for nonexistent URLs.
            'hreflang' => [],
        ];
    }

    /**
     * Build explicit hreflang alternates ONLY when the caller supplies
     * real per-locale URLs (validated non-empty http(s)/relative URLs).
     *
     * @param  array<string, string>  $urlsByLocale
     * @return array<int, array{locale:string,url:string}>
     */
    public static function alternates(array $urlsByLocale): array
    {
        $out = [];

        foreach ($urlsByLocale as $locale => $url) {
            $locale = Localization::normalizeCode((string) $locale);

            if ($locale === '' || ! Localization::isActiveLocale($locale)) {
                continue;
            }

            if (! is_string($url) || trim($url) === '') {
                continue;
            }

            // Refuse arbitrary injection: only relative paths or http(s).
            if (! preg_match('#^(https?://|/)#i', trim($url))) {
                continue;
            }

            $out[] = ['locale' => $locale, 'url' => trim($url)];
        }

        return $out;
    }
}
