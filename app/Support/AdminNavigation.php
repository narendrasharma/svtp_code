<?php

namespace App\Support;

use App\Models\User;

/**
 * Central admin navigation registry (Phase 11.5A).
 *
 * ONE definition drives the sidebar, the sidebar search and the global
 * command-palette navigation results — never hardcode admin links in
 * three places. Each entry: id, label, icon, route (name), params,
 * group, module (nullable), permission (nullable), keywords, order.
 *
 * Filtering is server-side (module-aware + permission-aware) so hidden
 * links are never leaked to unauthorized staff.
 */
class AdminNavigation
{
    /**
     * @return array<int, array{key:string,label:string,items:array<int,array<string,mixed>>}>
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'items' => [
                    self::item('dashboard', 'Dashboard', 'bi-speedometer2', 'admin.dashboard', null, 'dashboard.view', ['home', 'overview', 'stats', 'analytics'], 10),
                ],
            ],
            [
                'key' => 'locations',
                'label' => 'Locations',
                'items' => [
                    self::item('countries', 'Countries', 'bi-globe', 'admin.countries.index', null, 'locations.countries.view', ['country', 'countries', 'iso', 'currency country'], 10),
                    self::item('states', 'States & Regions', 'bi-map', 'admin.states.index', null, 'locations.states.view', ['state', 'region', 'province'], 20),
                    self::item('cities', 'Cities', 'bi-buildings', 'admin.cities.index', null, 'locations.cities.view', ['city', 'cities', 'town'], 30),
                    self::item('destinations', 'Destinations', 'bi-geo-alt', 'admin.destinations.index', ModuleManager::TOURS, 'tours.view', ['destination', 'travel zone', 'area', 'island'], 40),
                    self::item('places', 'Places', 'bi-pin-map', 'admin.places.index', ModuleManager::TOURS, 'tours.view', ['place', 'attraction', 'landmark', 'sightseeing'], 50),
                ],
            ],
            [
                'key' => 'tours',
                'label' => 'Tours',
                'module' => ModuleManager::TOURS,
                'items' => [
                    self::item('packages', 'Tour Packages', 'bi-map', 'admin.packages.index', null, 'tours.view', ['tours', 'packages', 'itinerary', 'price'], 10),
                    self::item('tour-categories', 'Categories', 'bi-grid', 'admin.tour-categories.index', null, 'tours.view', ['category', 'categories'], 20),
                    self::item('tags', 'Tags', 'bi-tags', 'admin.tags.index', null, 'tours.view', ['tag', 'label'], 50),
                ],
            ],
            [
                'key' => 'marketplace',
                'label' => 'Marketplace',
                'items' => [
                    self::item('vendor-applications', 'Vendor Applications', 'bi-shop', 'admin.vendor-applications.index', null, 'vendors.view', ['vendor', 'application', 'onboarding', 'seller'], 10),
                    self::item('reviews', 'Reviews', 'bi-star', 'admin.reviews.index', null, 'tours.view', ['review', 'rating', 'testimonial', 'moderation'], 20),
                    self::item('vendor-plans', 'Vendor Plans', 'bi-award', 'admin.vendor-plans.index', null, 'vendors.view', ['plan', 'subscription', 'pricing plan', 'entitlement'], 30),
                ],
            ],
            [
                'key' => 'bookings',
                'label' => 'Bookings',
                'items' => [
                    self::item('bookings-new', 'New Booking', 'bi-plus-circle', 'admin.bookings.desk', null, 'bookings.create', ['new booking', 'reservation desk', 'walk-in', 'walk in', 'phone booking', 'offline'], 5),
                    self::item('bookings', 'All Bookings', 'bi-calendar-check', 'admin.bookings.index', null, 'bookings.view', ['booking', 'reservation', 'cancellation', 'refund', 'reference', 'trip', 'payment', 'reschedule'], 10),
                    self::item('enquiries', 'Enquiries', 'bi-chat-left-text', 'admin.enquiries.index', null, 'bookings.view', ['enquiry', 'inquiry', 'lead', 'contact', 'callback'], 20),
                ],
            ],
            [
                'key' => 'taxi',
                'label' => 'Taxi',
                'module' => ModuleManager::TAXI,
                'items' => [
                    self::item('taxi-dashboard', 'Taxi Dashboard', 'bi-taxi-front', 'admin.taxi.dashboard', null, 'taxi.dashboard.view', ['taxi', 'rides', 'fleet overview', 'cabs'], 5),
                    self::item('taxi-dispatch', 'Taxi Dispatch', 'bi-kanban', 'admin.taxi.dispatch.index', null, 'taxi.bookings.view', ['taxi dispatch', 'dispatch board', 'assign driver', 'operations'], 7),
                    self::item('taxi-tracking', 'Taxi Tracking', 'bi-geo-alt', 'admin.taxi.tracking.index', null, 'taxi.bookings.view', ['taxi tracking', 'live location', 'driver tracking', 'gps'], 9),
                    self::item('taxi-bookings-new', 'New Taxi Booking', 'bi-plus-circle', 'admin.taxi.bookings.create', null, 'taxi.bookings.create', ['new taxi', 'one way', 'airport transfer', 'cab booking'], 8),
                    self::item('taxi-bookings', 'Taxi Bookings', 'bi-taxi-front-fill', 'admin.taxi.bookings.index', null, 'taxi.bookings.view', ['taxi booking', 'ride', 'pickup', 'drop', 'airport', 'one way'], 10),
                    self::item('taxi-pricing', 'Taxi Pricing', 'bi-currency-exchange', 'admin.taxi.pricing.index', null, 'taxi.pricing.view', ['taxi pricing', 'rate card', 'fare', 'rental package'], 15),
                    self::item('taxi-drivers', 'Drivers', 'bi-person-badge', 'admin.taxi.drivers.index', null, 'taxi.drivers.view', ['driver', 'chauffeur', 'licence', 'availability'], 20),
                    self::item('taxi-cancellation-policies', 'Cancellation Policies', 'bi-calendar-x', 'admin.taxi.cancellation-policies.index', null, 'taxi.cancellations.view', ['cancel', 'refund', 'reschedule'], 28),
                    self::item('taxi-reviews', 'Taxi Reviews', 'bi-star', 'admin.taxi.reviews.index', null, 'taxi.reviews.view', ['taxi review', 'rating', 'service quality', 'feedback'], 29),
                    self::item('taxi-earnings', 'Driver Earnings', 'bi-cash-coin', 'admin.taxi.earnings.index', null, 'taxi.driver_earnings.view', ['driver earning', 'payout', 'compensation', 'settlement', 'salary'], 25),
                    self::item('taxi-payouts', 'Driver Payouts', 'bi-bank', 'admin.taxi.payouts.index', null, 'taxi.driver_payouts.view', ['payout', 'settle', 'pay driver', 'batch'], 27),
                    self::item('taxi-vehicles', 'Vehicles', 'bi-truck-front', 'admin.taxi.vehicles.index', null, 'taxi.vehicles.view', ['vehicle', 'fleet', 'car', 'cab', 'registration'], 30),
                    self::item('taxi-vehicle-types', 'Vehicle Types', 'bi-car-front', 'admin.taxi.vehicle-types.index', null, 'taxi.vehicle_types.view', ['vehicle type', 'sedan', 'suv', 'category'], 40),
                    self::item('taxi-settings', 'Taxi Settings', 'bi-gear', 'admin.taxi.settings.index', null, 'taxi.settings.manage', ['taxi settings', 'advance booking', 'trip types'], 50),
                ],
            ],
            [
                'key' => 'hotels',
                'label' => 'Hotels',
                'module' => ModuleManager::HOTELS,
                'items' => [
                    self::item('hotel-properties', 'Properties', 'bi-buildings', 'admin.hotel.properties.index', null, 'hotel.properties.view', ['hotel', 'property', 'resort', 'villa', 'homestay', 'listing'], 10),
                    self::item('hotel-reviews', 'Reviews', 'bi-star', 'admin.hotel.reviews.index', null, 'hotel.reviews.view', ['hotel review', 'rating', 'verified stay', 'moderation'], 12),
                    self::item('hotel-inventory', 'Inventory', 'bi-calendar-check', 'admin.hotel.inventory.index', null, 'hotel.inventory.view', ['inventory', 'availability', 'calendar', 'stop sell', 'blocked'], 15),
                    self::item('hotel-rate-plans', 'Rate Plans', 'bi-currency-exchange', 'admin.hotel.rate-plans.index', null, 'hotel.rate_plans.view', ['rate plan', 'pricing', 'rates', 'meal plan', 'season'], 16),
                    self::item('hotel-daily-rates', 'Daily Rates', 'bi-calendar2-week', 'admin.hotel.daily-rates.index', null, 'hotel.pricing.view', ['daily rate', 'price calendar', 'seasonal price'], 17),
                    self::item('hotel-charges', 'Taxes & Fees', 'bi-receipt', 'admin.hotel.charges.index', null, 'hotel.charges.manage', ['tax', 'fee', 'city tax', 'charges'], 18),
                    self::item('hotel-property-types', 'Property Types', 'bi-tags', 'admin.hotel.property-types.index', null, 'hotel.property_types.manage', ['property type', 'hotel type', 'resort', 'villa'], 20),
                    self::item('hotel-amenities', 'Amenities', 'bi-stars', 'admin.hotel.amenities.index', null, 'hotel.amenities.manage', ['amenity', 'wifi', 'pool', 'facilities'], 30),
                    self::item('hotel-bed-types', 'Bed Types', 'bi-lamp', 'admin.hotel.bed-types.index', null, 'hotel.bed_types.manage', ['bed', 'king', 'twin', 'bunk', 'sleeping'], 35),
                    self::item('hotel-custom-fields', 'Custom Fields', 'bi-input-cursor-text', 'admin.hotel.custom-fields.index', null, 'hotel.custom_fields.view', ['custom field', 'metadata', 'extra field', 'attribute'], 40),
                    self::item('hotel-settings', 'Hotel Settings', 'bi-gear', 'admin.hotel.settings.index', null, 'hotel.settings.manage', ['hotel settings', 'approval', 'currency'], 50),
                ],
            ],
            [
                'key' => 'crm',
                'label' => 'CRM',
                'items' => [
                    self::item('crm-dashboard', 'CRM Overview', 'bi-kanban', 'admin.crm.index', null, 'leads.view', ['crm', 'overview', 'pipeline', 'sales dashboard'], 5),
                    self::item('leads', 'Leads', 'bi-person-lines-fill', 'admin.leads.index', null, 'leads.view', ['lead', 'prospect', 'pipeline', 'assign', 'convert'], 10),
                    self::item('follow-ups', 'Follow-ups', 'bi-bell', 'admin.follow-ups.index', null, 'leads.view', ['follow-up', 'followup', 'reminder', 'due', 'overdue', 'call back'], 20),
                    self::item('quotations', 'Quotations', 'bi-file-earmark-text', 'admin.quotations.index', null, 'quotations.view', ['quotation', 'quote', 'offer', 'price quote', 'revision'], 30),
                    self::item('lead-sources', 'Lead Sources', 'bi-tags', 'admin.lead-sources.index', null, 'leads.update', ['source', 'channel', 'attribution', 'website', 'referral'], 40),
                ],
            ],
            [
                'key' => 'support',
                'label' => 'Support',
                'items' => [
                    self::item('support-tickets', 'Tickets', 'bi-life-preserver', 'admin.support.index', null, 'support.view', ['support', 'ticket', 'helpdesk', 'complaint', 'query'], 10),
                    self::item('support-categories', 'Ticket Categories', 'bi-tag', 'admin.support-categories.index', null, 'support.manage_categories', ['category', 'ticket type'], 20),
                ],
            ],
            [
                'key' => 'communications',
                'label' => 'Communications',
                'items' => [
                    self::item('campaigns', 'Campaigns', 'bi-megaphone', 'admin.campaigns.index', null, 'campaigns.view', ['campaign', 'newsletter', 'bulk', 'promotion', 'marketing email'], 10),
                    self::item('templates', 'Templates', 'bi-envelope-paper', 'admin.templates.index', null, 'communications.manage_templates', ['template', 'message template', 'email template', 'sms', 'whatsapp template'], 20),
                    self::item('communication-logs', 'Message Log', 'bi-journal-text', 'admin.communication-logs.index', null, 'communications.view_logs', ['log', 'history', 'sent messages', 'audit'], 30),
                ],
            ],
            [
                'key' => 'finance',
                'label' => 'Finance',
                'items' => [
                    self::item('withdrawals', 'Withdrawals', 'bi-cash-stack', 'admin.withdrawals.index', null, 'finance.view', ['withdrawal', 'payout', 'settlement', 'ledger', 'adjustment', 'money'], 10),
                    self::item('payout-accounts', 'Payout Accounts', 'bi-bank', 'admin.payout-accounts.index', null, 'finance.view', ['payout account', 'bank', 'upi', 'kyc bank', 'verify account'], 20),
                ],
            ],
            [
                'key' => 'marketing',
                'label' => 'Marketing',
                'items' => [
                    self::item('coupons', 'Coupons', 'bi-ticket-perforated', 'admin.coupons.index', null, 'marketing.coupons', ['coupon', 'discount', 'promo', 'offer', 'code'], 10),
                    self::item('promotional-popup', 'Promotional Popups', 'bi-window-stack', 'admin.promotional-popup.index', null, 'marketing.view', ['popup', 'promotion', 'banner popup', 'offer popup'], 20),
                ],
            ],
            [
                'key' => 'content',
                'label' => 'Content',
                'items' => [
                    self::item('pages', 'Pages', 'bi-file-earmark-text', 'admin.pages.index', null, 'content.pages', ['page', 'cms', 'about', 'privacy', 'terms', 'faq', 'blog', 'gallery', 'team'], 10),
                    self::item('menus', 'Menus', 'bi-list', 'admin.menus.index', null, 'content.menus', ['menu', 'navigation', 'header', 'footer', 'link'], 20),
                    self::item('homepage-sections', 'Homepage Sections', 'bi-columns-gap', 'admin.homepage-sections.index', null, 'content.pages', ['homepage', 'home', 'section', 'hero'], 30),
                    self::item('banners', 'Banners', 'bi-images', 'admin.banners.index', null, 'marketing.view', ['banner', 'slider', 'hero image'], 40),
                ],
            ],
            [
                'key' => 'users',
                'label' => 'Users',
                'items' => [
                    self::item('users', 'All Users', 'bi-people', 'admin.users.index', null, 'users.view', ['user', 'customer', 'account', 'member'], 10),
                    self::item('staff', 'Staff', 'bi-person-badge', 'admin.staff.index', null, 'staff.view', ['staff', 'team', 'employee', 'executive', 'manager'], 20),
                    self::item('roles', 'Roles & Permissions', 'bi-shield-lock', 'admin.roles.index', null, 'roles.manage', ['role', 'permission', 'access', 'rbac'], 30),
                ],
            ],
            [
                'key' => 'system',
                'label' => 'System',
                'items' => [
                    self::item('settings', 'Settings', 'bi-gear', 'admin.settings.index', null, 'settings.view', ['setting', 'configuration', 'general', 'contact', 'social', 'marketplace commission'], 10),
                    self::item('settings-seo', 'SEO Settings', 'bi-search', 'admin.settings.index', null, 'settings.view', ['seo', 'meta', 'search engine', 'google', 'og image', 'index'], 20),
                    self::item('modules', 'Modules', 'bi-grid-1x2', 'admin.modules.index', null, 'modules.manage', ['module', 'taxi', 'hotels', 'tours module', 'enable', 'disable'], 30),
                    self::item('number-series', 'Number Series', 'bi-123', 'admin.number-series.index', null, 'settings.view', ['number series', 'reference', 'prefix', 'sequence', 'booking reference', 'invoice number'], 40),
                    self::item('reports', 'Reports', 'bi-bar-chart', 'admin.reports.index', null, 'reports.view', ['report', 'analytics', 'booking report', 'finance report', 'export', 'csv', 'commission'], 50),
                    self::item('activity-logs', 'Activity Logs', 'bi-activity', 'admin.activity-logs.index', null, 'audit.view', ['audit', 'activity', 'log', 'trail', 'history', 'who changed'], 60),
                    self::item('system-health', 'System Health', 'bi-heart-pulse', 'admin.system.index', null, 'system.health.view', ['system', 'health', 'status', 'scheduler', 'queue', 'cron', 'worker', 'failed jobs', 'version', 'php'], 70),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function item(string $id, string $label, string $icon, string $route, ?string $module, ?string $permission, array $keywords, int $order): array
    {
        return compact('id', 'label', 'icon', 'route', 'module', 'permission', 'keywords', 'order');
    }

    /**
     * Navigation filtered for a user: module-aware + permission-aware,
     * with /code-safe absolute-path URLs resolved server-side.
     *
     * @return array<int, array{key:string,label:string,items:array<int,array<string,mixed>>}>
     */
    public static function filteredFor(?User $user, ?ModuleManager $modules = null): array
    {
        $modules ??= app(ModuleManager::class);

        $result = [];

        foreach (static::groups() as $group) {
            // Whole-group module gate (e.g. Tours when disabled).
            if (isset($group['module']) && $modules->isDisabled($group['module'])) {
                continue;
            }

            $items = [];

            foreach ($group['items'] as $item) {
                if (! empty($item['module']) && $modules->isDisabled($item['module'])) {
                    continue;
                }

                if ($user === null) {
                    continue;
                }

                if (! empty($item['permission']) && ! $user->can($item['permission'])) {
                    continue;
                }

                try {
                    $url = route($item['route'], absolute: false);
                } catch (\Throwable) {
                    continue;
                }

                $items[] = [
                    'id' => $item['id'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'url' => $url,
                    'group' => $group['label'],
                    'keywords' => $item['keywords'],
                    'order' => $item['order'],
                ];
            }

            if ($items !== []) {
                $result[] = [
                    'key' => $group['key'],
                    'label' => $group['label'],
                    'items' => $items,
                ];
            }
        }

        return $result;
    }

    /**
     * Flat item lookup for tests and the active-group logic.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function flatItems(): array
    {
        $flat = [];

        foreach (static::groups() as $group) {
            foreach ($group['items'] as $item) {
                $flat[] = $item + ['group' => $group['label'], 'groupKey' => $group['key']];
            }
        }

        return $flat;
    }

    /**
     * Resolve which group key owns a request path (longest-prefix match
     * on the item's named route path) so the sidebar auto-opens the
     * current parent section.
     */
    public static function activeGroupKey(string $currentPath): ?string
    {
        $path = '/'.ltrim(strtok($currentPath, '?'), '/');
        $bestKey = null;
        $bestLength = 0;

        foreach (static::groups() as $group) {
            foreach ($group['items'] as $item) {
                try {
                    $itemPath = route($item['route'], absolute: false);
                } catch (\Throwable) {
                    continue;
                }

                $itemPath = strtok($itemPath, '?');

                if ($path === $itemPath || str_starts_with($path, rtrim($itemPath, '/').'/')) {
                    if (strlen($itemPath) > $bestLength) {
                        $bestLength = strlen($itemPath);
                        $bestKey = $group['key'];
                    }
                }
            }
        }

        return $bestKey;
    }

    /**
     * Sidebar / palette navigation search over already-filtered items.
     *
     * @param  array<int, array{key:string,label:string,items:array<int,array<string,mixed>>}>  $filteredGroups
     * @return array<int, array<string, mixed>>
     */
    public static function searchFiltered(array $filteredGroups, string $query, int $limit = 8): array
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return [];
        }

        $matches = [];

        foreach ($filteredGroups as $group) {
            foreach ($group['items'] as $item) {
                $haystack = mb_strtolower($item['label'].' '.$item['group'].' '.implode(' ', $item['keywords'] ?? []));

                if (str_contains($haystack, $query)) {
                    $matches[] = [
                        'type' => 'navigation',
                        'label' => $item['label'],
                        'subtitle' => $item['group'].' → '.$item['label'],
                        'url' => $item['url'],
                        'icon' => $item['icon'] ?? 'bi-link',
                    ];
                }

                if (count($matches) >= $limit) {
                    break 2;
                }
            }
        }

        return $matches;
    }
}
