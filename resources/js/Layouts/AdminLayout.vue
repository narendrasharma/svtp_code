<script setup>
import { appUrl } from '../appUrl';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const isSidebarOpen = ref(false);
const navigation = [
    ['Dashboard', '/admin/dashboard', 'bi-speedometer2'], ['Enquiries', '/admin/enquiries', 'bi-chat-left-text'],
    ['Packages', '/admin/packages', 'bi-map'], ['Tour Categories', '/admin/tour-categories', 'bi-grid'],
    ['Destinations', '/admin/destinations', 'bi-geo-alt'], ['Places / Attractions', '/admin/places', 'bi-pin-map'],
    ['Tags', '/admin/tags', 'bi-tags'], ['Banners', '/admin/banners', 'bi-images'],
    ['Promotional Popup', '/admin/promotional-popup', 'bi-window-stack'],
    ['Bookings', '/admin/bookings', 'bi-calendar-check'], ['Reviews', '/admin/reviews', 'bi-star'],
].map(([label, path, icon]) => ({ label, path, icon }));
const adminName = computed(() => page.props.auth?.user?.name ?? 'Administrator');

function isActive(path) {
    const currentPath = page.url.split('?')[0];

    return currentPath.endsWith(path) || currentPath.includes(`${path}/`);
}

watch(() => page.url, () => { isSidebarOpen.value = false; });
</script>

<template>
    <div class="admin-shell">
        <header class="admin-mobile-header">
            <button type="button" class="admin-icon-button" aria-label="Open admin navigation" @click="isSidebarOpen = true"><i class="bi bi-list"></i></button>
            <span class="fw-bold text-white">SVTP Admin</span>
            <Link :href="appUrl('/admin/profile')" class="admin-icon-button" aria-label="Admin profile"><i class="bi bi-person-circle"></i></Link>
        </header>

        <button v-if="isSidebarOpen" type="button" class="admin-sidebar-backdrop" aria-label="Close admin navigation" @click="isSidebarOpen = false"></button>

        <aside class="admin-sidebar" :class="{ 'is-open': isSidebarOpen }">
            <div class="admin-sidebar-heading">
                <div><span class="admin-eyebrow">Shree Vrindavan</span><h1>SVTP Admin</h1></div>
                <button type="button" class="admin-sidebar-close" aria-label="Close admin navigation" @click="isSidebarOpen = false"><i class="bi bi-x-lg"></i></button>
            </div>

            <nav class="admin-navigation" aria-label="Admin navigation">
                <Link v-for="item in navigation" :key="item.path" :href="appUrl(item.path)" class="admin-nav-link" :class="{ 'is-active': isActive(item.path) }">
                    <i class="bi" :class="item.icon"></i><span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="admin-account">
                <div class="admin-account-summary"><i class="bi bi-person-circle"></i><div><strong>{{ adminName }}</strong><span>Administrator</span></div></div>
                <Link :href="appUrl('/admin/profile')" class="admin-nav-link" :class="{ 'is-active': isActive('/admin/profile') }"><i class="bi bi-person-gear"></i><span>Profile</span></Link>
                <Link :href="appUrl('/admin/logout')" method="post" as="button" class="admin-nav-link admin-logout"><i class="bi bi-box-arrow-right"></i><span>Logout</span></Link>
            </div>
        </aside>

        <main class="admin-main"><div class="admin-content"><slot /></div></main>
    </div>
</template>

