<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    tickets: Object,
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
    canViewAll: { type: Boolean, default: false },
});

const endpoint = appUrl('/admin/support');
const filters = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    category_id: props.filters.category_id ?? '',
    assigned_to: props.filters.assigned_to ?? '',
    requester_type: props.filters.requester_type ?? '',
    filter: props.filters.filter ?? '',
});

const quickFilters = [
    { value: '', label: 'All' },
    { value: 'mine', label: 'Mine' },
    { value: 'unassigned', label: 'Unassigned' },
    { value: 'stale', label: 'Stale 48h+' },
];

function apply() {
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}

function setQuick(value) {
    filters.filter = value;
    apply();
}

function statusBadge(status) {
    return { open: 'bg-primary', pending_staff: 'bg-warning text-dark', pending_customer: 'bg-info text-dark', resolved: 'bg-success', closed: 'bg-secondary' }[status] ?? 'bg-secondary';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="mt-2 mb-1">Support Tickets</h2>
                <p class="text-muted mb-0">{{ canViewAll ? 'Every ticket in the helpdesk.' : 'Tickets assigned to you.' }}</p>
            </div>
            <Link :href="appUrl('/admin/support-categories')" class="btn btn-sm btn-outline-secondary">Categories</Link>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <button
                v-for="qf in quickFilters"
                :key="qf.value"
                type="button"
                class="btn btn-sm"
                :class="filters.filter === qf.value ? 'btn-svtp' : 'btn-outline-secondary'"
                @click="setQuick(qf.value)"
            >{{ qf.label }}</button>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="apply">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="t-search" class="form-label small">Search</label>
                    <input id="t-search" v-model="filters.search" class="form-control form-control-sm" placeholder="Reference, subject, requester" />
                </div>
                <div class="col-md-2">
                    <label for="t-status" class="form-label small">Status</label>
                    <select id="t-status" v-model="filters.status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="t-priority" class="form-label small">Priority</label>
                    <select id="t-priority" v-model="filters.priority" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="t-category" class="form-label small">Category</label>
                    <select id="t-category" v-model="filters.category_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div v-if="canViewAll" class="col-md-2">
                    <label for="t-assignee" class="form-label small">Assignee</label>
                    <select id="t-assignee" v-model="filters.assigned_to" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-sm btn-svtp w-100">Go</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Reference</th><th>Subject</th><th>Requester</th><th>Status</th><th>Assignee</th><th>Last reply</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="ticket in tickets.data" :key="ticket.id">
                        <td class="text-nowrap">{{ ticket.reference }}</td>
                        <td>{{ ticket.subject }}</td>
                        <td class="small">{{ ticket.requester?.name ?? '—' }} <span class="text-muted">({{ ticket.requester?.role ?? '' }})</span></td>
                        <td><span class="badge" :class="statusBadge(ticket.status)">{{ ticket.status }}</span></td>
                        <td class="small">{{ ticket.assignee?.name ?? '—' }}</td>
                        <td class="small text-muted text-nowrap">{{ ticket.last_reply_at ? new Date(ticket.last_reply_at).toLocaleString('en-IN') : '—' }}</td>
                        <td class="text-end"><Link :href="appUrl(`/admin/support/${ticket.id}`)" class="btn btn-sm btn-outline-primary">Open</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!tickets.data.length" class="text-muted">No tickets found.</p>
        <Pagination :links="tickets.links" />
    </AdminLayout>
</template>
