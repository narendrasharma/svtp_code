<script setup>
import { appUrl } from '../appUrl';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import AdminToasts from '../Components/AdminToasts.vue';
import ImpersonationBanner from '../Components/ImpersonationBanner.vue';
import NotificationBell from '../Components/NotificationBell.vue';
import DashboardBrand from '../Components/Dashboard/DashboardBrand.vue';
import DashboardUserMenu from '../Components/Dashboard/DashboardUserMenu.vue';
import DashboardFooter from '../Components/Dashboard/DashboardFooter.vue';
import AdminSidebar from '../Components/Admin/AdminSidebar.vue';
import AdminCommandPalette from '../Components/Admin/AdminCommandPalette.vue';

const page = usePage();
const isSidebarOpen = ref(false);
const isPaletteOpen = ref(false);
const isQuickAddOpen = ref(false);

const navigation = computed(() => page.props.adminNavigation ?? []);
const quickActions = computed(() => page.props.quickActions ?? []);

function openPalette() {
    isPaletteOpen.value = true;
}

function onGlobalKeydown(event) {
    const isMac = navigator.platform?.toUpperCase().includes('MAC');
    if ((isMac ? event.metaKey : event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        isPaletteOpen.value = !isPaletteOpen.value;
    }
}

onMounted(() => document.addEventListener('keydown', onGlobalKeydown));
onUnmounted(() => document.removeEventListener('keydown', onGlobalKeydown));

watch(() => page.url, () => {
    isSidebarOpen.value = false;
    isQuickAddOpen.value = false;
});
</script>

<template>
    <ImpersonationBanner />
    <div class="admin-shell">
        <aside class="admin-sidebar" :class="{ 'is-open': isSidebarOpen }">
            <div class="admin-sidebar-heading">
                <DashboardBrand panel-label="Admin Panel" />
                <button type="button" class="admin-sidebar-close" aria-label="Close navigation" @click="isSidebarOpen = false"><i class="bi bi-x-lg"></i></button>
            </div>

            <AdminSidebar :groups="navigation" />

            <div class="admin-sidebar-footer">
                <Link :href="appUrl('/')" class="admin-nav-link admin-nav-link--muted"><i class="bi bi-box-arrow-up-right"></i><span>Back to Website</span></Link>
            </div>
        </aside>

        <button v-if="isSidebarOpen" type="button" class="admin-sidebar-backdrop" aria-label="Close navigation" @click="isSidebarOpen = false"></button>

        <div class="admin-main-wrapper">
            <header class="admin-topbar">
                <div class="admin-topbar-left">
                    <button type="button" class="admin-topbar-hamburger" aria-label="Open navigation" @click="isSidebarOpen = true"><i class="bi bi-list"></i></button>
                    <button type="button" class="admin-global-search" aria-label="Search anything (Ctrl+K)" @click="openPalette">
                        <i class="bi bi-search"></i>
                        <span class="d-none d-md-inline">Search anything...</span>
                        <kbd class="d-none d-md-inline">Ctrl K</kbd>
                    </button>
                </div>
                <div class="admin-topbar-right">
                    <div v-if="quickActions.length" class="admin-quick-add">
                        <button type="button" class="admin-quick-add-toggle" aria-label="Quick actions" aria-haspopup="menu" :aria-expanded="isQuickAddOpen" @click="isQuickAddOpen = !isQuickAddOpen">
                            <i class="bi bi-plus-lg"></i><span class="d-none d-md-inline">New</span>
                        </button>
                        <div v-if="isQuickAddOpen" class="admin-quick-add-menu" role="menu">
                            <Link v-for="action in quickActions" :key="action.id" :href="appUrl(action.url)" role="menuitem" @click="isQuickAddOpen = false">
                                <i class="bi" :class="action.icon"></i>{{ action.label }}
                            </Link>
                        </div>
                    </div>
                    <NotificationBell />
                    <DashboardUserMenu :profile-url="appUrl('/admin/profile')" :show-view-website="true" />
                </div>
            </header>

            <main class="admin-main">
                <AdminToasts />
                <div class="admin-content"><slot /></div>
            </main>

            <DashboardFooter variant="admin" />
        </div>
    </div>

    <AdminCommandPalette :open="isPaletteOpen" :navigation="navigation" :quick-actions="quickActions" @close="isPaletteOpen = false" />
</template>

<style scoped>
.admin-shell { min-height: 100vh; background: #090f1d; color: #e5e7eb; display: flex; }
.admin-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 1040; display: flex; width: 270px; flex-direction: column; overflow-y: auto; background: #101827; border-right: 1px solid #263247; box-shadow: 12px 0 35px rgba(0,0,0,.22); }
.admin-sidebar-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.25rem 1rem 0; border-bottom: 1px solid #1e293b; }
.admin-sidebar-close { display: none; padding: .35rem; background: transparent; border: 0; color: #cbd5e1; font-size: 1.2rem; }
.admin-nav-link { display: flex; width: 100%; align-items: center; gap: .75rem; padding: .68rem .8rem; border: 0; border-radius: .65rem; background: transparent; color: #cbd5e1; font-size: .92rem; text-align: left; text-decoration: none; transition: background-color .18s ease,color .18s ease; }
.admin-nav-link i { width: 1.2rem; color: #94a3b8; text-align: center; }
.admin-nav-link:hover { background: #1e293b; color: #fff; }
.admin-nav-link--muted { color: #94a3b8; font-size: 0.85rem; }
.admin-sidebar-footer { padding: 1rem .85rem; border-top: 1px solid #1e293b; }
.admin-main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; margin-left: 270px; min-height: 100vh; background: #090f1d; }
.admin-topbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; height: 56px; padding: 0 1.25rem; background: #101827; border-bottom: 1px solid #263247; position: sticky; top: 0; z-index: 1020; }
.admin-topbar-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.admin-topbar-hamburger { display: none; width: 36px; height: 36px; align-items: center; justify-content: center; border: 0; border-radius: 8px; background: #1e293b; color: #e5e7eb; font-size: 1.2rem; }
.admin-global-search { display: inline-flex; align-items: center; gap: .6rem; min-width: 0; max-width: 420px; width: 300px; padding: .45rem .8rem; border: 1px solid #263247; border-radius: .6rem; background: #0b1322; color: #94a3b8; font-size: .85rem; }
.admin-global-search span { flex: 1; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.admin-global-search:hover { border-color: #f59e0b; color: #cbd5e1; }
.admin-global-search kbd { padding: 0 .4rem; border: 1px solid #334155; border-radius: .35rem; background: #1e293b; font-size: .68rem; }
.admin-topbar-right { display: flex; align-items: center; gap: .75rem; }
.admin-quick-add { position: relative; }
.admin-quick-add-toggle { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem .8rem; border: 0; border-radius: .6rem; background: #f59e0b; color: #111827; font-size: .85rem; font-weight: 600; }
.admin-quick-add-toggle:hover { background: #fbbf24; }
.admin-quick-add-menu { position: absolute; right: 0; top: calc(100% + 6px); z-index: 1050; min-width: 200px; padding: .35rem; border: 1px solid #334155; border-radius: .6rem; background: #162235; box-shadow: 0 12px 32px rgba(0,0,0,.45); }
.admin-quick-add-menu a { display: flex; align-items: center; gap: .6rem; padding: .5rem .7rem; border-radius: .45rem; color: #e5e7eb; text-decoration: none; font-size: .88rem; }
.admin-quick-add-menu a:hover { background: #1e293b; color: #fff; }
.admin-quick-add-menu a i { color: #fbbf24; }
.admin-main { flex: 1; }
.admin-content { width: 100%; max-width: 1500px; padding: 1.75rem; }
.admin-sidebar-backdrop { display: none; }
.admin-content :deep(h1),.admin-content :deep(h2),.admin-content :deep(h3),.admin-content :deep(h4),.admin-content :deep(h5),.admin-content :deep(h6) { color: #f8fafc; }
.admin-content :deep(.card),.admin-content :deep(.border),.admin-content :deep(.bg-light) { background-color: #111c2d !important; border-color: #334155 !important; color: #e5e7eb; }
.admin-content :deep(.table) { --bs-table-bg: transparent; --bs-table-color: #e5e7eb; --bs-table-border-color: #334155; --bs-table-striped-color: #e5e7eb; --bs-table-hover-color: #fff; --bs-table-hover-bg: rgba(51,65,85,.45); color: #e5e7eb; }
.admin-content :deep(.form-control),.admin-content :deep(.form-select) { border-color: #475569; background-color: #162235; color: #f8fafc; color-scheme: dark; }
.admin-content :deep(.form-control::placeholder) { color: #94a3b8; }
.admin-content :deep(.form-control:focus),.admin-content :deep(.form-select:focus) { border-color: #f59e0b; background-color: #162235; color: #fff; box-shadow: 0 0 0 .2rem rgba(245,158,11,.18); }
.admin-content :deep(.form-check-input) { border-color: #64748b; background-color: #162235; }.admin-content :deep(.form-check-input:checked) { border-color: #f59e0b; background-color: #f59e0b; }
.admin-content :deep(.text-muted),.admin-content :deep(.form-text) { color: #aebbd0 !important; }.admin-content :deep(.dropdown-menu),.admin-content :deep(.list-group-item) { border-color: #475569; background: #162235; color: #e5e7eb; }.admin-content :deep(.page-link) { border-color: #334155; background: #162235; color: #e5e7eb; }.admin-content :deep(.page-item.active .page-link) { border-color: #f59e0b; background: #f59e0b; color: #111827; }
@media (max-width: 991.98px) {
    .admin-sidebar { width: min(86vw,300px); transform: translateX(-105%); transition: transform .22s ease; }
    .admin-sidebar.is-open { transform: translateX(0); }
    .admin-sidebar-close { display: inline-flex; }
    .admin-sidebar-backdrop { position: fixed; inset: 0; z-index: 1035; display: block; padding: 0; border: 0; background: rgba(2,6,23,.7); }
    .admin-main-wrapper { margin-left: 0; }
    .admin-topbar-hamburger { display: inline-flex; }
    .admin-global-search { width: auto; }
    .admin-content { padding: 1.25rem 1rem 1.5rem; }
}
</style>
