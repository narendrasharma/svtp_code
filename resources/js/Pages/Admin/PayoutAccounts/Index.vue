<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    accounts: Object,
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    pendingCount: { type: Number, default: 0 },
});

const endpoint = appUrl('/admin/payout-accounts');
const filters = useForm({
    status: props.filters.status ?? '',
    search: props.filters.search ?? '',
});

const hasActiveFilters = computed(() => Object.values(filters.data()).some(value => value !== '' && value !== null));

function applyFilters() {
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}
function clearFilters() {
    router.get(endpoint);
}
function statusBadge(status) {
    return {
        pending: 'bg-warning text-dark',
        verified: 'bg-success',
        rejected: 'bg-danger',
    }[status] ?? 'bg-secondary';
}
function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN') : '—';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Payout Accounts</h2>
                <p class="text-muted mb-0">Vendor payout destinations. Masked identifiers only — full secrets never enter page source.</p>
            </div>
            <span v-if="pendingCount" class="badge bg-warning text-dark fs-6">{{ pendingCount }} pending</span>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label for="filter-status" class="form-label small">Status</label><select id="filter-status" v-model="filters.status" class="form-select form-select-sm"><option value="">All statuses</option><option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                <div class="col-md-4"><label for="filter-search" class="form-label small">Vendor</label><input id="filter-search" v-model="filters.search" class="form-control form-control-sm" placeholder="Business name" /></div>
                <div class="col-12 d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-svtp" :disabled="filters.processing">Apply Filters</button>
                    <button v-if="hasActiveFilters" type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                    <span class="text-muted small ms-auto align-self-center">{{ accounts.total }} account(s)</span>
                </div>
            </div>
        </form>

        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Vendor</th><th>KYC</th><th>Method</th><th>Masked Destination</th><th>Status</th><th>Updated</th><th></th></tr></thead>
            <tbody>
                <tr v-for="item in accounts.data" :key="item.id">
                    <td>{{ item.business_name ?? '—' }}</td>
                    <td><span class="badge" :class="item.kyc_verified ? 'bg-success' : 'bg-warning text-dark'">{{ item.kyc_verified ? 'Verified' : 'Unverified' }}</span></td>
                    <td class="small">{{ item.method_label ?? item.method }}</td>
                    <td class="fw-semibold small">{{ item.masked_destination }}</td>
                    <td><span class="badge" :class="statusBadge(item.status)">{{ item.status_label ?? item.status }}</span></td>
                    <td class="text-nowrap small text-muted">{{ formatDate(item.updated_at) }}</td>
                    <td class="text-nowrap text-end"><Link :href="`${endpoint}/${item.id}`" class="btn btn-sm btn-outline-primary">Review</Link></td>
                </tr>
                <tr v-if="!accounts.data.length"><td colspan="7" class="text-center text-muted py-4">No payout accounts match these filters.</td></tr>
            </tbody>
        </table></div>
        <Pagination :links="accounts.links" />
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
</style>
