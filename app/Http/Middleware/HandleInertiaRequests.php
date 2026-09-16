<?php

namespace App\Http\Middleware;

use App\Models\PromotionalPopup;
use App\Models\Setting;
use App\Models\TourCategory;
use App\Services\PublicMenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('flash'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'siteSettings' => function () {

                $settings = Setting::getAllSettings();

                return [
                    'site_name' => $settings['site_name']
                        ?? config('app.name'),

                    'site_tagline' => $settings['site_tagline']
                        ?? null,

                    'copyright_text' => $settings['copyright_text']
                        ?? 'All rights reserved.',

                    'site_logo' => $settings['site_logo']
                        ?? null,

                    'site_favicon' => $settings['site_favicon']
                        ?? null,

                    'primary_phone' => $settings['primary_phone']
                        ?? null,

                    'secondary_phone' => $settings['secondary_phone']
                        ?? null,

                    'contact_email' => $settings['contact_email']
                        ?? null,

                    'website_url' => $settings['website_url']
                        ?? null,

                    'office_address' => $settings['office_address']
                        ?? null,

                    'google_maps_url' => $settings['google_maps_url']
                        ?? null,

                    'facebook_url' => $settings['facebook_url']
                        ?? null,

                    'instagram_url' => $settings['instagram_url']
                        ?? null,

                    'youtube_url' => $settings['youtube_url']
                        ?? null,

                    'whatsapp_number' => $settings['whatsapp_number']
                        ?? null,

                    'whatsapp_message' => $settings['whatsapp_message']
                        ?? null,
                    'seo_meta_title' => $settings['seo_meta_title']
                        ?? null,

                    'seo_meta_description' => $settings['seo_meta_description']
                        ?? null,

                    'seo_meta_keywords' => $settings['seo_meta_keywords']
                        ?? null,

                    'seo_og_image' => $settings['seo_og_image']
                        ?? null,

                    'seo_index' => ($settings['seo_index'] ?? '1') === '1',

                    'seo_follow' => ($settings['seo_follow'] ?? '1') === '1',

                    'google_site_verification' => $settings['google_site_verification']
                        ?? null,
                ];
            },
            'securityQuestion' => $this->securityQuestion($request),
            'navigation' => fn () => $request->routeIs('admin.*')
                ? ['header' => [], 'footer' => []]
                : app(PublicMenuService::class)->navigation(),
            'tourCategories' => fn () => TourCategory::active()
                ->whereHas('tourPackages', fn ($query) => $query->active())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'icon']),
            'promotionalPopup' => fn () => Cache::remember('active.promotional_popup', 3600, function (): ?array {
                $popup = PromotionalPopup::query()
                    ->where('is_active', true)
                    ->whereNotNull('image_path')
                    ->latest('id')
                    ->first(['id', 'image_path', 'title', 'cta_url']);

                return $popup ? [
                    'id' => $popup->id,
                    'image_url' => Storage::disk('public')->url($popup->image_path),
                    'title' => $popup->title,
                    'cta_url' => $popup->cta_url,
                ] : null;
            }),
        ];
    }

    private function securityQuestion(Request $request): string
    {
        if (! $request->session()->has('enquiry_math_answer')
            || ! $request->session()->has('enquiry_math_question')) {
            $left = random_int(1, 9);
            $right = random_int(1, 9);

            $request->session()->put([
                'enquiry_math_answer' => $left + $right,
                'enquiry_math_question' => "{$left} + {$right} = ?",
            ]);
        }

        return $request->session()->get('enquiry_math_question');
    }
}
