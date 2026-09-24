<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { appUrl } from '../appUrl';

const page = usePage();
const unread = computed(() => Number(page.props.notificationsUnreadCount ?? 0));

const open = ref(false);
const loading = ref(false);
const items = ref([]);
const panel = ref(null);

async function toggle() {
    open.value = !open.value;

    if (open.value) {
        await refresh();
    }
}

function close() {
    open.value = false;
}

async function refresh() {
    loading.value = true;

    try {
        const response = await axios.get(appUrl('/notifications/recent'));
        items.value = response.data.items ?? [];
    } catch (e) {
        items.value = [];
    } finally {
        loading.value = false;
    }
}

function markRead(id) {
    router.patch(appUrl(`/notifications/${id}/read`), {}, {
        preserveScroll: true,
        onSuccess: () => refresh(),
    });
}

function markAllRead() {
    router.post(appUrl('/notifications/read-all'), {}, {
        preserveScroll: true,
        onSuccess: () => refresh(),
    });
}

function onClickOutside(event) {
    if (panel.value && !panel.value.contains(event.target)) {
        close();
    }
}

function onEscape(event) {
    if (event.key === 'Escape') {
        close();
    }
}

onMounted(() => {
    document.addEventListener('click', onClickOutside);
    document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
    document.removeEventListener('keydown', onEscape);
});

function timeAgo(value) {
    if (!value) {
        return '';
    }

    const seconds = Math.floor((Date.now() - new Date(value).getTime()) / 1000);

    if (seconds < 60) {
        return 'just now';
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)}m ago`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)}h ago`;
    }

    return new Date(value).toLocaleDateString('en-IN');
}
</script>

<template>
    <div ref="panel" class="notification-bell-wrap">
        <button type="button" class="notification-bell" aria-label="Notifications" :aria-expanded="open" @click="toggle">
            <i class="bi bi-bell"></i>
            <span v-if="unread > 0" class="notification-bell-badge">{{ unread > 99 ? '99+' : unread }}</span>
        </button>

        <div v-if="open" class="notification-dropdown" role="menu" aria-label="Recent notifications">
            <div class="notification-dropdown-head">
                <strong>Notifications</strong>
                <button v-if="unread > 0" type="button" class="btn btn-link btn-sm p-0" @click="markAllRead">Mark all read</button>
            </div>

            <div v-if="loading" class="notification-dropdown-empty">Loading…</div>
            <div v-else-if="!items.length" class="notification-dropdown-empty">You're all caught up.</div>
            <ul v-else class="notification-dropdown-list">
                <li v-for="item in items" :key="item.id" :class="{ 'is-unread': !item.read }">
                    <div class="notification-item-body">
                        <div class="notification-item-title">{{ item.title }}</div>
                        <div class="notification-item-message">{{ item.message }}</div>
                        <div class="notification-item-time">{{ timeAgo(item.created_at) }}</div>
                    </div>
                    <div class="notification-item-actions">
                        <Link v-if="item.action_url" :href="appUrl(item.action_url)" class="btn btn-sm btn-link p-0" @click="close">Open</Link>
                        <button v-if="!item.read" type="button" class="btn btn-sm btn-link p-0" @click="markRead(item.id)">Mark read</button>
                    </div>
                </li>
            </ul>

            <Link :href="appUrl('/notifications')" class="notification-dropdown-foot" @click="close">View all notifications</Link>
        </div>
    </div>
</template>

<style scoped>
.notification-bell-wrap { position: relative; }
.notification-bell { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border: 0; border-radius: 8px; background: transparent; color: inherit; }
.notification-bell:hover { background: rgba(127, 127, 127, 0.15); }
.notification-bell-badge { position: absolute; top: 2px; right: 0; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 9px; background: #dc3545; color: #fff; font-size: 11px; font-weight: 700; line-height: 18px; text-align: center; }
.notification-dropdown { position: absolute; right: 0; top: calc(100% + 8px); z-index: 1050; width: min(360px, 90vw); max-height: 420px; display: flex; flex-direction: column; overflow: hidden; border: 1px solid var(--admin-popover-border, rgba(127, 127, 127, 0.3)); border-radius: 12px; background: var(--admin-popover-bg, var(--bs-body-bg, #fff)); color: var(--admin-text, #212529); box-shadow: var(--admin-shadow-md, 0 12px 32px rgba(0, 0, 0, 0.25)); }
.notification-dropdown-head { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid var(--admin-border, rgba(127, 127, 127, 0.2)); }
.notification-dropdown-head strong { color: var(--admin-text, #212529); }
.notification-dropdown-head .btn-link { color: var(--admin-link, #0d6efd); }
.notification-dropdown-empty { padding: 20px 14px; color: var(--admin-text-muted, #6c757d); font-size: 0.9rem; text-align: center; }
.notification-dropdown-list { margin: 0; padding: 4px; overflow-y: auto; list-style: none; }
.notification-dropdown-list li { display: flex; gap: 8px; align-items: flex-start; justify-content: space-between; padding: 10px; border-radius: 8px; color: var(--admin-text, #212529); }
.notification-dropdown-list li.is-unread { background: var(--admin-surface-sunken, rgba(13, 110, 253, 0.08)); }
.notification-dropdown-list li:hover { background: var(--admin-surface-sunken, rgba(13, 110, 253, 0.08)); }
.notification-item-body { min-width: 0; }
.notification-item-title { color: var(--admin-text, #212529); font-weight: 600; font-size: 0.9rem; }
.notification-item-message { color: var(--admin-text-muted, #6c757d); font-size: 0.82rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.notification-item-time { color: var(--admin-text-muted, #6c757d); font-size: 0.75rem; }
.notification-item-actions { display: flex; flex-direction: column; gap: 2px; flex-shrink: 0; }
.notification-item-actions .btn-link { color: var(--admin-link, #0d6efd); }
.notification-dropdown-foot { display: block; padding: 10px 14px; border-top: 1px solid var(--admin-border, rgba(127, 127, 127, 0.2)); color: var(--admin-link, #0d6efd); font-size: 0.88rem; text-align: center; text-decoration: none; }
.notification-dropdown .btn-link:hover, .notification-dropdown .btn-link:focus-visible, .notification-dropdown-foot:hover, .notification-dropdown-foot:focus-visible { color: var(--admin-link-hover, var(--admin-link, #0d6efd)); }
.notification-dropdown :focus-visible { outline: 2px solid var(--admin-primary, #0d6efd); outline-offset: 2px; }
</style>
