<script setup>
import { appUrl } from '../appUrl';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminToasts from '../Components/AdminToasts.vue';
import ImpersonationBanner from '../Components/ImpersonationBanner.vue';
import NotificationBell from '../Components/NotificationBell.vue';
import DashboardBrand from '../Components/Dashboard/DashboardBrand.vue';
import DashboardUserMenu from '../Components/Dashboard/DashboardUserMenu.vue';
import DashboardFooter from '../Components/Dashboard/DashboardFooter.vue';

const page = usePage();
const isSidebarOpen = ref(false);
const navigation = [
    ['Dashboard', '/vendor', 'bi-speedometer2'],
    ['My Tours', '/vendor/tours', 'bi-map'],
    ['Customer Bookings', '/vendor/bookings', 'bi-receipt'],
    ['Taxi Operations', '/vendor/taxi/dashboard', 'bi-taxi-front'],
    ['Taxi Dispatch', '/vendor/taxi/dispatch', 'bi-kanban'],
    ['Taxi Tracking', '/vendor/taxi/tracking', 'bi-geo-alt'],
    ['Taxi Pricing', '/vendor/taxi/pricing', 'bi-currency-exchange'],
    ['Cancellation Policies', '/vendor/taxi/cancellation-policies', 'bi-calendar-x'],
    ['Driver Earnings', '/vendor/taxi/earnings', 'bi-cash-coin'],
    ['Driver Payouts', '/vendor/taxi/payouts', 'bi-bank'],
    ['Coupons', '/vendor/coupons', 'bi-ticket-perforated'],
    ['Earnings & Payouts', '/vendor/finance', 'bi-cash-stack'],
    ['My Application', '/vendor/application', 'bi-file-earmark-text'],
    ['Support', '/vendor/support', 'bi-life-preserver'],
    ['Profile', '/vendor/profile', 'bi-person-gear'],
    ['My Bookings', '/account/bookings', 'bi-calendar-check'],
    ['Browse Tours', '/packages', 'bi-compass'],
].map(([label, path, icon]) => ({ label, path, icon }));

function isActive(path) {
    const currentPath = page.url.split('?')[0];
    return currentPath === path || currentPath.startsWith(`${path}/`) || (path === '/vendor' && currentPath === '/vendor');
}

watch(() => page.url, () => { isSidebarOpen.value = false; });
</script>

