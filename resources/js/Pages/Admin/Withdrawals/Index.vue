<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    withdrawals: Object,
    filters: { type: Object, default: () => ({}) },
    vendors: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    pendingCount: { type: Number, default: 0 },
});

const endpoint = appUrl('/admin/withdrawals');
const filters = useForm({
    status: props.filters.status ?? '',
    vendor: props.filters.vendor ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
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
        approved: 'bg-info text-dark',
        rejected: 'bg-secondary',
        paid: 'bg-success',
        cancelled: 'bg-secondary',
    }[status] ?? 'bg-secondary';
}
function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN') : '—';
}
function money(value) {
    return `₹${Number(value ?? 0).toFixed(2)}`;
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Vendor Withdrawals</h2>
                <p class="text-muted mb-0">Manual payout review. Funds are held at request time; approve keeps the hold, reject releases it, paid settles it.</p>
            </div>
            <span v-if="pendingCount" class="badge bg-warning text-dark fs-6">{{ pendingCount }} pending</span>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label for="filter-status" class="form-label small">Status</label><select id="filter-status" v-model="filters.status" class="form-select form-select-sm"><option value="">All statuses</option><option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                <div class="col-md-3"><label for="filter-vendor" class="form-label small">Vendor</label><select id="filter-vendor" v-model="filters.vendor" class="form-select form-select-sm"><option value="">All vendors</option><option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option></select></div>
                <div class="col-md-4"><div class="d-flex gap-2">
                    <div class="flex-fill"><label for="filter-from" class="form-label small">Requested from</label><input id="filter-from" v-model="filters.date_from" type="date" class="form-control form-control-sm" /></div>
                    <div class="flex-fill"><label for="filter-to" class="form-label small">Requested to</label><input id="filter-to" v-model="filters.date_to" type="date" class="form-control form-control-sm" /></div>
                </div></div>
                <div class="col-12 d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-svtp" :disabled="filters.processing">Apply Filters</button>
                    <button v-if="hasActiveFilters" type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                    <span class="text-muted small ms-auto align-self-center">{{ withdrawals.total }} request(s)</span>
                </div>
            </div>
        </form>

        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>ID</th><th>Vendor</th><th>KYC</th><th>Amount</th><th>Status</th><th>Requested</th><th></th></tr></thead>
            <tbody>
                <tr v-for="item in withdrawals.data" :key="item.id">
                    <td><Link :href="`${endpoint}/${item.id}`" class="text-decoration-none fw-semibold">#{{ item.id }}</Link></td>
                    <td>{{ item.vendor_profile?.business_name ?? '—' }}</td>
                    <td><span class="badge" :class="item.kyc_verified ? 'bg-success' : 'bg-warning text-dark'">{{ item.kyc_verified ? 'Verified' : 'Unverified' }}</span></td>
                    <td class="text-nowrap">{{ money(item.amount) }}</td>
                    <td><span class="badge" :class="statusBadge(item.status)">{{ item.status }}</span></td>
                    <td class="text-nowrap small text-muted">{{ formatDate(item.requested_at) }}</td>
                    <td class="text-nowrap text-end"><Link :href="`${endpoint}/${item.id}`" class="btn btn-sm btn-outline-primary">Review</Link></td>
                </tr>
                <tr v-if="!withdrawals.data.length"><td colspan="7" class="text-center text-muted py-4">No withdrawal requests match these filters.</td></tr>
            </tbody>
        </table></div>
        <Pagination :links="withdrawals.links" />
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
</style>
