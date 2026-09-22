<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';

defineProps({
    active: { type: String, default: 'dashboard' },
});

const page = usePage();
const unread = computed(() => Number(page.props.notificationsUnreadCount ?? 0));

const tabs = [
    { key: 'hotels', label: 'My Hotel Bookings', href: '/account/hotel-bookings', icon: 'bi-buildings' },
    { key: 'taxi', label: 'My Taxi Bookings', href: '/account/taxi/bookings', icon: 'bi-taxi-front' },
    { key: 'dashboard', label: 'Dashboard', href: '/account', icon: 'bi-speedometer2' },
    { key: 'bookings', label: 'My Bookings', href: '/account/bookings', icon: 'bi-calendar-check' },
    { key: 'support', label: 'Support', href: '/account/support', icon: 'bi-life-preserver' },
    { key: 'notifications', label: 'Notifications', href: '/notifications', icon: 'bi-bell' },
    { key: 'profile', label: 'Profile', href: '/account/profile', icon: 'bi-person-gear' },
    { key: 'vendor', label: 'Become a Vendor', href: '/vendor/apply', icon: 'bi-shop' },
];
const visibleTabs = computed(() => tabs.filter(tab => tab.key !== 'hotels'
    || page.props.platformModules?.some(module => module.key === 'hotels' && module.enabled)));
</script>

<template>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <Link
            v-for="tab in visibleTabs"
            :key="tab.key"
            :href="appUrl(tab.href)"
            class="btn btn-sm"
            :class="active === tab.key ? 'btn-svtp' : 'btn-outline-svtp'"
        >
            <i class="bi me-1" :class="tab.icon"></i>{{ tab.label }}
            <span v-if="tab.key === 'notifications' && unread > 0" class="badge bg-danger ms-1">{{ unread > 99 ? '99+' : unread }}</span>
        </Link>
        <Link :href="appUrl('/admin/logout')" method="post" as="button" class="btn btn-sm btn-outline-secondary ms-auto">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
        </Link>
    </div>
</template>
