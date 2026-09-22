<?php

namespace App\Support;

/**
 * Central staff permission catalogue (Phase 11.5A RBAC).
 *
 * Granular, readable permission keys grouped for the Roles UI. Business
 * logic must check these permission keys — never hardcode staff role
 * names. Future module namespaces (taxi.*, hotels.*) fit naturally.
 */
class StaffPermissions
{
    public const SUPER_ADMIN_ROLE = 'super-admin';

    public const ADMINISTRATOR_ROLE = 'administrator';

    /**
     * @return array<string, array{label:string, permissions: array<string, string>}>
     *                                                                                group key => [label, permissions: key => human description]
     */
    public static function grouped(): array
    {
        return [
            'general' => [
                'label' => 'General',
                'permissions' => [
                    'dashboard.view' => 'View admin dashboard',
                    'reports.view' => 'View reports & analytics',
                    'analytics.view' => 'View dashboard analytics (alias of reports)',
                    'audit.view' => 'View activity / audit logs',
                    'system.health.view' => 'View system health & information',
                    'system.jobs.manage' => 'Retry / delete failed queue jobs',
                ],
            ],
            'users' => [
                'label' => 'Users',
                'permissions' => [
                    'users.view' => 'View users',
                    'users.create' => 'Create users',
                    'users.update' => 'Update users',
                    'users.impersonate' => 'Impersonate customers & vendors',
                    'staff.view' => 'View staff members',
                    'staff.create' => 'Create staff members',
                    'staff.update' => 'Update staff & assign roles',
                    'roles.manage' => 'Manage roles & permissions',
                ],
            ],
            'tours' => [
                'label' => 'Tours',
                'permissions' => [
                    'tours.view' => 'View tours & masters',
                    'tours.create' => 'Create tours',
                    'tours.update' => 'Update tours',
                    'tours.delete' => 'Delete tours',
                    'tours.approve' => 'Approve / moderate tours',
                ],
            ],
            'locations' => [
                'label' => 'Locations',
                'permissions' => [
                    'locations.countries.view' => 'View countries',
                    'locations.countries.manage' => 'Create & edit countries',
                    'locations.states.view' => 'View states & regions',
                    'locations.states.manage' => 'Create & edit states & regions',
                    'locations.cities.view' => 'View cities',
                    'locations.cities.manage' => 'Create & edit cities',
                    'locations.destinations.view' => 'View destinations',
                    'locations.destinations.manage' => 'Create & edit destinations',
                    'locations.places.view' => 'View places & attractions',
                    'locations.places.manage' => 'Create & edit places & attractions',
                ],
            ],
            'bookings' => [
                'label' => 'Bookings',
                'permissions' => [
                    'bookings.view' => 'View bookings & enquiries',
                    'bookings.create' => 'Create manual bookings',
                    'bookings.update' => 'Update booking status',
                    'bookings.cancel' => 'Review cancellation requests',
                    'bookings.reschedule' => 'Reschedule bookings',
                ],
            ],
            'vendors' => [
                'label' => 'Vendors',
                'permissions' => [
                    'vendors.view' => 'View vendors & plans',
                    'vendors.approve' => 'Approve / reject vendors & plans',
                    'vendors.kyc_review' => 'Review vendor KYC documents',
                ],
            ],
            'finance' => [
                'label' => 'Finance',
                'permissions' => [
                    'finance.view' => 'View finance (withdrawals, payouts)',
                    'finance.refunds' => 'Record booking refunds',
                    'finance.withdrawals' => 'Approve / pay withdrawals',
                    'finance.adjustments' => 'Post vendor ledger adjustments',
                    'payments.record' => 'Record booking payments',
                ],
            ],
            'crm' => [
                'label' => 'CRM',
                'permissions' => [
                    'leads.view' => 'View assigned leads',
                    'leads.view_all' => 'View all leads',
                    'leads.create' => 'Create leads',
                    'leads.update' => 'Update leads & follow-ups',
                    'leads.assign' => 'Assign leads to staff',
                    'leads.convert' => 'Convert leads to customers',
                    'quotations.view' => 'View quotations',
                    'quotations.create' => 'Create quotations',
                    'quotations.update' => 'Update quotations',
                    'quotations.send' => 'Send quotations',
                    'quotations.accept' => 'Accept / reject quotations',
                    'quotations.convert' => 'Convert quotations to bookings',
                ],
            ],
            'marketing' => [
                'label' => 'Marketing',
                'permissions' => [
                    'marketing.view' => 'View banners & popups',
                    'marketing.coupons' => 'Manage coupons',
                ],
            ],
            'support' => [
                'label' => 'Support',
                'permissions' => [
                    'support.view' => 'View assigned support tickets',
                    'support.view_all' => 'View all support tickets',
                    'support.reply' => 'Reply to support tickets',
                    'support.assign' => 'Assign support tickets',
                    'support.close' => 'Resolve / close tickets',
                    'support.manage_categories' => 'Manage ticket categories',
                ],
            ],
            'taxi' => [
                'label' => 'Taxi',
                'permissions' => [
                    'taxi.dashboard.view' => 'View taxi dashboard',
                    'taxi.bookings.view' => 'View taxi bookings',
                    'taxi.bookings.create' => 'Create taxi bookings',
                    'taxi.bookings.update' => 'Update taxi bookings',
                    'taxi.bookings.assign' => 'Assign drivers & vehicles',
                    'taxi.bookings.status' => 'Update ride status',
                    'taxi.bookings.cancel' => 'Cancel taxi bookings',
                    'taxi.pricing.view' => 'View taxi pricing',
                    'taxi.pricing.manage' => 'Manage taxi pricing',
                    'taxi.vehicle_types.view' => 'View vehicle types',
                    'taxi.vehicle_types.manage' => 'Manage vehicle types',
                    'taxi.vehicles.view' => 'View vehicles',
                    'taxi.vehicles.create' => 'Create vehicles',
                    'taxi.vehicles.update' => 'Update vehicles',
                    'taxi.drivers.view' => 'View drivers',
                    'taxi.drivers.create' => 'Create drivers',
                    'taxi.drivers.update' => 'Update drivers',
                    'taxi.drivers.documents' => 'Manage driver documents',
                    'taxi.cancellations.view' => 'View taxi cancellations and policies',
                    'taxi.cancellations.manage' => 'Manage taxi cancellations and policies',
                    'taxi.refunds.view' => 'View taxi refunds',
                    'taxi.refunds.manage' => 'Record taxi refunds',
                    'taxi.reschedule.manage' => 'Reschedule taxi bookings',
                    'taxi.reviews.view' => 'View taxi reviews & ratings',
                    'taxi.reviews.moderate' => 'Moderate taxi reviews',
                    'taxi.reviews.reply' => 'Reply to taxi reviews',
                    'taxi.driver_earnings.view' => 'View driver earnings & payouts',
                    'taxi.driver_earnings.manage' => 'Manage compensation plans & adjustments',
                    'taxi.driver_payouts.view' => 'View driver payouts',
                    'taxi.driver_payouts.manage' => 'Create & settle driver payouts',
                    'taxi.settings.manage' => 'Manage taxi settings',
                ],
            ],
            'hotels' => [
                'label' => 'Hotels',
                'permissions' => [
                    'hotel.operations.view' => 'View hotel operations',
                    'hotel.properties.view' => 'View hotel properties',
                    'hotel.properties.manage' => 'Create & edit hotel properties',
                    'hotel.properties.publish' => 'Publish & moderate hotel properties',
                    'hotel.property_types.manage' => 'Manage property types',
                    'hotel.amenities.manage' => 'Manage hotel amenities',
                    'hotel.room_types.view' => 'View hotel room types',
                    'hotel.room_types.manage' => 'Manage hotel room types',
                    'hotel.room_units.view' => 'View hotel room units',
                    'hotel.room_units.manage' => 'Manage hotel room units',
                    'hotel.bed_types.manage' => 'Manage bed types',
                    'hotel.custom_fields.view' => 'View hotel custom fields',
                    'hotel.custom_fields.manage' => 'Manage hotel custom fields',
                    'hotel.inventory.view' => 'View hotel inventory',
                    'hotel.inventory.manage' => 'Manage hotel inventory',
                    'hotel.rate_plans.view' => 'View hotel rate plans',
                    'hotel.rate_plans.manage' => 'Manage hotel rate plans',
                    'hotel.pricing.view' => 'View hotel pricing calendars',
                    'hotel.pricing.manage' => 'Manage hotel daily rates',
                    'hotel.charges.manage' => 'Manage hotel taxes & fees',
                    'hotel.settings.manage' => 'Manage hotel settings',
                    'hotel.bookings.view' => 'View hotel bookings',
                    'hotel.bookings.create' => 'Create hotel bookings',
                    'hotel.bookings.manage' => 'Manage hotel bookings',
                    'hotel.bookings.status' => 'Update hotel booking status',
                    'hotel.cancellations.view' => 'View hotel cancellations',
                    'hotel.cancellations.manage' => 'Manage hotel cancellations',
                    'hotel.refunds.view' => 'View hotel refunds',
                    'hotel.refunds.manage' => 'Manage hotel refunds',
                    'hotel.reschedules.manage' => 'Reschedule hotel bookings',
                    'hotel.reviews.view' => 'View hotel reviews & ratings',
                    'hotel.reviews.moderate' => 'Moderate hotel reviews & property responses',
                    'hotel.reviews.reply' => 'Add official property responses to hotel reviews',
                ],
            ],
            'communications' => [
                'label' => 'Communications',
                'permissions' => [
                    'communications.send' => 'Send messages & share documents',
                    'communications.view_logs' => 'View communication logs',
                    'communications.manage_templates' => 'Manage message templates',
                    'campaigns.view' => 'View campaigns',
                    'campaigns.create' => 'Create campaign drafts',
                    'campaigns.send' => 'Send campaigns',
                    'users.message' => 'Message users directly',
                ],
            ],
            'content' => [
                'label' => 'Content',
                'permissions' => [
                    'content.pages' => 'Manage pages, homepage & banners content',
                    'content.menus' => 'Manage menus',
                    'content.seo' => 'Manage SEO settings',
                ],
            ],
            'system' => [
                'label' => 'System',
                'permissions' => [
                    'settings.view' => 'View settings & number series',
                    'settings.update' => 'Update settings & number series',
                    'modules.manage' => 'Enable / disable platform modules',
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return collect(static::grouped())
            ->flatMap(fn (array $group): array => array_keys($group['permissions']))
            ->values()
            ->all();
    }

    public static function isKnown(string $permission): bool
    {
        return in_array($permission, static::all(), true);
    }

    /**
     * Default permission sets for seeded staff roles. Role names are
     * display/seed conveniences only — authorization always uses keys.
     *
     * @return array<string, array{description:string, permissions:array<int,string>}>
     */
    public static function defaultRoles(): array
    {
        $all = static::all();
        $without = fn (array $exclude): array => array_values(array_diff($all, $exclude));

        return [
            // Protected: cannot be deleted, always keeps every permission.
            'super-admin' => [
                'description' => 'Full platform access, including staff, roles and system settings. Protected role.',
                'permissions' => $all,
                'protected' => true,
            ],
            'administrator' => [
                'description' => 'Full operational access. Cannot manage roles or critical system settings.',
                // Bulk campaign sends and template management stay with
                // Super Admin — dangerous messaging is never auto-granted.
                'permissions' => $without(['roles.manage', 'modules.manage', 'campaigns.send', 'communications.manage_templates']),
            ],
            'operations-manager' => [
                'description' => 'Day-to-day tour, booking and vendor operations.',
                'permissions' => [
                    'dashboard.view', 'reports.view',
                    'users.view', 'users.impersonate', 'users.message',
                    'tours.view', 'tours.create', 'tours.update', 'tours.approve',
                    'locations.countries.view', 'locations.countries.manage',
                    'locations.states.view', 'locations.states.manage',
                    'locations.cities.view', 'locations.cities.manage',
                    'locations.destinations.view', 'locations.destinations.manage',
                    'locations.places.view', 'locations.places.manage',
                    'bookings.view', 'bookings.create', 'bookings.update', 'bookings.cancel', 'bookings.reschedule',
                    'vendors.view', 'vendors.approve', 'vendors.kyc_review',
                    'finance.view', 'payments.record',
                    'marketing.view',
                    'content.pages', 'content.menus',
                    'settings.view',
                    'leads.view', 'leads.view_all', 'leads.create', 'leads.update', 'leads.assign', 'leads.convert',
                    'quotations.view', 'quotations.create', 'quotations.update', 'quotations.send', 'quotations.accept', 'quotations.convert',
                    'support.view', 'support.view_all', 'support.reply', 'support.assign', 'support.close', 'support.manage_categories',
                    'communications.send', 'communications.view_logs',
                    'campaigns.view', 'campaigns.create',
                    'taxi.dashboard.view',
                    'taxi.bookings.view', 'taxi.bookings.create', 'taxi.bookings.update', 'taxi.bookings.assign', 'taxi.bookings.status', 'taxi.bookings.cancel',
                    'taxi.pricing.view', 'taxi.pricing.manage',
                    'taxi.vehicle_types.view', 'taxi.vehicle_types.manage',
                    'taxi.vehicles.view', 'taxi.vehicles.create', 'taxi.vehicles.update',
                    'taxi.drivers.view', 'taxi.drivers.create', 'taxi.drivers.update', 'taxi.drivers.documents',
                    'taxi.driver_earnings.view', 'taxi.driver_earnings.manage',
                    'taxi.driver_payouts.view', 'taxi.driver_payouts.manage',
                    'taxi.reviews.view', 'taxi.reviews.moderate', 'taxi.reviews.reply',
                    'hotel.properties.view', 'hotel.properties.manage', 'hotel.properties.publish',
                    'hotel.property_types.manage', 'hotel.amenities.manage',
                    'hotel.room_types.view', 'hotel.room_types.manage',
                    'hotel.room_units.view', 'hotel.room_units.manage',
                    'hotel.bed_types.manage',
                    'hotel.custom_fields.view', 'hotel.custom_fields.manage',
                    'hotel.inventory.view', 'hotel.inventory.manage',
                    'hotel.rate_plans.view', 'hotel.rate_plans.manage',
                    'hotel.pricing.view', 'hotel.pricing.manage',
                    'hotel.charges.manage',
                    'hotel.reviews.view', 'hotel.reviews.moderate', 'hotel.reviews.reply',
                ],
            ],
            'booking-executive' => [
                'description' => 'Creates and manages bookings.',
                'permissions' => [
                    'dashboard.view',
                    'users.view',
                    'tours.view',
                    'bookings.view', 'bookings.create', 'bookings.update', 'bookings.cancel', 'bookings.reschedule',
                    'vendors.view',
                    'payments.record',
                    'leads.view', 'leads.create', 'leads.update', 'leads.convert',
                    'quotations.view', 'quotations.create', 'quotations.update', 'quotations.convert',
                    'taxi.dashboard.view',
                    'taxi.bookings.view', 'taxi.bookings.create', 'taxi.bookings.update', 'taxi.bookings.status',
                    'taxi.pricing.view',
                ],
            ],
            'support-agent' => [
                'description' => 'Customer support: assigned tickets, read-only operations plus impersonation for troubleshooting.',
                'permissions' => [
                    'dashboard.view',
                    'users.view', 'users.impersonate',
                    'tours.view',
                    'bookings.view',
                    'vendors.view',
                    'support.view', 'support.reply',
                ],
            ],
            'content-manager' => [
                'description' => 'Tours catalogue and content only. No finance, vendor or user management.',
                'permissions' => [
                    'dashboard.view',
                    'tours.view', 'tours.create', 'tours.update',
                    'marketing.view', 'marketing.coupons',
                    'content.pages', 'content.menus', 'content.seo',
                    'settings.view',
                ],
            ],
            'finance-manager' => [
                'description' => 'Finance, refunds, withdrawals and payouts. No content editing.',
                'permissions' => [
                    'dashboard.view', 'reports.view',
                    'users.view',
                    'bookings.view',
                    'vendors.view',
                    'finance.view', 'finance.refunds', 'finance.withdrawals', 'finance.adjustments',
                    'taxi.driver_earnings.view', 'taxi.driver_payouts.view', 'taxi.driver_payouts.manage',
                    'payments.record',
                    'settings.view',
                ],
            ],
        ];
    }
}
