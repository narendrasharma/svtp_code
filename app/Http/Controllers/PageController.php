<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\Localization;
use App\Support\SeoLocalization;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Show a public CMS page.
     */
    public function show(string $slug): Response
    {
        // Only fetch active pages; 404 if not found or inactive.
        $page = Page::where('slug', $slug)
            ->where('is_active', true)
            ->withLocaleTranslations()
            ->firstOrFail();

        $locale = Localization::currentLocale();

        // Localized SEO with default-locale → original fallback, never blank.
        $seo = SeoLocalization::forModel($page, 'meta_title', 'meta_description', null, $locale);
        $seo['title'] ??= $page->translated('title', $locale) ?? $page->title;

        // Pass only the fields needed by the frontend.
        return Inertia::render('Cms/Page', [
            'page' => [
                'title' => $page->translated('title', $locale) ?? $page->title,
                'content' => $page->translated('content', $locale) ?? $page->content,
                'template' => $page->template ?? 'default',
                'meta_title' => $seo['title'],
                'meta_description' => $seo['description'] ?? $page->meta_description,
                'excerpt' => $page->translated('excerpt', $locale) ?? $page->excerpt,
                'slug' => $page->slug,
                'locale' => $locale,
            ],
            'localizedSeo' => $seo,
        ]);
    }
}
