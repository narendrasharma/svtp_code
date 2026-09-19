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
    ['Dashboard', '/driver/taxi/dashboard', 'bi-speedometer2'],
    ['My Trips', '/driver/taxi/trips', 'bi-car-front'],
    ['Job Offers', '/driver/taxi/offers', 'bi-briefcase'],
    ['My Earnings', '/driver/taxi/earnings', 'bi-cash-coin'],
    ['Profile', '/driver/taxi/profile', 'bi-person-badge'],
    ['Notifications', '/notifications', 'bi-bell'],
].map(([label, path, icon]) => ({ label, path, icon }));

function isActive(path) {
    const currentPath = page.url.split('?')[0];
    return currentPath === path || currentPath.startsWith(`${path}/`) || (path === '/driver' && currentPath === '/driver');
}

const pageHeading = computed(() => {
    const current = navigation.find(item => isActive(item.path));
    return current ? current.label : 'Driver Portal';
});

watch(() => page.url, () => { isSidebarOpen.value = false; });
</script>

<template>
    <div>
        <ImpersonationBanner />
        <div class="driver-shell">
            <aside class="driver-sidebar" :class="{ 'is-open': isSidebarOpen }">
                <div class="driver-sidebar-heading">
                    <DashboardBrand panel-label="Driver Portal" />
                    <button type="button" class="driver-sidebar-close" aria-label="Close navigation" @click="isSidebarOpen = false"><i class="bi bi-x-lg"></i></button>
                </div>

                <nav class="driver-navigation" aria-label="Driver navigation">
                    <Link v-for="item in navigation" :key="item.path" :href="appUrl(item.path)" class="driver-nav-link" :class="{ 'is-active': isActive(item.path) }">
                        <i class="bi" :class="item.icon"></i><span>{{ item.label }}</span>
                    </Link>
                </nav>

                <div class="driver-sidebar-footer">
                    <Link :href="appUrl('/')" class="driver-nav-link driver-nav-link--muted"><i class="bi bi-box-arrow-up-right"></i><span>Back to Website</span></Link>
                </div>
            </aside>

            <button v-if="isSidebarOpen" type="button" class="driver-sidebar-backdrop" aria-label="Close navigation" @click="isSidebarOpen = false"></button>

            <div class="driver-main-wrapper">
                <header class="driver-topbar">
                    <div class="driver-topbar-left">
                        <button type="button" class="driver-topbar-hamburger" aria-label="Open navigation" @click="isSidebarOpen = true"><i class="bi bi-list"></i></button>
                        <span class="driver-topbar-title">{{ pageHeading }}</span>
                    </div>
                    <div class="driver-topbar-right">
                        <NotificationBell />
                        <DashboardUserMenu :profile-url="appUrl('/driver/taxi/profile')" :show-view-website="true" />
                    </div>
                </header>

                <main class="driver-main">
                    <AdminToasts />
                    <div class="driver-content"><slot /></div>
                </main>

                <nav class="driver-bottomnav" aria-label="Driver quick navigation">
                    <Link v-for="item in navigation.slice(0, 3)" :key="item.path" :href="appUrl(item.path)" class="driver-bottomnav-link" :class="{ 'is-active': isActive(item.path) }">
                        <i class="bi" :class="item.icon"></i><span>{{ item.label }}</span>
                    </Link>
                </nav>

                <DashboardFooter variant="vendor" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.driver-shell { min-height: 100vh; background: #090f1d; color: #e5e7eb; display: flex; }
.driver-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 1040; display: flex; width: 270px; flex-direction: column; overflow-y: auto; background: #101827; border-right: 1px solid #263247; box-shadow: 12px 0 35px rgba(0,0,0,.22); }
.driver-sidebar-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.25rem 1rem 1rem; border-bottom: 1px solid #1e293b; }
.driver-sidebar-close { display: none; padding: .35rem; background: transparent; border: 0; color: #cbd5e1; font-size: 1.2rem; }
.driver-navigation { display: flex; flex: 1; flex-direction: column; gap: .25rem; padding: 1rem .85rem 1rem; }
.driver-nav-link { display: flex; width: 100%; align-items: center; gap: .75rem; min-height: 48px; padding: .68rem .8rem; border: 0; border-radius: .65rem; background: transparent; color: #cbd5e1; font-size: 1rem; text-align: left; text-decoration: none; transition: background-color .18s ease,color .18s ease; }
.driver-nav-link i { width: 1.2rem; color: #94a3b8; text-align: center; }
.driver-nav-link:hover,.driver-nav-link.is-active { background: #1e293b; color: #fff; }
.driver-nav-link.is-active { box-shadow: inset 3px 0 #38bdf8; }
.driver-nav-link:hover i,.driver-nav-link.is-active i { color: #38bdf8; }
.driver-nav-link--muted { color: #94a3b8; font-size: 0.85rem; }
.driver-sidebar-footer { padding: 1rem .85rem; border-top: 1px solid #1e293b; }
.driver-main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; margin-left: 270px; min-height: 100vh; background: #090f1d; }
.driver-topbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; height: 56px; padding: 0 1.25rem; background: #101827; border-bottom: 1px solid #263247; position: sticky; top: 0; z-index: 1020; }
.driver-topbar-left { display: flex; align-items: center; gap: 0.75rem; }
.driver-topbar-hamburger { display: none; width: 44px; height: 44px; align-items: center; justify-content: center; border: 0; border-radius: 8px; background: #1e293b; color: #e5e7eb; font-size: 1.2rem; }
.driver-topbar-title { font-size: 1rem; font-weight: 600; color: #f8fafc; letter-spacing: 0.02em; }
.driver-main { flex: 1; }
.driver-content { width: 100%; max-width: 960px; padding: 1.25rem; }
.driver-bottomnav { display: none; }
.driver-content :deep(h1),.driver-content :deep(h2),.driver-content :deep(h3),.driver-content :deep(h4),.driver-content :deep(h5),.driver-content :deep(h6) { color: #f8fafc; }
.driver-content :deep(.card),.driver-content :deep(.border),.driver-content :deep(.bg-light) { background-color: #111c2d !important; border-color: #334155 !important; color: #e5e7eb; }
.driver-content :deep(.table) { --bs-table-bg: transparent; --bs-table-color: #e5e7eb; --bs-table-border-color: #334155; color: #e5e7eb; }
.driver-content :deep(.form-control),.driver-content :deep(.form-select) { border-color: #475569; background-color: #162235; color: #f8fafc; color-scheme: dark; font-size: 1rem; }
.driver-content :deep(.form-control::placeholder) { color: #94a3b8; }
.driver-content :deep(.form-control:focus),.driver-content :deep(.form-select:focus) { border-color: #38bdf8; background-color: #162235; color: #fff; box-shadow: 0 0 0 .2rem rgba(56,189,248,.18); }
.driver-content :deep(.btn) { min-height: 44px; }
@media (max-width: 991.98px) {
    .driver-sidebar { width: min(86vw,300px); transform: translateX(-105%); transition: transform .22s ease; }
    .driver-sidebar.is-open { transform: translateX(0); }
    .driver-sidebar-close { display: inline-flex; }
    .driver-sidebar-backdrop { position: fixed; inset: 0; z-index: 1035; display: block; padding: 0; border: 0; background: rgba(2,6,23,.7); }
    .driver-main-wrapper { margin-left: 0; padding-bottom: 72px; }
    .driver-topbar-hamburger { display: inline-flex; }
    .driver-content { padding: 1rem 1rem 1.5rem; }
    .driver-bottomnav { display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 1025; background: #101827; border-top: 1px solid #263247; padding: .35rem .5rem calc(.35rem + env(safe-area-inset-bottom)); }
    .driver-bottomnav-link { flex: 1; display: flex; flex-direction: column; align-items: center; gap: .15rem; padding: .5rem .25rem; border-radius: .5rem; color: #94a3b8; font-size: .72rem; text-decoration: none; }
    .driver-bottomnav-link i { font-size: 1.25rem; }
    .driver-bottomnav-link.is-active { color: #38bdf8; background: #1e293b; }
}
</style>
