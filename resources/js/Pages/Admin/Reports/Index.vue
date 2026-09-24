<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import SystemReportingCharts from '../../../Components/SystemReportingCharts.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    tab: { type: String, default: 'overview' },
    range: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    rows: { type: Array, default: () => [] },
    pagination: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'bookings', label: 'Bookings' },
    { key: 'hotels', label: 'Hotels' },
    { key: 'tours', label: 'Tours' },
    { key: 'taxi', label: 'Taxi' },
    { key: 'customers', label: 'Customers' },
    { key: 'vendors', label: 'Vendors' },
    { key: 'payments', label: 'Payments' },
    { key: 'leads', label: 'Leads' },
    { key: 'commission', label: 'Commission' },
];

const filters = useForm({
    tab: props.tab,
    preset: props.filters.preset ?? 'last30',
    from: props.range.from ? String(props.range.from).slice(0, 10) : '',
    to: props.range.to ? String(props.range.to).slice(0, 10) : '',
    module: props.filters.module ?? '',
    status: props.filters.status ?? '',
    payment_status: props.filters.payment_status ?? '',
    search: props.filters.search ?? '',
    vendor_id: props.filters.vendor_id ?? '',
});

const isTableTab = computed(() => ['bookings', 'hotels', 'tours', 'taxi', 'customers', 'payments', 'leads', 'vendors', 'commission'].includes(filters.tab));
const isSystemBookingTab = computed(() => ['bookings', 'hotels', 'tours', 'taxi'].includes(filters.tab));
const systemSummary = computed(() => props.summary.system ?? {});

function apply(tab = null) {
    if (tab) {
        filters.tab = tab;
        if (tab !== 'bookings') filters.module = '';
    }
    filters.get(appUrl('/admin/reports'), { preserveState: true, preserveScroll: true });
}

function resetFilters() {
    window.location.href = appUrl(`/admin/reports?tab=${filters.tab}`);
}

function exportCsv() {
    const query = new URLSearchParams(Object.fromEntries(Object.entries(filters.data()).filter(([, value]) => value !== '')));
    window.location.href = `${appUrl('/admin/reports/export')}?${query.toString()}`;
}

function moneyByCurrency(valueByCurrency = {}) {
    const values = Object.entries(valueByCurrency);

    return values.length ? values.map(([currency, value]) => `${currency} ${value.amount}`).join(' · ') : '—';
}

function heading(value) {
    return value.replace(/_/g, ' ');
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div><h1 class="h3 mb-1">Reports</h1><p class="text-muted mb-0">System-wide operational reporting with source statuses and currencies preserved.</p></div>
            <button v-if="isTableTab && filters.tab !== 'customers'" type="button" class="btn btn-sm btn-outline-primary" @click="exportCsv">Export CSV</button>
        </div>

        <div class="report-tabs mb-3" role="tablist" aria-label="Report sections">
            <button v-for="item in tabs" :key="item.key" type="button" class="btn btn-sm" :class="filters.tab === item.key ? 'btn-svtp' : 'btn-outline-secondary'" @click="apply(item.key)">{{ item.label }}</button>
        </div>

        <div class="card p-3 mb-3">
            <form class="row g-2 align-items-end" @submit.prevent="apply()">
                <div class="col-6 col-md-2"><label for="report-preset" class="form-label small">Range</label><select id="report-preset" v-model="filters.preset" class="form-select form-select-sm"><option value="today">Today</option><option value="last7">Last 7 days</option><option value="last30">Last 30 days</option><option value="month">This month</option><option value="custom">Custom</option><option value="all">All time</option></select></div>
                <div class="col-6 col-md-2"><label for="report-from" class="form-label small">From</label><input id="report-from" v-model="filters.from" type="date" class="form-control form-control-sm"></div>
                <div class="col-6 col-md-2"><label for="report-to" class="form-label small">To</label><input id="report-to" v-model="filters.to" type="date" class="form-control form-control-sm"></div>
                <div v-if="filters.tab === 'bookings'" class="col-6 col-md-2"><label for="report-module" class="form-label small">Module</label><select id="report-module" v-model="filters.module" class="form-select form-select-sm"><option value="">All modules</option><option value="hotels">Hotels</option><option value="tours">Tours</option><option value="taxi">Taxi</option></select></div>
                <div v-if="isTableTab" class="col-6 col-md-2"><label for="report-status" class="form-label small">Status</label><input id="report-status" v-model="filters.status" class="form-control form-control-sm" placeholder="optional"></div>
                <div v-if="isSystemBookingTab" class="col-6 col-md-2"><label for="report-payment-status" class="form-label small">Payment status</label><input id="report-payment-status" v-model="filters.payment_status" class="form-control form-control-sm" placeholder="optional"></div>
                <div v-if="isTableTab" class="col-6 col-md-2"><label for="report-search" class="form-label small">Search</label><input id="report-search" v-model="filters.search" class="form-control form-control-sm" placeholder="Reference or name"></div>
                <div v-if="isSystemBookingTab || filters.tab === 'vendors'" class="col-6 col-md-2"><label for="report-vendor" class="form-label small">Vendor</label><select id="report-vendor" v-model="filters.vendor_id" class="form-select form-select-sm"><option value="">All vendors</option><option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option></select></div>
                <div class="col-12 col-md-auto d-flex gap-2"><button class="btn btn-sm btn-svtp">Apply</button><button type="button" class="btn btn-sm btn-outline-secondary" @click="resetFilters">Reset</button></div>
            </form>
        </div>

        <template v-if="filters.tab === 'overview'">
            <div class="row g-3 mb-3"><div v-for="(item, key) in systemSummary.modules" :key="key" class="col-md-4"><div class="card p-3 h-100"><div class="small text-muted">{{ item.label }} bookings</div><strong class="fs-3">{{ item.bookings }}</strong><div class="small mt-2">{{ moneyByCurrency(item.value_by_currency) }}</div></div></div></div>
            <SystemReportingCharts :system="systemSummary" />
        </template>

        <template v-else>
            <div v-if="rows.length" class="table-responsive"><table class="table align-middle small"><thead><tr><th v-for="(value, key) in rows[0]" :key="key">{{ heading(key) }}</th></tr></thead><tbody><tr v-for="(row, index) in rows" :key="`${row.reference ?? row.customer ?? index}-${index}`"><td v-for="(value, key) in row" :key="key">{{ value ?? '—' }}</td></tr></tbody></table></div>
            <div v-else class="card p-4 text-center text-muted">No records match this report and date range.</div>
            <Pagination v-if="pagination" :links="pagination.links" />
        </template>

        <p v-if="filters.tab === 'vendors'" class="small text-muted mt-3">Vendor earnings currently reflect the existing tour vendor ledger. Hotel and taxi vendor marketplace settlement is not inferred here.</p>
    </AdminLayout>
</template>

<style scoped>
.report-tabs { display: flex; flex-wrap: wrap; gap: .45rem; }
.report-tabs .btn { white-space: nowrap; }
</style>
