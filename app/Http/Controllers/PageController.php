<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\HotelHtml;
use App\Support\Localization;
use App\Support\SeoLocalization;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    /**
     * Render a shared public content surface from the active CMS page when
     * one exists. Optional content stays intentionally sparse until it is
     * published by the marketplace administrator.
     */
    public function about(): Response
    {
        return $this->renderPublicPage(
            'about',
            'Static/About',
            'about',
            'About the travel marketplace',
            'A shared space for discovering destinations, places, stays, and journeys.',
        );
    }

    public function faq(): Response
    {
        return $this->renderPublicPage(
            'faq',
            'Static/Faq',
            'faq',
            'Frequently asked questions',
            'Answers will appear here as the marketplace publishes its help content.',
        );
    }

    public function privacy(): Response
    {
        return $this->renderPublicPage(
            'privacy',
            'Static/Privacy',
            'privacy',
            'Privacy policy',
            'The privacy policy for this marketplace has not been published yet.',
            true,
        );
    }

    public function terms(): Response
    {
        return $this->renderPublicPage(
            'terms',
            'Static/Terms',
            'terms',
            'Terms and conditions',
            'The terms and conditions for this marketplace have not been published yet.',
            true,
        );
    }

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
        $seo = SeoLocalization::forModel(
            $page,
            'meta_title',
            'meta_description',
            route('cms.page', ['slug' => $page->slug], absolute: true),
            $locale,
        );
        $seo['title'] ??= $page->translated('title', $locale) ?? $page->title;

        // Pass only the fields needed by the frontend.
        return Inertia::render('Cms/Page', [
            'page' => [
                'title' => $page->translated('title', $locale) ?? $page->title,
                'content' => HotelHtml::clean($page->translated('content', $locale) ?? $page->content, 30000),
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

    private function renderPublicPage(
        string $slug,
        string $component,
        string $routeName,
        string $fallbackTitle,
        string $fallbackDescription,
        bool $noindex = false,
    ): Response {
        $locale = Localization::currentLocale();
        $page = Page::query()
            ->active()
            ->where('slug', $slug)
            ->withLocaleTranslations()
            ->first();

        $title = $page?->translated('title', $locale) ?? $page?->title ?? $fallbackTitle;
        $description = $page?->translated('meta_description', $locale)
            ?? $page?->translated('excerpt', $locale)
            ?? $page?->meta_description
            ?? $fallbackDescription;
        $seo = $page
            ? SeoLocalization::forModel($page, 'meta_title', 'meta_description', route($routeName, absolute: true), $locale)
            : [
                'locale' => $locale,
                'direction' => Localization::direction($locale),
                'title' => $title,
                'description' => $description,
                'canonical' => route($routeName, absolute: true),
                'hreflang' => [],
            ];
        $seo['title'] ??= $title;
        $seo['description'] ??= $description;
        $content = $page
            ? HotelHtml::clean($page->translated('content', $locale) ?? $page->content, 30000)
            : null;

        return Inertia::render($component, [
            'page' => [
                'title' => $title,
                'content' => $content,
                'excerpt' => $page?->translated('excerpt', $locale) ?? $page?->excerpt,
                'meta_title' => $seo['title'],
                'meta_description' => $seo['description'],
                'content_available' => filled($content),
                'locale' => $locale,
            ],
            'localizedSeo' => $seo,
            'noindex' => $noindex,
        ]);
    }
}