<style scoped>
.admin-shell { min-height: 100vh; background: #090f1d; color: #e5e7eb; }
.admin-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 1040; display: flex; width: 270px; flex-direction: column; overflow-y: auto; background: #101827; border-right: 1px solid #263247; box-shadow: 12px 0 35px rgba(0,0,0,.22); }
.admin-sidebar-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.5rem 1.25rem 1.1rem; }
.admin-sidebar-heading h1 { margin: .1rem 0 0; color: #fff; font-family: var(--font-body); font-size: 1.25rem; font-weight: 700; }
.admin-eyebrow { color: #fbbf24; font-size: .7rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
.admin-sidebar-close { display: none; padding: .35rem; background: transparent; border: 0; color: #cbd5e1; font-size: 1.2rem; }
.admin-navigation { display: flex; flex: 1; flex-direction: column; gap: .25rem; padding: .35rem .85rem 1rem; }
.admin-nav-link { display: flex; width: 100%; align-items: center; gap: .75rem; padding: .68rem .8rem; border: 0; border-radius: .65rem; background: transparent; color: #cbd5e1; font-size: .92rem; text-align: left; text-decoration: none; transition: background-color .18s ease,color .18s ease; }
.admin-nav-link i { width: 1.2rem; color: #94a3b8; text-align: center; }
.admin-nav-link:hover,.admin-nav-link.is-active { background: #26344c; color: #fff; }
.admin-nav-link.is-active { box-shadow: inset 3px 0 #f59e0b; }
.admin-nav-link:hover i,.admin-nav-link.is-active i { color: #fbbf24; }
.admin-account { display: flex; flex-direction: column; gap: .25rem; padding: 1rem .85rem 1.25rem; border-top: 1px solid #263247; }
.admin-account-summary { display: flex; align-items: center; gap: .7rem; padding: 0 .8rem .7rem; }
.admin-account-summary > i { color: #fbbf24; font-size: 1.75rem; }
.admin-account-summary div { min-width: 0; }
.admin-account-summary strong,.admin-account-summary span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.admin-account-summary strong { color: #f8fafc; font-size: .88rem; }.admin-account-summary span { color: #94a3b8; font-size: .72rem; }
.admin-logout { cursor: pointer; }.admin-main { min-height: 100vh; margin-left: 270px; background: #090f1d; }.admin-content { width: 100%; max-width: 1500px; padding: 2rem; }
.admin-mobile-header,.admin-sidebar-backdrop { display: none; }
.admin-content :deep(h1),.admin-content :deep(h2),.admin-content :deep(h3),.admin-content :deep(h4),.admin-content :deep(h5),.admin-content :deep(h6) { color: #f8fafc; }
.admin-content :deep(.card),.admin-content :deep(.border),.admin-content :deep(.bg-light) { background-color: #111c2d !important; border-color: #334155 !important; color: #e5e7eb; }
.admin-content :deep(.table) { --bs-table-bg: transparent; --bs-table-color: #e5e7eb; --bs-table-border-color: #334155; --bs-table-striped-color: #e5e7eb; --bs-table-hover-color: #fff; --bs-table-hover-bg: rgba(51,65,85,.45); color: #e5e7eb; }
.admin-content :deep(.form-control),.admin-content :deep(.form-select) { border-color: #475569; background-color: #162235; color: #f8fafc; color-scheme: dark; }
.admin-content :deep(.form-control::placeholder) { color: #94a3b8; }
.admin-content :deep(.form-control:focus),.admin-content :deep(.form-select:focus) { border-color: #f59e0b; background-color: #162235; color: #fff; box-shadow: 0 0 0 .2rem rgba(245,158,11,.18); }
.admin-content :deep(.form-check-input) { border-color: #64748b; background-color: #162235; }.admin-content :deep(.form-check-input:checked) { border-color: #f59e0b; background-color: #f59e0b; }
.admin-content :deep(.text-muted),.admin-content :deep(.form-text) { color: #aebbd0 !important; }.admin-content :deep(.dropdown-menu),.admin-content :deep(.list-group-item) { border-color: #475569; background: #162235; color: #e5e7eb; }.admin-content :deep(.page-link) { border-color: #334155; background: #162235; color: #e5e7eb; }.admin-content :deep(.page-item.active .page-link) { border-color: #f59e0b; background: #f59e0b; color: #111827; }
@media (max-width: 991.98px) {
    .admin-mobile-header { position: sticky; top: 0; z-index: 1030; display: flex; height: 60px; align-items: center; justify-content: space-between; padding: 0 1rem; background: #101827; border-bottom: 1px solid #263247; }
    .admin-icon-button { display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center; padding: 0; border: 0; border-radius: .55rem; background: #26344c; color: #f8fafc; font-size: 1.25rem; }
    .admin-sidebar { width: min(86vw,300px); transform: translateX(-105%); transition: transform .22s ease; }.admin-sidebar.is-open { transform: translateX(0); }.admin-sidebar-close { display: inline-flex; }
    .admin-sidebar-backdrop { position: fixed; inset: 0; z-index: 1035; display: block; padding: 0; border: 0; background: rgba(2,6,23,.7); }.admin-main { margin-left: 0; }.admin-content { padding: 1.25rem 1rem 2rem; }
}
@media (max-width: 575.98px) { .admin-content { overflow-x: hidden; }.admin-content :deep(.btn) { max-width: 100%; }.admin-content :deep(.table-responsive) { margin-inline: -.25rem; } }
</style>
