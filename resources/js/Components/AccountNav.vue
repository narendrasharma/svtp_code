<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';
import { useLocalization } from '../i18n';

const props = defineProps({
    active: { type: String, default: '' },
});

const page = usePage();
const { t } = useLocalization();
const unread = computed(() => Number(page.props.notificationsUnreadCount ?? 0));

const tabs = [
    { key: 'hotels', label: () => t('common.hotel_bookings', 'Hotel Bookings'), href: '/account/hotel-bookings', icon: 'bi-buildings' },
    { key: 'taxi', label: () => t('common.taxi_bookings', 'Taxi Bookings'), href: '/account/taxi/bookings', icon: 'bi-taxi-front' },
    { key: 'dashboard', label: () => t('common.overview', 'Overview'), href: '/account', icon: 'bi-house' },
    { key: 'bookings', label: () => t('common.tour_bookings', 'Tour Bookings'), href: '/account/bookings', icon: 'bi-calendar-check' },
    { key: 'support', label: () => t('common.support', 'Support'), href: '/account/support', icon: 'bi-life-preserver' },
    { key: 'notifications', label: () => t('common.notifications', 'Notifications'), href: '/notifications', icon: 'bi-bell' },
    { key: 'profile', label: () => t('common.profile', 'Profile'), href: '/account/profile', icon: 'bi-person-gear' },
    { key: 'vendor', label: () => t('common.become_vendor', 'Become a Vendor'), href: '/vendor/apply', icon: 'bi-shop' },
];
const visibleTabs = computed(() => tabs.filter(tab => {
    const moduleKey = { hotels: 'hotels', bookings: 'tours', taxi: 'taxi' }[tab.key];
    const module = page.props.platformModules?.find(item => item.key === moduleKey);
    return !moduleKey || (module ? module.enabled : moduleKey !== 'hotels');
}).sort((a, b) => ['dashboard', 'profile', 'hotels', 'bookings', 'taxi', 'support', 'notifications', 'vendor'].indexOf(a.key)
    - ['dashboard', 'profile', 'hotels', 'bookings', 'taxi', 'support', 'notifications', 'vendor'].indexOf(b.key)));

const activeTab = computed(() => {
    const path = page.url.split('?')[0];
    if (path.startsWith(appUrl('/account/taxi/'))) return 'taxi';
    return tabs.find(tab => path === appUrl(tab.href)
        || (tab.key !== 'dashboard' && path.startsWith(appUrl(tab.href) + '/')))?.key || props.active;
});
</script>

<template>
    <nav class="account-nav" :aria-label="t('common.my_account', 'My account')">
        <Link
            v-for="tab in visibleTabs"
            :key="tab.key"
            :href="appUrl(tab.href)"
            class="account-nav__link"
            :class="{ 'is-active': activeTab === tab.key }"
            :aria-current="activeTab === tab.key ? 'page' : undefined"
        >
            <i class="bi" :class="tab.icon" aria-hidden="true"></i>{{ tab.label() }}
            <span v-if="tab.key === 'notifications' && unread > 0" class="badge bg-danger ms-1">{{ unread > 99 ? '99+' : unread }}</span>
        </Link>
        <Link :href="appUrl('/admin/logout')" method="post" as="button" class="account-nav__link account-nav__logout">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>{{ t('common.logout', 'Logout') }}
        </Link>
    </nav>
</template>

<style scoped>
.account-nav { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-block: 1rem 2rem; padding-block-end: 1rem; border-block-end: 1px solid var(--public-border); }
.account-nav__link { display: inline-flex; align-items: center; gap: .4rem; padding: .65rem .85rem; border: 1px solid var(--public-border); border-radius: .7rem; background: var(--public-surface); color: var(--public-text); font-size: .85rem; font-weight: 600; text-decoration: none; white-space: nowrap; }
.account-nav__link:hover { border-color: var(--public-brand); color: var(--public-brand); }
.account-nav__link.is-active { background: var(--public-brand); border-color: var(--public-brand); color: #fff; }
.account-nav__link:focus-visible { outline: 3px solid var(--public-brand); outline-offset: 3px; }
.account-nav__logout { margin-inline-start: auto; }
@media (max-width: 640px) {
    .account-nav { flex-wrap: nowrap; overflow-x: auto; padding: .3rem .2rem 1rem; }
    .account-nav__link { flex-shrink: 0; }
}
</style>
