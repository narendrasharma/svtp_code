<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    leads: Object,
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    serviceTypes: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
    canViewAll: { type: Boolean, default: false },
});

const endpoint = appUrl('/admin/leads');
const filters = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    priority: props.filters.priority ?? '',
    service_type: props.filters.service_type ?? '',
    assigned_to: props.filters.assigned_to ?? '',
    filter: props.filters.filter ?? '',
});

const quickFilters = [
    { value: '', label: 'All' },
    { value: 'unassigned', label: 'Unassigned' },
    { value: 'hot', label: 'Hot' },
    { value: 'due_today', label: 'Due today' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'upcoming', label: 'Upcoming' },
];

function apply() {
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}

function setQuick(value) {
    filters.filter = value;
    apply();
}

function priorityBadge(priority) {
    return { low: 'bg-secondary', normal: 'bg-info text-dark', high: 'bg-warning text-dark', hot: 'bg-danger' }[priority] ?? 'bg-secondary';
}

function statusBadge(status) {
    return { new: 'bg-primary', won: 'bg-success', lost: 'bg-secondary' }[status] ?? 'bg-light text-dark';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="mt-2 mb-1">Leads</h2>
                <p class="text-muted mb-0">{{ canViewAll ? 'Every lead in the pipeline.' : 'Leads assigned to you.' }} Website enquiries flow in automatically.</p>
            </div>
            <Link :href="appUrl('/admin/leads/create')" class="btn btn-svtp"><i class="bi bi-plus-lg me-1"></i>New Lead</Link>
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
                    <label for="lead-search" class="form-label small">Search</label>
                    <input id="lead-search" v-model="filters.search" class="form-control form-control-sm" placeholder="Reference, name, phone, email" />
                </div>
                <div class="col-md-2">
                    <label for="lead-status" class="form-label small">Status</label>
                    <select id="lead-status" v-model="filters.status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="lead-priority" class="form-label small">Priority</label>
                    <select id="lead-priority" v-model="filters.priority" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="lead-service" class="form-label small">Service</label>
                    <select id="lead-service" v-model="filters.service_type" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="t in serviceTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                </div>
                <div v-if="canViewAll" class="col-md-2">
                    <label for="lead-assignee" class="form-label small">Assignee</label>
                    <select id="lead-assignee" v-model="filters.assigned_to" class="form-select form-select-sm">
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
                <thead><tr><th>Reference</th><th>Contact</th><th>Interest</th><th>Status</th><th>Assignee</th><th>Follow-ups</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="lead in leads.data" :key="lead.id">
                        <td class="text-nowrap">{{ lead.reference }}</td>
                        <td><div>{{ lead.name }}</div><small class="text-muted">{{ lead.phone }}<span v-if="lead.email"> · {{ lead.email }}</span></small></td>
                        <td><small>{{ lead.product_title || lead.destination || lead.service_type }}</small></td>
                        <td><span class="badge" :class="statusBadge(lead.status)">{{ lead.status }}</span> <span class="badge ms-1" :class="priorityBadge(lead.priority)">{{ lead.priority }}</span></td>
                        <td class="small">{{ lead.assignee?.name ?? '—' }}</td>
                        <td class="small">{{ lead.pending_follow_ups_count ? `${lead.pending_follow_ups_count} pending` : '—' }}</td>
                        <td class="text-end"><Link :href="appUrl(`/admin/leads/${lead.id}`)" class="btn btn-sm btn-outline-primary">Open</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!leads.data.length" class="text-muted">No leads found.</p>
        <Pagination :links="leads.links" />
    </AdminLayout>
</template>
