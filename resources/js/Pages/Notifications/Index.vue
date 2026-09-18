<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import VendorLayout from '../../Layouts/VendorLayout.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import Pagination from '../../Components/Pagination.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    notifications: { type: Object, required: true },
    unreadCount: { type: Number, default: 0 },
    role: { type: String, default: 'customer' },
});

const layout = computed(() => props.role === 'admin' ? AdminLayout : props.role === 'vendor' ? VendorLayout : AppLayout);

function markRead(notification) {
    router.patch(appUrl(`/notifications/${notification.id}/read`), {}, { preserveScroll: true });
}
function markAllRead() {
    router.post(appUrl('/notifications/read-all'), {}, { preserveScroll: true });
}
function actionHref(notification) {
    const url = notification.data?.action_url;
    return url ? appUrl(url) : null;
}
function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <component :is="layout">
        <div class="mx-auto" style="max-width: 860px;">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h2 class="mb-1">Notifications</h2>
                    <p class="text-muted small mb-0">{{ unreadCount }} unread</p>
                </div>
                <button v-if="unreadCount" type="button" class="btn btn-sm btn-outline-secondary" @click="markAllRead">Mark all as read</button>
            </div>

            <div v-for="notification in notifications.data" :key="notification.id" class="card p-3 mb-2" :class="{ 'border-primary': !notification.read_at }">
                <div class="d-flex justify-content-between gap-3">
                    <div>
                        <div class="fw-semibold">{{ notification.data?.title ?? 'Notification' }}</div>
                        <p class="small text-muted mb-1">{{ notification.data?.message }}</p>
                        <div class="small text-muted">{{ formatDateTime(notification.created_at) }}</div>
                    </div>
                    <div class="d-flex flex-column gap-2 align-items-end">
                        <span v-if="!notification.read_at" class="badge bg-primary">New</span>
                        <Link v-if="actionHref(notification)" :href="actionHref(notification)" class="btn btn-sm btn-outline-primary">View</Link>
                        <button v-if="!notification.read_at" type="button" class="btn btn-sm btn-link p-0" @click="markRead(notification)">Mark read</button>
                    </div>
                </div>
            </div>

            <p v-if="!notifications.data.length" class="text-muted text-center py-4">No notifications yet.</p>
            <Pagination :links="notifications.links" />
        </div>
    </component>
</template>
