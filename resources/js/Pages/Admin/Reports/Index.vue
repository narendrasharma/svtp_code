<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    tab: { type: String, default: 'bookings' },
    range: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    rows: { type: Array, default: () => [] },
    vendors: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'bookings', label: 'Bookings' },
    { key: 'payments', label: 'Payments' },
    { key: 'leads', label: 'Leads' },
    { key: 'vendors', label: 'Vendor earnings' },
    { key: 'commission', label: 'Commission' },
];

const filters = useForm({
    tab: props.tab,
    preset: props.filters.preset ?? 'last30',
    from: props.range.from ? String(props.range.from).slice(0, 10) : '',
    to: props.range.to ? String(props.range.to).slice(0, 10) : '',
    status: props.filters.status ?? '',
    source: props.filters.source ?? '',
    vendor_id: props.filters.vendor_id ?? '',
});

function apply(tab = null) {
    if (tab) filters.tab = tab;
    filters.get(appUrl('/admin/reports'), { preserveState: true, preserveScroll: true });
}

function exportCsv() {
    const q = new URLSearchParams({
        tab: filters.tab,
        preset: filters.preset,
        from: filters.from,
        to: filters.to,
        status: filters.status,
        source: filters.source,
        vendor_id: filters.vendor_id,
    });
    window.location.href = appUrl('/admin/reports/export') + '?' + q.toString();
}
</script>

<template>
    <AdminLayout>
        <h2 class="mt-2 mb-1">Reports</h2>
        <p class="text-muted mb-3">Narrow operational reports with server-side filters. Exports are CSV and contain figures only — no KYC, bank or credential data.</p>

        <ul class="nav nav-tabs mb-3">
            <li v-for="t in tabs" :key="t.key" class="nav-item">
                <button class="nav-link" :class="{ active: filters.tab === t.key }" @click="apply(t.key)">{{ t.label }}</button>
            </li>
        </ul>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card p-3"><div class="small text-muted">Gross Booking Value</div><strong>₹{{ summary.gross_booking_value ?? '—' }}</strong></div>
            </div>
            <div class="col-md-3">
                <div class="card p-3"><div class="small text-muted">Platform Commission</div><strong>₹{{ summary.platform_commission ?? '—' }}</strong></div>
            </div>
            <div class="col-md-3">
                <div class="card p-3"><div class="small text-muted">Vendor Earnings</div><strong>₹{{ summary.vendor_earnings ?? '—' }}</strong></div>
            </div>
            <div class="col-md-3">
                <div class="card p-3"><div class="small text-muted">Refunded</div><strong>₹{{ summary.refunded_amount ?? '—' }}</strong></div>
            </div>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="apply()">
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label for="rep-preset" class="form-label small">Range</label>
                    <select id="rep-preset" v-model="filters.preset" class="form-select form-select-sm">
                        <option value="today">Today</option>
                        <option value="last7">Last 7 days</option>
                        <option value="last30">Last 30 days</option>
                        <option value="month">This month</option>
                        <option value="custom">Custom</option>
                        <option value="all">All time</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="rep-from" class="form-label small">From</label>
                    <input id="rep-from" v-model="filters.from" type="date" class="form-control form-control-sm" />
                </div>
                <div class="col-md-2">
                    <label for="rep-to" class="form-label small">To</label>
                    <input id="rep-to" v-model="filters.to" type="date" class="form-control form-control-sm" />
                </div>
                <div class="col-md-2">
                    <label for="rep-status" class="form-label small">Status</label>
                    <input id="rep-status" v-model="filters.status" class="form-control form-control-sm" placeholder="optional" />
                </div>
                <div class="col-md-2">
                    <label for="rep-vendor" class="form-label small">Vendor</label>
                    <select id="rep-vendor" v-model="filters.vendor_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.business_name }}</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-sm btn-svtp flex-fill">Go</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" @click="exportCsv">CSV</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle small">
                <thead><tr><th v-for="(v, k) in (rows[0] ?? {})" :key="k">{{ k.replace(/_/g, ' ') }}</th></tr></thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="i">
                        <td v-for="(v, k) in row" :key="k">{{ v ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!rows.length" class="text-muted">No rows for this filter. Showing up to {{ tab === 'vendors' ? 200 : 50 }} rows — refine filters or export CSV (up to 2000 rows).</p>
    </AdminLayout>
</template>
