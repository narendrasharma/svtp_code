<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    earnings: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    vendors: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/taxi/earnings');

const form = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    vendor_id: props.filters.vendor_id ?? '',
    currency: props.filters.currency ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

function applyFilters() {
    form.get(endpoint, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    form.reset();
    applyFilters();
}

function money(row) {
    return `${row.currency} ${Number(row.net_earning).toFixed(2)}`;
}

function statusBadge(status) {
    return {
        pending: 'bg-secondary',
        payable: 'bg-info text-dark',
        partially_paid: 'bg-warning text-dark',
        paid: 'bg-success',
        void: 'bg-dark',
    }[status] ?? 'bg-secondary';
}
</script>

<template>
    <AdminLayout>
        <div class="mb-3 d-flex align-items-center gap-2">
            <div>
                <h2 class="mt-2 mb-1">Driver Earnings</h2>
                <p class="text-muted mb-0">Immutable per-trip earnings. Rate changes never rewrite history.</p>
            </div>
            <div class="ms-auto d-flex gap-2">
                <Link :href="appUrl('/admin/taxi/plans')" class="btn btn-sm btn-outline-secondary">Compensation plans</Link>
                <Link :href="appUrl('/admin/taxi/payouts')" class="btn btn-sm btn-outline-secondary">Payouts</Link>
            </div>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Search</label>
                    <input v-model="form.search" class="form-control form-control-sm" placeholder="Earning no, trip ref, driver" />
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Status</label>
                    <select v-model="form.status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Vendor</label>
                    <select v-model="form.vendor_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.business_name }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Currency</label>
                    <input v-model="form.currency" class="form-control form-control-sm" placeholder="INR" maxlength="3" />
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-sm btn-svtp" :disabled="form.processing">Apply</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                    <span class="ms-auto text-muted small align-self-center">{{ earnings.total }} row(s)</span>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Earning</th>
                            <th>Driver</th>
                            <th>Trip</th>
                            <th class="text-end">Gross</th>
                            <th class="text-end">Net</th>
                            <th class="text-end">Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in earnings.data" :key="row.id">
                            <td>
                                <Link :href="`${endpoint}/${row.id}`" class="fw-semibold">{{ row.earning_number }}</Link>
                                <div class="small text-muted">{{ row.calculation_type }} · {{ row.earned_at?.slice(0, 10) }}</div>
                            </td>
                            <td>
                                <div>{{ row.driver?.first_name }} {{ row.driver?.last_name }}</div>
                                <div class="small text-muted">{{ row.vendor_profile?.business_name ?? 'Platform' }}</div>
                            </td>
                            <td class="small">{{ row.booking?.reference ?? '—' }}</td>
                            <td class="text-end">{{ row.currency }} {{ Number(row.gross_earning).toFixed(2) }}</td>
                            <td class="text-end fw-semibold">{{ money(row) }}</td>
                            <td class="text-end">{{ row.currency }} {{ Number(row.paid_amount).toFixed(2) }}</td>
                            <td><span class="badge" :class="statusBadge(row.status)">{{ row.status }}</span></td>
                        </tr>
                        <tr v-if="!earnings.data.length"><td colspan="7" class="text-center text-muted py-4">No earnings yet. Complete a trip to generate one.</td></tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="earnings.links" class="px-3 py-2" />
        </div>
    </AdminLayout>
</template>
