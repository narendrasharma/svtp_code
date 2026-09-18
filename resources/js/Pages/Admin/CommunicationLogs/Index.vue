<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    logs: Object,
    filters: { type: Object, default: () => ({}) },
    channels: { type: Array, default: () => [] },
});

const filters = useForm({
    search: props.filters.search ?? '',
    channel: props.filters.channel ?? '',
    status: props.filters.status ?? '',
});

function apply() {
    filters.get(appUrl('/admin/communication-logs'), { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <h2 class="mt-2 mb-1">Message Log</h2>
        <p class="text-muted mb-3">Every outbound message, one row each. Destinations are masked — no raw PII, tokens or credentials are stored here.</p>

        <form class="card p-3 mb-3" @submit.prevent="apply">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="log-search" class="form-label small">Search</label>
                    <input id="log-search" v-model="filters.search" class="form-control form-control-sm" placeholder="Template, event, recipient" />
                </div>
                <div class="col-md-3">
                    <label for="log-channel" class="form-label small">Channel</label>
                    <select id="log-channel" v-model="filters.channel" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="c in channels" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="log-status" class="form-label small">Status</label>
                    <select id="log-status" v-model="filters.status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="sent">Sent</option>
                        <option value="failed">Failed</option>
                        <option value="skipped">Skipped</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-svtp w-100">Go</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle small">
                <thead><tr><th>When</th><th>Recipient</th><th>Channel</th><th>Event</th><th>Destination</th><th>Status</th><th>By</th></tr></thead>
                <tbody>
                    <tr v-for="log in logs.data" :key="log.id">
                        <td class="text-nowrap">{{ log.created_at ? new Date(log.created_at).toLocaleString('en-IN') : '' }}</td>
                        <td>{{ log.recipient?.name ?? `user #${log.recipient_user_id}` }} <span class="text-muted">({{ log.recipient_type }})</span></td>
                        <td><span class="badge bg-light text-dark">{{ log.channel }}</span></td>
                        <td>{{ log.template_key ?? log.event ?? '—' }}</td>
                        <td class="text-muted">{{ log.destination_masked ?? '—' }}</td>
                        <td>
                            <span class="badge" :class="log.status === 'sent' ? 'bg-success' : log.status === 'failed' ? 'bg-danger' : 'bg-secondary'">{{ log.status }}</span>
                            <div v-if="log.error" class="text-danger">{{ log.error }}</div>
                        </td>
                        <td class="text-muted">{{ log.creator?.name ?? 'system' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!logs.data.length" class="text-muted">No messages logged yet.</p>
        <Pagination :links="logs.links" />
    </AdminLayout>
</template>
