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

const THEME_KEY = 'svtp-admin-theme';
const COLLAPSE_KEY = 'svtp-admin-collapsed';

// Theme choice: light | dark | system (persisted; OS preference when system).
const themeChoice = ref('system');
try {
    themeChoice.value = localStorage.getItem(THEME_KEY)
        || document.documentElement.getAttribute('data-admin-theme-choice')
        || 'system';
} catch (e) { /* private mode */ }

// Desktop sidebar collapse (persisted; mobile drawer uses isSidebarOpen).
const isCollapsed = ref(false);
try {
    isCollapsed.value = localStorage.getItem(COLLAPSE_KEY) === '1';
} catch (e) { /* private mode */ }

function resolveTheme(choice) {
    if (choice === 'dark') return 'dark';
    if (choice === 'light') return 'light';
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme() {
    document.documentElement.setAttribute('data-admin-theme', resolveTheme(themeChoice.value));
    document.documentElement.setAttribute('data-admin-theme-choice', themeChoice.value);
    try {
        localStorage.setItem(THEME_KEY, themeChoice.value);
    } catch (e) { /* private mode */ }
}

function setTheme(choice) {
    themeChoice.value = choice;
    applyTheme();
}

function onSystemThemeChange() {
    if (themeChoice.value === 'system') applyTheme();
}

function toggleCollapse() {
    isCollapsed.value = !isCollapsed.value;
    try {
        localStorage.setItem(COLLAPSE_KEY, isCollapsed.value ? '1' : '0');
    } catch (e) { /* private mode */ }
}

let systemMedia = null;

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

onMounted(() => {
    document.addEventListener('keydown', onGlobalKeydown);
    applyTheme();
    try {
        systemMedia = window.matchMedia('(prefers-color-scheme: dark)');
        if (systemMedia.addEventListener) systemMedia.addEventListener('change', onSystemThemeChange);
    } catch (e) { /* unsupported */ }
});
onUnmounted(() => {
    document.removeEventListener('keydown', onGlobalKeydown);
    try {
        if (systemMedia && systemMedia.removeEventListener) systemMedia.removeEventListener('change', onSystemThemeChange);
    } catch (e) { /* unsupported */ }
});

watch(() => page.url, () => {
    isSidebarOpen.value = false;
    isQuickAddOpen.value = false;
});
</script>

<template>
    <ImpersonationBanner />
    <div class="admin-shell" :class="{ 'is-collapsed': isCollapsed }">
        <aside class="admin-sidebar" :class="{ 'is-open': isSidebarOpen }">
            <div class="admin-sidebar-heading">
                <DashboardBrand panel-label="Admin Console" />
                <button type="button" class="admin-sidebar-close" aria-label="Close navigation" @click="isSidebarOpen = false"><i class="bi bi-x-lg"></i></button>
            </div>

            <AdminSidebar :groups="navigation" :collapsed="isCollapsed" />

            <div class="admin-sidebar-footer">
                <Link :href="appUrl('/')" class="admin-nav-link admin-nav-link--muted"><i class="bi bi-box-arrow-up-right"></i><span>Back to Website</span></Link>
            </div>
        </aside>

        <button v-if="isSidebarOpen" type="button" class="admin-sidebar-backdrop" aria-label="Close navigation" @click="isSidebarOpen = false"></button>

        <div class="admin-main-wrapper">
            <header class="admin-topbar">
                <div class="admin-topbar-left">
                    <button type="button" class="admin-topbar-hamburger" aria-label="Open navigation" @click="isSidebarOpen = true"><i class="bi bi-list"></i></button>
                    <button type="button" class="admin-collapse-toggle d-none d-lg-inline-flex" :aria-label="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'" :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'" @click="toggleCollapse"><i class="bi" :class="isCollapsed ? 'bi-layout-sidebar-inset' : 'bi-layout-sidebar'"></i></button>
                    <button type="button" class="admin-global-search" aria-label="Search anything (Ctrl+K)" @click="openPalette">
                        <i class="bi bi-search"></i>
                        <span class="d-none d-md-inline">Search anything...</span>
                        <kbd class="d-none d-md-inline">Ctrl K</kbd>
                    </button>
                </div>
                <div class="admin-topbar-right">
                    <div class="admin-theme-switch" role="group" aria-label="Color theme">
                        <button
                            v-for="opt in [
                                { value: 'light', icon: 'bi-sun', label: 'Light' },
                                { value: 'dark', icon: 'bi-moon-stars', label: 'Dark' },
                                { value: 'system', icon: 'bi-circle-half', label: 'System' },
                            ]"
                            :key="opt.value"
                            type="button"
                            class="admin-theme-btn"
                            :class="{ 'is-active': themeChoice === opt.value }"
                            :aria-pressed="themeChoice === opt.value"
                            :title="`${opt.label} theme`"
                            @click="setTheme(opt.value)"
                        ><i class="bi" :class="opt.icon"></i><span class="d-none d-xl-inline">{{ opt.label }}</span></button>
                    </div>
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
.admin-shell { min-height: 100vh; background: var(--admin-bg); color: var(--admin-text); display: flex; }
.admin-sidebar { position: fixed; inset: 0 auto 0 0; z-index: 1040; display: flex; width: 270px; flex-direction: column; overflow-y: auto; background: var(--admin-sidebar-bg); border-right: 1px solid var(--admin-sidebar-border); box-shadow: var(--admin-shadow-md); transition: width .2s ease; }
.admin-sidebar-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.1rem 1rem .9rem; border-bottom: 1px solid var(--admin-sidebar-border); }
.admin-sidebar-close { display: none; padding: .35rem; background: transparent; border: 0; color: var(--admin-sidebar-muted); font-size: 1.2rem; }
.admin-nav-link { display: flex; width: 100%; align-items: center; gap: .75rem; padding: .68rem .8rem; border: 0; border-radius: .65rem; background: transparent; color: var(--admin-sidebar-text); font-size: .92rem; text-align: left; text-decoration: none; transition: background-color .18s ease,color .18s ease; }
.admin-nav-link i { width: 1.2rem; color: var(--admin-sidebar-muted); text-align: center; }
.admin-nav-link:hover { background: var(--admin-sidebar-hover-bg); color: var(--admin-sidebar-active-text); }
.admin-nav-link--muted { color: var(--admin-sidebar-muted); font-size: 0.85rem; }
.admin-sidebar-footer { padding: 1rem .85rem; border-top: 1px solid var(--admin-sidebar-border); }
.admin-main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; margin-left: 270px; min-height: 100vh; background: var(--admin-bg); transition: margin-left .2s ease; }
.admin-topbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 58px; padding: .45rem 1.25rem; background: var(--admin-topbar-bg); -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px); border-bottom: 1px solid var(--admin-topbar-border); position: sticky; top: 0; z-index: 1020; color: var(--admin-topbar-text); }
.admin-topbar-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.admin-topbar-hamburger { display: none; width: 36px; height: 36px; align-items: center; justify-content: center; border: 1px solid var(--admin-border); border-radius: 8px; background: var(--admin-surface); color: var(--admin-text); font-size: 1.2rem; }
.admin-collapse-toggle { width: 36px; height: 36px; align-items: center; justify-content: center; border: 1px solid var(--admin-border); border-radius: 8px; background: var(--admin-surface); color: var(--admin-text-muted); font-size: 1.05rem; }
.admin-collapse-toggle:hover { color: var(--admin-primary); border-color: var(--admin-primary); }
.admin-global-search { display: inline-flex; align-items: center; gap: .6rem; min-width: 0; max-width: 420px; width: 300px; padding: .45rem .8rem; border: 1px solid var(--admin-input-border); border-radius: .6rem; background: var(--admin-input-bg); color: var(--admin-text-muted); font-size: .85rem; box-shadow: var(--admin-shadow-sm); }
.admin-global-search span { flex: 1; text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.admin-global-search:hover { border-color: var(--admin-primary); color: var(--admin-text); }
.admin-global-search kbd { padding: 0 .4rem; border: 1px solid var(--admin-border); border-radius: .35rem; background: var(--admin-surface-sunken); font-size: .68rem; }
.admin-topbar-right { display: flex; align-items: center; gap: .75rem; }
.admin-theme-switch { display: inline-flex; align-items: center; gap: 2px; padding: 2px; border: 1px solid var(--admin-border); border-radius: .65rem; background: var(--admin-surface); box-shadow: var(--admin-shadow-sm); }
.admin-theme-btn { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .55rem; border: 0; border-radius: .5rem; background: transparent; color: var(--admin-text-muted); font-size: .78rem; font-weight: 600; }
.admin-theme-btn:hover { color: var(--admin-text); background: var(--admin-surface-sunken); }
.admin-theme-btn.is-active { background: var(--admin-primary); color: var(--admin-primary-contrast); }
.admin-quick-add { position: relative; }
.admin-quick-add-toggle { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem .8rem; border: 0; border-radius: .6rem; background: var(--admin-primary); color: var(--admin-primary-contrast); font-size: .85rem; font-weight: 600; }
.admin-quick-add-toggle:hover { background: var(--admin-primary-hover); }
.admin-quick-add-menu { position: absolute; right: 0; top: calc(100% + 6px); z-index: 1050; min-width: 200px; padding: .35rem; border: 1px solid var(--admin-popover-border); border-radius: .6rem; background: var(--admin-popover-bg); box-shadow: var(--admin-shadow-md); }
.admin-quick-add-menu a { display: flex; align-items: center; gap: .6rem; padding: .5rem .7rem; border-radius: .45rem; color: var(--admin-text); text-decoration: none; font-size: .88rem; }
.admin-quick-add-menu a:hover { background: var(--admin-surface-sunken); }
.admin-quick-add-menu a i { color: var(--admin-primary); }
.admin-quick-add-menu a { display: flex; align-items: center; gap: .6rem; padding: .5rem .7rem; border-radius: .45rem; color: var(--admin-text); text-decoration: none; font-size: .88rem; }
.admin-quick-add-menu a:hover { background: var(--admin-surface-sunken); }
.admin-quick-add-menu a i { color: var(--admin-primary); }
.admin-main { flex: 1; }
.admin-content { width: 100%; max-width: 1500px; padding: 1.75rem; color: var(--admin-text); }
.admin-sidebar-backdrop { display: none; }
/* Page inheritance: every admin page picks up the premium system automatically. */
.admin-content :deep(h1),.admin-content :deep(h2),.admin-content :deep(h3),.admin-content :deep(h4),.admin-content :deep(h5),.admin-content :deep(h6) { color: var(--admin-text); font-family: var(--admin-font); letter-spacing: -0.01em; }
/* Ordinary links (entity names, table links, breadcrumbs) use the link token —
   never danger red — so they stay readable on both light and dark surfaces. */
.admin-content :deep(a) { color: var(--admin-link); }
.admin-content :deep(a:hover) { color: var(--admin-link-hover); text-decoration: underline; }
.admin-content :deep(a.btn) { text-decoration: none; }
.admin-content :deep(a.btn:hover) { text-decoration: none; }
.admin-content :deep(.card),.admin-content :deep(.border),.admin-content :deep(.bg-light) { background-color: var(--admin-surface) !important; border-color: var(--admin-border) !important; color: var(--admin-text); box-shadow: var(--admin-shadow-sm); }
.admin-content :deep(.card-header),.admin-content :deep(.card-footer) { background-color: var(--admin-surface-sunken); border-color: var(--admin-border); color: var(--admin-text); }
.admin-content :deep(.table) { --bs-table-bg: transparent; --bs-table-color: var(--admin-text); --bs-table-border-color: var(--admin-border); --bs-table-striped-color: var(--admin-text); --bs-table-hover-color: var(--admin-text); --bs-table-hover-bg: var(--admin-surface-sunken); color: var(--admin-text); }
.admin-content :deep(.table thead th) { color: var(--admin-text-muted); font-weight: 700; font-size: .78rem; letter-spacing: .04em; text-transform: uppercase; border-bottom-color: var(--admin-border-strong); }
.admin-content :deep(.form-label) { color: var(--admin-text); font-weight: 600; }
.admin-content :deep(.form-control),.admin-content :deep(.form-select) { border-color: var(--admin-input-border); background-color: var(--admin-input-bg); color: var(--admin-text); }
.admin-content :deep(.form-control::placeholder) { color: var(--admin-input-placeholder); }
.admin-content :deep(.form-control:focus),.admin-content :deep(.form-select:focus) { border-color: var(--admin-primary); background-color: var(--admin-input-bg); color: var(--admin-text); box-shadow: 0 0 0 .2rem var(--admin-focus-ring); }
.admin-content :deep(.form-check-input) { border-color: var(--admin-input-border); background-color: var(--admin-input-bg); }.admin-content :deep(.form-check-input:checked) { border-color: var(--admin-primary); background-color: var(--admin-primary); }
.admin-content :deep(.text-muted),.admin-content :deep(.form-text) { color: var(--admin-text-muted) !important; }.admin-content :deep(.dropdown-menu) { border-color: var(--admin-popover-border); background: var(--admin-popover-bg); color: var(--admin-text); box-shadow: var(--admin-shadow-md); }.admin-content :deep(.dropdown-item) { color: var(--admin-text); }.admin-content :deep(.dropdown-item:hover) { background: var(--admin-surface-sunken); color: var(--admin-text); }.admin-content :deep(.list-group-item) { border-color: var(--admin-border); background: var(--admin-surface); color: var(--admin-text); }.admin-content :deep(.page-link) { border-color: var(--admin-border); background: var(--admin-surface); color: var(--admin-link); }.admin-content :deep(.page-item.active .page-link) { border-color: var(--admin-primary); background: var(--admin-primary); color: var(--admin-primary-contrast); }
.admin-content :deep(.modal-content) { background: var(--admin-surface-elevated); border-color: var(--admin-border); color: var(--admin-text); box-shadow: var(--admin-shadow-lg); }.admin-content :deep(.modal-header),.admin-content :deep(.modal-footer) { border-color: var(--admin-border); }.admin-content :deep(.btn-close) { filter: var(--admin-btn-close-filter, none); }
.admin-content :deep(.invalid-feedback) { color: var(--admin-danger); }.admin-content :deep(.valid-feedback) { color: var(--admin-success); }
.admin-content :deep(.breadcrumb-item),.admin-content :deep(.breadcrumb-item a) { color: var(--admin-link); }.admin-content :deep(.breadcrumb-item.active) { color: var(--admin-text-muted); }
@media (max-width: 991.98px) {
    .admin-sidebar { width: min(86vw,300px); transform: translateX(-105%); transition: transform .22s ease; }
    .admin-sidebar.is-open { transform: translateX(0); }
    .admin-sidebar-close { display: inline-flex; }
    .admin-sidebar-backdrop { position: fixed; inset: 0; z-index: 1035; display: block; padding: 0; border: 0; background: rgba(2,6,23,.7); }
    .admin-main-wrapper { margin-left: 0; }
    .admin-topbar-hamburger { display: inline-flex; }
    .admin-global-search { width: auto; }
    .admin-content { padding: 1.25rem 1rem 1.5rem; }
    .admin-theme-switch .admin-theme-btn span { display: none; }
}
</style>