<template>
    <div>
        <ImpersonationBanner />
        <div class="admin-shell">
            <aside class="admin-sidebar" :class="{ 'is-open': isSidebarOpen }">
                <div class="admin-sidebar-heading">
                    <DashboardBrand panel-label="Vendor Portal" />
                    <button type="button" class="admin-sidebar-close" aria-label="Close navigation" @click="isSidebarOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>

                <nav class="admin-navigation" aria-label="Vendor navigation">
                    <Link v-for="item in navigation" :key="item.path" :href="appUrl(item.path)" class="admin-nav-link" :class="{ 'is-active': isActive(item.path) }">
                        <i class="bi" :class="item.icon"></i><span>{{ item.label }}</span>
                    </Link>
                </nav>

                <div class="admin-sidebar-footer">
                    <Link :href="appUrl('/')" class="admin-nav-link admin-nav-link--muted"><i class="bi bi-box-arrow-up-right"></i><span>Back to Website</span></Link>
                </div>
            </aside>

            <button v-if="isSidebarOpen" type="button" class="admin-sidebar-backdrop" aria-label="Close navigation" @click="isSidebarOpen = false"></button>

            <div class="admin-main-wrapper">
                <header class="admin-topbar">
                    <div class="admin-topbar-left">
                        <button type="button" class="admin-topbar-hamburger" aria-label="Open navigation" @click="isSidebarOpen = true"><i class="bi bi-list"></i></button>
                        <span class="admin-topbar-title d-none d-md-inline">Vendor Portal</span>
                    </div>
                    <div class="admin-topbar-right">
                        <NotificationBell />
                        <DashboardUserMenu :profile-url="appUrl('/vendor/profile')" :show-view-website="true" />
                    </div>
                </header>

                <main class="admin-main">
                    <AdminToasts />
                    <div class="admin-content"><slot /></div>
                </main>

                <DashboardFooter variant="vendor" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-shell { min-height: 100vh; background: #090f1d; color: #e5e7eb; display: flex; }
.admin-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 1040; display: flex; width: 270px; flex-direction: column; overflow-y: auto; background: #101827; border-right: 1px solid #263247; box-shadow: 12px 0 35px rgba(0,0,0,.22); }
.admin-sidebar-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.25rem 1rem 1rem; border-bottom: 1px solid #1e293b; }
.admin-sidebar-close { display: none; padding: .35rem; background: transparent; border: 0; color: #cbd5e1; font-size: 1.2rem; }
.admin-navigation { display: flex; flex: 1; flex-direction: column; gap: .25rem; padding: 1rem .85rem 1rem; }
.admin-nav-link { display: flex; width: 100%; align-items: center; gap: .75rem; padding: .68rem .8rem; border: 0; border-radius: .65rem; background: transparent; color: #cbd5e1; font-size: .92rem; text-align: left; text-decoration: none; transition: background-color .18s ease,color .18s ease; }
.admin-nav-link i { width: 1.2rem; color: #94a3b8; text-align: center; }
.admin-nav-link:hover,.admin-nav-link.is-active { background: #1e293b; color: #fff; }
.admin-nav-link.is-active { box-shadow: inset 3px 0 #38bdf8; }
.admin-nav-link:hover i,.admin-nav-link.is-active i { color: #38bdf8; }
.admin-nav-link--muted { color: #94a3b8; font-size: 0.85rem; }
.admin-sidebar-footer { padding: 1rem .85rem; border-top: 1px solid #1e293b; }
.admin-main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; margin-left: 270px; min-height: 100vh; background: #090f1d; }
.admin-topbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; height: 56px; padding: 0 1.25rem; background: #101827; border-bottom: 1px solid #263247; position: sticky; top: 0; z-index: 1020; }
.admin-topbar-left { display: flex; align-items: center; gap: 0.75rem; }
.admin-topbar-hamburger { display: none; width: 36px; height: 36px; align-items: center; justify-content: center; border: 0; border-radius: 8px; background: #1e293b; color: #e5e7eb; font-size: 1.2rem; }
.admin-topbar-title { font-size: 0.85rem; font-weight: 600; color: #cbd5e1; letter-spacing: 0.02em; }
.admin-main { flex: 1; }
.admin-content { width: 100%; max-width: 1500px; padding: 1.75rem; }
.admin-sidebar-backdrop { display: none; }
.admin-content :deep(h1),.admin-content :deep(h2),.admin-content :deep(h3),.admin-content :deep(h4),.admin-content :deep(h5),.admin-content :deep(h6) { color: #f8fafc; }
.admin-content :deep(.card),.admin-content :deep(.border),.admin-content :deep(.bg-light) { background-color: #111c2d !important; border-color: #334155 !important; color: #e5e7eb; }
.admin-content :deep(.table) { --bs-table-bg: transparent; --bs-table-color: #e5e7eb; --bs-table-border-color: #334155; color: #e5e7eb; }
.admin-content :deep(.form-control),.admin-content :deep(.form-select) { border-color: #475569; background-color: #162235; color: #f8fafc; color-scheme: dark; }
.admin-content :deep(.form-control::placeholder) { color: #94a3b8; }
.admin-content :deep(.form-control:focus),.admin-content :deep(.form-select:focus) { border-color: #38bdf8; background-color: #162235; color: #fff; box-shadow: 0 0 0 .2rem rgba(56,189,248,.18); }
@media (max-width: 991.98px) {
    .admin-sidebar { width: min(86vw,300px); transform: translateX(-105%); transition: transform .22s ease; }
    .admin-sidebar.is-open { transform: translateX(0); }
    .admin-sidebar-close { display: inline-flex; }
    .admin-sidebar-backdrop { position: fixed; inset: 0; z-index: 1035; display: block; padding: 0; border: 0; background: rgba(2,6,23,.7); }
    .admin-main-wrapper { margin-left: 0; }
    .admin-topbar-hamburger { display: inline-flex; }
    .admin-content { padding: 1.25rem 1rem 1.5rem; }
}
</style>
