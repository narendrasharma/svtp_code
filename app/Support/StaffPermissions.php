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
                    'taxi.driver_earnings.view' => 'View driver earnings & payouts',
                    'taxi.driver_earnings.manage' => 'Manage compensation plans & adjustments',
                    'taxi.driver_payouts.view' => 'View driver payouts',
                    'taxi.driver_payouts.manage' => 'Create & settle driver payouts',
                    'taxi.settings.manage' => 'Manage taxi settings',
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
