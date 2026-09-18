<?php

namespace App\Http\Middleware;

use App\Models\PromotionalPopup;
use App\Models\Setting;
use App\Models\TourCategory;
use App\Services\ImpersonationService;
use App\Services\PublicMenuService;
use App\Support\AdminNavigation;
use App\Support\ModuleManager;
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
            // Phase 9: cheap unread badge for bells (single count query,
            // guests get zero). Recent items load only on the page itself.
            'notificationsUnreadCount' => fn () => $request->user()
                ? $request->user()->unreadNotifications()->count()
                : 0,
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
                ->whereHas('tourPackages', fn ($query) => $query->publiclyVisible())
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
            'impersonation' => fn () => app(ImpersonationService::class)->getBannerData($request),
            'platformVersion' => config('platform.version'),
            'platformName' => config('platform.name'),
            // Phase 11.5A platform core: server-filtered admin navigation
            // (module-aware + permission-aware), effective staff
            // permissions, module states and permission-aware quick
            // actions. Only resolved for staff on admin routes.
            'adminNavigation' => fn () => $this->adminNavigation($request),
            'staffPermissions' => fn () => $this->staffPermissions($request),
            'isSuperAdmin' => function () use ($request): bool {
                $user = $request->user();

                return $user !== null && $user->isAdmin() && $user->isSuperAdmin();
            },
            'platformModules' => fn () => app(ModuleManager::class)->all(),
            'quickActions' => fn () => $this->quickActions($request),
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

    /**
     * Server-filtered admin navigation for the sidebar, sidebar search
     * and command palette. Guests and non-staff get an empty set.
     *
     * @return array<int, array<string, mixed>>
     */
    private function adminNavigation(Request $request): array
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return [];
        }

        return AdminNavigation::filteredFor($user);
    }

    /**
     * @return array<int, string>
     */
    private function staffPermissions(Request $request): array
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return [];
        }

        return $user->staffPermissionNames();
    }

    /**
     * Permission-aware "+ New" quick actions. Only currently implemented
     * destinations — never future Taxi/Hotel actions.
     *
     * @return array<int, array<string, string>>
     */
    private function quickActions(Request $request): array
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return [];
        }

        $modules = app(ModuleManager::class);

        $candidates = [
            ['id' => 'tour', 'label' => 'New Tour', 'route' => 'admin.packages.create', 'permission' => 'tours.create', 'module' => ModuleManager::TOURS, 'icon' => 'bi-map'],
            ['id' => 'booking', 'label' => 'New Booking', 'route' => 'admin.bookings.desk', 'permission' => 'bookings.create', 'module' => null, 'icon' => 'bi-calendar-check'],
            ['id' => 'taxi-booking', 'label' => 'New Taxi Booking', 'route' => 'admin.taxi.bookings.create', 'permission' => 'taxi.bookings.create', 'module' => ModuleManager::TAXI, 'icon' => 'bi-taxi-front'],
            ['id' => 'lead', 'label' => 'New Lead', 'route' => 'admin.leads.create', 'permission' => 'leads.create', 'module' => null, 'icon' => 'bi-person-lines-fill'],
            ['id' => 'quotation', 'label' => 'New Quotation', 'route' => 'admin.quotations.create', 'permission' => 'quotations.create', 'module' => null, 'icon' => 'bi-file-earmark-text'],
            ['id' => 'coupon', 'label' => 'New Coupon', 'route' => 'admin.coupons.create', 'permission' => 'marketing.coupons', 'module' => null, 'icon' => 'bi-ticket-perforated'],
            ['id' => 'staff', 'label' => 'New Staff', 'route' => 'admin.staff.create', 'permission' => 'staff.create', 'module' => null, 'icon' => 'bi-person-badge'],
            ['id' => 'page', 'label' => 'New Page', 'route' => 'admin.pages.create', 'permission' => 'content.pages', 'module' => null, 'icon' => 'bi-file-earmark-text'],
        ];

        $actions = [];

        foreach ($candidates as $candidate) {
            if ($candidate['module'] !== null && $modules->isDisabled($candidate['module'])) {
                continue;
            }

            if (! $user->can($candidate['permission'])) {
                continue;
            }

            try {
                $url = route($candidate['route'], absolute: false);
            } catch (\Throwable) {
                continue;
            }

            $actions[] = [
                'id' => $candidate['id'],
                'label' => $candidate['label'],
                'url' => $url,
                'icon' => $candidate['icon'],
            ];
        }

        return $actions;
    }
}
