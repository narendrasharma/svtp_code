<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side staff authorization for the admin area (Phase 11.5A).
 *
 * Navigation hiding is NOT security: this middleware enforces one central
 * route-name → permission map for every admin route. Staff checks use
 * Gates (User::hasStaffPermission), so legacy full admins (role=admin
 * with no staff roles assigned) keep working while role-assigned staff
 * are strictly limited to their granted permissions.
 *
 * Unmapped admin routes fall back to dashboard.view (any staff member).
 */
class EnsureStaffPermission
{
    /**
     * Exact route-name → required permission. Mutation-heavy actions list
     * their stronger permission explicitly.
     *
     * @return array<string, string>
     */
    public static function routePermissions(): array
    {
        return [
            'admin.dashboard' => 'dashboard.view',
            'admin.profile.edit' => 'dashboard.view',
            'admin.profile.update' => 'dashboard.view',
            'admin.editor.upload-image' => 'content.pages',

            // Tours module.
            'admin.packages.index' => 'tours.view',
            'admin.packages.create' => 'tours.create',
            'admin.packages.store' => 'tours.create',
            'admin.packages.show' => 'tours.view',
            'admin.packages.edit' => 'tours.update',
            'admin.packages.update' => 'tours.update',
            'admin.packages.destroy' => 'tours.delete',
            'admin.packages.ai-draft' => 'tours.create',
            'admin.packages.approve' => 'tours.approve',
            'admin.packages.requestChanges' => 'tours.approve',
            'admin.packages.reject' => 'tours.approve',
            'admin.packages.addons.index' => 'tours.view',
            'admin.packages.addons.store' => 'tours.update',
            'admin.packages.addons.update' => 'tours.update',
            'admin.packages.addons.toggle' => 'tours.update',
            'admin.packages.addons.destroy' => 'tours.update',
            'admin.packages.availability.show' => 'tours.view',
            'admin.packages.availability.update' => 'tours.update',
            'admin.packages.blackouts.store' => 'tours.update',
            'admin.packages.blackouts.destroy' => 'tours.update',
            'admin.destinations.index' => 'tours.view',
            'admin.destinations.create' => 'tours.create',
            'admin.destinations.store' => 'tours.create',
            'admin.destinations.edit' => 'tours.update',
            'admin.destinations.update' => 'tours.update',
            'admin.destinations.destroy' => 'tours.delete',
            'admin.places.index' => 'tours.view',
            'admin.places.create' => 'tours.create',
            'admin.places.store' => 'tours.create',
            'admin.places.edit' => 'tours.update',
            'admin.places.update' => 'tours.update',
            'admin.places.destroy' => 'tours.delete',
            'admin.tags.index' => 'tours.view',
            'admin.tags.create' => 'tours.create',
            'admin.tags.store' => 'tours.create',
            'admin.tags.edit' => 'tours.update',
            'admin.tags.update' => 'tours.update',
            'admin.tags.destroy' => 'tours.delete',
            'admin.tour-categories.index' => 'tours.view',
            'admin.tour-categories.create' => 'tours.create',
            'admin.tour-categories.store' => 'tours.create',
            'admin.tour-categories.edit' => 'tours.update',
            'admin.tour-categories.update' => 'tours.update',
            'admin.tour-categories.destroy' => 'tours.delete',

            // Bookings + enquiries.
            'admin.bookings.index' => 'bookings.view',
            'admin.bookings.create' => 'bookings.create',
            'admin.bookings.desk' => 'bookings.create',
            'admin.bookings.store' => 'bookings.create',
            'admin.bookings.show' => 'bookings.view',
            'admin.bookings.status' => 'bookings.update',
            'admin.bookings.cancellation.approve' => 'bookings.cancel',
            'admin.bookings.cancellation.reject' => 'bookings.cancel',
            'admin.bookings.refunds.store' => 'finance.refunds',
            'admin.bookings.payments.store' => 'payments.record',
            'admin.bookings.payments.due-date' => 'payments.record',
            'admin.bookings.payments.remind' => 'payments.record',
            'admin.bookings.payments.receipt' => 'bookings.view',
            'admin.bookings.reschedule.store' => 'bookings.reschedule',
            'admin.bookings.notes.store' => 'bookings.update',
            'admin.enquiries.index' => 'bookings.view',
            'admin.enquiries.destroy' => 'bookings.update',

            // Taxi module (12A.1).
            'admin.taxi.dashboard' => 'taxi.dashboard.view',
            'admin.taxi.bookings.index' => 'taxi.bookings.view',
            'admin.taxi.bookings.create' => 'taxi.bookings.create',
            'admin.taxi.bookings.store' => 'taxi.bookings.create',
            'admin.taxi.bookings.convert' => 'taxi.bookings.create',
            'admin.taxi.bookings.show' => 'taxi.bookings.view',
            'admin.taxi.bookings.assign' => 'taxi.bookings.assign',
            'admin.taxi.bookings.unassign' => 'taxi.bookings.assign',
            'admin.taxi.bookings.status' => 'taxi.bookings.status',
            'admin.taxi.bookings.cancel' => 'taxi.bookings.cancel',
            'admin.taxi.bookings.payments.store' => 'taxi.bookings.update',
            'admin.taxi.vehicle-types.index' => 'taxi.vehicle_types.view',
            'admin.taxi.vehicle-types.store' => 'taxi.vehicle_types.manage',
            'admin.taxi.vehicle-types.update' => 'taxi.vehicle_types.manage',
            'admin.taxi.vehicle-types.destroy' => 'taxi.vehicle_types.manage',
            'admin.taxi.vehicles.index' => 'taxi.vehicles.view',
            'admin.taxi.vehicles.create' => 'taxi.vehicles.create',
            'admin.taxi.vehicles.store' => 'taxi.vehicles.create',
            'admin.taxi.vehicles.show' => 'taxi.vehicles.view',
            'admin.taxi.vehicles.edit' => 'taxi.vehicles.update',
            'admin.taxi.vehicles.update' => 'taxi.vehicles.update',
            'admin.taxi.vehicles.documents.store' => 'taxi.vehicles.update',
            'admin.taxi.vehicles.documents.verify' => 'taxi.vehicles.update',
            'admin.taxi.vehicles.unavailable.store' => 'taxi.vehicles.update',
            'admin.taxi.vehicles.unavailable.destroy' => 'taxi.vehicles.update',
            'admin.taxi.drivers.index' => 'taxi.drivers.view',
            'admin.taxi.drivers.create' => 'taxi.drivers.create',
            'admin.taxi.drivers.store' => 'taxi.drivers.create',
            'admin.taxi.drivers.show' => 'taxi.drivers.view',
            'admin.taxi.drivers.edit' => 'taxi.drivers.update',
            'admin.taxi.drivers.update' => 'taxi.drivers.update',
            'admin.taxi.drivers.documents.store' => 'taxi.drivers.documents',
            'admin.taxi.drivers.documents.verify' => 'taxi.drivers.documents',
            'admin.taxi.drivers.availability.store' => 'taxi.drivers.update',
            'admin.taxi.drivers.availability.destroy' => 'taxi.drivers.update',
            'admin.taxi.settings.index' => 'taxi.settings.manage',
            'admin.taxi.settings.update' => 'taxi.settings.manage',

            // CRM + quotations.
            'admin.crm.index' => 'leads.view',
            'admin.lead-sources.index' => 'leads.update',
            'admin.lead-sources.store' => 'leads.update',
            'admin.lead-sources.update' => 'leads.update',
            'admin.lead-sources.destroy' => 'leads.update',
            'admin.leads.index' => 'leads.view',
            'admin.leads.create' => 'leads.create',
            'admin.leads.store' => 'leads.create',
            'admin.leads.show' => 'leads.view',
            'admin.leads.edit' => 'leads.update',
            'admin.leads.update' => 'leads.update',
            'admin.leads.assign' => 'leads.assign',
            'admin.leads.status' => 'leads.update',
            'admin.leads.notes.store' => 'leads.update',
            'admin.leads.convert' => 'leads.convert',
            'admin.leads.follow-ups.store' => 'leads.update',
            'admin.follow-ups.index' => 'leads.view',
            'admin.follow-ups.complete' => 'leads.update',
            'admin.follow-ups.cancel' => 'leads.update',
            'admin.quotations.index' => 'quotations.view',
            'admin.quotations.create' => 'quotations.create',
            'admin.quotations.store' => 'quotations.create',
            'admin.quotations.show' => 'quotations.view',
            'admin.quotations.edit' => 'quotations.update',
            'admin.quotations.update' => 'quotations.update',
            'admin.quotations.revisions.store' => 'quotations.update',
            'admin.quotations.send' => 'quotations.send',
            'admin.quotations.accept' => 'quotations.accept',
            'admin.quotations.reject' => 'quotations.accept',
            'admin.quotations.expire' => 'quotations.update',
            'admin.quotations.convert.form' => 'quotations.convert',
            'admin.quotations.convert' => 'quotations.convert',
            'admin.customers.store' => 'users.create',
            'admin.customers.search' => 'leads.view',

            // Marketplace.
            'admin.vendor-applications.index' => 'vendors.view',
            'admin.vendor-applications.show' => 'vendors.view',
            'admin.vendor-applications.approve' => 'vendors.approve',
            'admin.vendor-applications.reject' => 'vendors.approve',
            'admin.vendor-applications.resubmission' => 'vendors.approve',
            'admin.vendor-applications.verify-kyc' => 'vendors.kyc_review',
            'admin.vendor-documents.download' => 'vendors.view',
            'admin.vendor-documents.verify' => 'vendors.kyc_review',
            'admin.vendor-documents.reject' => 'vendors.kyc_review',
            'admin.reviews.index' => 'tours.view',
            'admin.reviews.show' => 'tours.view',
            'admin.reviews.approve' => 'tours.update',
            'admin.reviews.reject' => 'tours.update',
            'admin.reviews.destroy' => 'tours.update',
            'admin.vendor-plans.index' => 'vendors.view',
            'admin.vendor-plans.create' => 'vendors.approve',
            'admin.vendor-plans.store' => 'vendors.approve',
            'admin.vendor-plans.edit' => 'vendors.approve',
            'admin.vendor-plans.update' => 'vendors.approve',
            'admin.vendor-plans.toggle' => 'vendors.approve',
            'admin.vendor-plans.destroy' => 'vendors.approve',
            'admin.vendor-profiles.plan.assign' => 'vendors.approve',

            // Finance.
            'admin.withdrawals.index' => 'finance.view',
            'admin.withdrawals.show' => 'finance.view',
            'admin.withdrawals.approve' => 'finance.withdrawals',
            'admin.withdrawals.reject' => 'finance.withdrawals',
            'admin.withdrawals.mark-paid' => 'finance.withdrawals',
            'admin.payout-accounts.index' => 'finance.view',
            'admin.payout-accounts.show' => 'finance.view',
            'admin.payout-accounts.verify' => 'finance.withdrawals',
            'admin.payout-accounts.reject' => 'finance.withdrawals',
            'admin.vendor-finances.show' => 'finance.view',
            'admin.vendor-finances.adjustments.store' => 'finance.adjustments',

            // Marketing.
            'admin.coupons.index' => 'marketing.coupons',
            'admin.coupons.create' => 'marketing.coupons',
            'admin.coupons.store' => 'marketing.coupons',
            'admin.coupons.edit' => 'marketing.coupons',
            'admin.coupons.update' => 'marketing.coupons',
            'admin.coupons.toggle' => 'marketing.coupons',
            'admin.coupons.destroy' => 'marketing.coupons',
            'admin.banners.index' => 'marketing.view',
            'admin.banners.store' => 'marketing.view',
            'admin.promotional-popup.index' => 'marketing.view',
            'admin.promotional-popup.store' => 'marketing.view',

            // Content.
            'admin.pages.index' => 'content.pages',
            'admin.pages.create' => 'content.pages',
            'admin.pages.store' => 'content.pages',
            'admin.pages.edit' => 'content.pages',
            'admin.pages.update' => 'content.pages',
            'admin.pages.destroy' => 'content.pages',
            'admin.menus.index' => 'content.menus',
            'admin.menus.create' => 'content.menus',
            'admin.menus.store' => 'content.menus',
            'admin.menus.edit' => 'content.menus',
            'admin.menus.update' => 'content.menus',
            'admin.menus.destroy' => 'content.menus',
            'admin.menus.items.index' => 'content.menus',
            'admin.menus.items.create' => 'content.menus',
            'admin.menus.items.reorder' => 'content.menus',
            'admin.menus.items.pages' => 'content.menus',
            'admin.menus.items.store' => 'content.menus',
            'admin.menus.items.edit' => 'content.menus',
            'admin.menus.items.update' => 'content.menus',
            'admin.menus.items.destroy' => 'content.menus',
            'admin.homepage-sections.index' => 'content.pages',
            'admin.homepage-sections.reorder' => 'content.pages',
            'admin.homepage-sections.update' => 'content.pages',

            // Users / staff.
            'admin.users.index' => 'users.view',
            'admin.users.show' => 'users.view',
            'admin.users.impersonate' => 'users.impersonate',
            'admin.staff.index' => 'staff.view',
            'admin.staff.create' => 'staff.create',
            'admin.staff.store' => 'staff.create',
            'admin.staff.show' => 'staff.view',
            'admin.staff.edit' => 'staff.update',
            'admin.staff.update' => 'staff.update',
            'admin.roles.index' => 'roles.manage',
            'admin.roles.create' => 'roles.manage',
            'admin.roles.store' => 'roles.manage',
            'admin.roles.edit' => 'roles.manage',
            'admin.roles.update' => 'roles.manage',
            'admin.roles.destroy' => 'roles.manage',

            // System.
            'admin.settings.index' => 'settings.view',
            'admin.settings.basic.update' => 'settings.update',
            'admin.settings.logo.update' => 'settings.update',
            'admin.settings.contact.update' => 'settings.update',
            'admin.settings.social.update' => 'settings.update',
            'admin.settings.seo.update' => 'settings.update',
            'admin.settings.marketplace.update' => 'settings.update',
            'admin.settings.operations.update' => 'settings.update',
            'admin.modules.index' => 'modules.manage',
            'admin.modules.update' => 'modules.manage',
            'admin.number-series.index' => 'settings.view',
            'admin.number-series.update' => 'settings.update',

            // Phase 11.5D platform operations.
            'admin.activity-logs.index' => 'audit.view',
            'admin.system.index' => 'system.health.view',
            'admin.system.failed-jobs.index' => 'system.health.view',
            'admin.system.failed-jobs.retry' => 'system.jobs.manage',
            'admin.system.failed-jobs.destroy' => 'system.jobs.manage',
            'admin.reports.index' => 'reports.view',
            'admin.reports.export' => 'reports.view',
            'admin.search' => 'dashboard.view',
            'admin.select-options' => 'dashboard.view',

            // Support desk.
            'admin.support.index' => 'support.view',
            'admin.support.show' => 'support.view',
            'admin.support.replies.store' => 'support.reply',
            'admin.support.notes.store' => 'support.reply',
            'admin.support.assign' => 'support.assign',
            'admin.support.status' => 'support.close',
            'admin.support.attachments.download' => 'support.view',
            'admin.support-categories.index' => 'support.manage_categories',
            'admin.support-categories.store' => 'support.manage_categories',
            'admin.support-categories.update' => 'support.manage_categories',
            'admin.support-categories.destroy' => 'support.manage_categories',

            // Communications.
            'admin.messages.create' => 'users.message',
            'admin.messages.store' => 'users.message',
            'admin.templates.index' => 'communications.manage_templates',
            'admin.templates.store' => 'communications.manage_templates',
            'admin.templates.update' => 'communications.manage_templates',
            'admin.communication-logs.index' => 'communications.view_logs',
            'admin.campaigns.index' => 'campaigns.view',
            'admin.campaigns.create' => 'campaigns.create',
            'admin.campaigns.store' => 'campaigns.create',
            'admin.campaigns.show' => 'campaigns.view',
            'admin.campaigns.edit' => 'campaigns.create',
            'admin.campaigns.update' => 'campaigns.create',
            'admin.campaigns.send' => 'campaigns.send',
            'admin.campaigns.schedule' => 'campaigns.send',
            'admin.campaigns.cancel' => 'campaigns.create',
            'admin.users.invite' => 'users.create',
            'admin.quotations.share' => 'communications.send',
            'admin.bookings.share-invoice' => 'communications.send',
            'admin.bookings.share-receipt' => 'communications.send',
        ];
    }

    public static function permissionForRoute(?string $routeName): string
    {
        if ($routeName === null) {
            return 'dashboard.view';
        }

        return static::routePermissions()[$routeName] ?? 'dashboard.view';
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Non-admin account types never reach here (admin middleware runs
        // first), but stay defensive.
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Admin access only.');
        }

        $permission = static::permissionForRoute($request->route()?->getName());

        if (! $user->can($permission)) {
            abort(403, 'You do not have permission to access this section.');
        }

        return $next($request);
    }
}
