<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import SystemReportingCharts from '../../Components/SystemReportingCharts.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    analytics: { type: Object, default: () => ({}) },
    analyticsRange: { type: Object, default: () => ({ preset: 'last30' }) },
    ops: { type: Object, default: () => ({}) },
});

const system = computed(() => props.analytics.system ?? {});
const modules = computed(() => system.value.modules ?? {});
const attention = computed(() => (props.ops.needs_attention ?? []).filter((item) => item.count > 0));
const recentActivity = computed(() => system.value.recent_activity ?? props.ops.recent_activity ?? []);
const toursEnabled = computed(() => {
    const module = usePage().props.platformModules?.find((item) => item.key === 'tours');
    return module ? !!module.enabled : true;
});

function setPreset(preset) {
    router.get(appUrl('/admin/dashboard'), { preset }, { preserveScroll: true, preserveState: false });
}

function moneyByCurrency(valueByCurrency = {}) {
    const values = Object.entries(valueByCurrency);

    return values.length
        ? values.map(([currency, value]) => `${currency} ${value.amount}`).join(' · ')
        : '—';
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString() : '—';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h1 class="h3 mb-1">Marketplace Dashboard</h1>
                <p class="text-muted mb-0">Bookings, customers, module activity, and items requiring action.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button v-for="preset in [['today', 'Today'], ['last7', 'Last 7 days'], ['last30', 'Last 30 days'], ['month', 'This month']]" :key="preset[0]" type="button" class="btn btn-sm" :class="analyticsRange.preset === preset[0] ? 'btn-svtp' : 'btn-outline-secondary'" @click="setPreset(preset[0])">{{ preset[1] }}</button>
                <span class="small text-muted">{{ analyticsRange.from ?? '—' }} → {{ analyticsRange.to ?? '—' }}</span>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-2"><div class="card p-3 h-100"><small class="text-muted">Bookings in period</small><strong class="fs-3">{{ system.bookings ?? 0 }}</strong></div></div>
            <div class="col-6 col-xl-2"><div class="card p-3 h-100"><small class="text-muted">Hotel bookings</small><strong class="fs-3">{{ modules.hotels?.bookings ?? 0 }}</strong></div></div>
            <div v-if="toursEnabled" class="col-6 col-xl-2"><div class="card p-3 h-100"><small class="text-muted">Tour bookings</small><strong class="fs-3">{{ modules.tours?.bookings ?? 0 }}</strong></div></div>
            <div class="col-6 col-xl-2"><div class="card p-3 h-100"><small class="text-muted">Taxi bookings</small><strong class="fs-3">{{ modules.taxi?.bookings ?? 0 }}</strong></div></div>
            <div class="col-6 col-xl-2"><div class="card p-3 h-100"><small class="text-muted">New customers</small><strong class="fs-3">{{ system.new_customers ?? 0 }}</strong></div></div>
            <div class="col-6 col-xl-2"><div class="card p-3 h-100"><small class="text-muted">Needs attention</small><strong class="fs-3">{{ attention.length }}</strong></div></div>
        </div>

        <div v-if="ops.finance_visible" class="card p-3 mb-4">
            <div class="d-flex flex-wrap justify-content-between gap-2"><div><strong>Booking value by currency</strong><span class="small text-muted ms-2">Non-cancelled records; currencies are kept separate.</span></div><div class="small text-muted">No exchange-rate conversion applied</div></div>
            <div class="row g-3 mt-1"><div v-for="(item, key) in modules" :key="key" class="col-md-4"><span class="small text-muted">{{ item.label }}</span><div class="fw-semibold">{{ moneyByCurrency(item.value_by_currency) }}</div></div></div>
        </div>

        <SystemReportingCharts :system="system" />

        <div class="row g-3 mt-1">
            <div class="col-lg-7"><div class="card h-100"><div class="card-header fw-semibold">Module performance</div><div class="table-responsive"><table class="table align-middle mb-0 small"><thead><tr><th>Module</th><th>Bookings</th><th>Booking value</th><th></th></tr></thead><tbody><tr v-for="(item, key) in modules" :key="key"><td>{{ item.label }}</td><td>{{ item.bookings }}</td><td>{{ moneyByCurrency(item.value_by_currency) }}</td><td class="text-end"><Link v-if="key === 'hotels'" :href="appUrl('/admin/hotel/bookings')" class="btn btn-sm btn-outline-primary">Open</Link><Link v-else-if="key === 'taxi'" :href="appUrl('/admin/taxi/bookings')" class="btn btn-sm btn-outline-primary">Open</Link><Link v-else-if="toursEnabled" :href="appUrl('/admin/tour/bookings')" class="btn btn-sm btn-outline-primary">Open</Link></td></tr></tbody></table></div></div></div>
            <div class="col-lg-5"><div class="card h-100"><div class="card-header fw-semibold">Needs attention</div><div v-if="attention.length" class="list-group list-group-flush"><Link v-for="item in attention" :key="item.key" :href="appUrl(item.url)" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"><span>{{ item.label }}</span><span class="badge bg-warning text-dark">{{ item.count }}</span></Link></div><div v-else class="card-body text-muted small">Nothing needs attention right now.</div></div></div>
        </div>

        <div class="card mt-3"><div class="card-header fw-semibold">Recent booking activity</div><div v-if="recentActivity.length" class="table-responsive"><table class="table align-middle mb-0 small"><thead><tr><th>When</th><th>Module</th><th>Reference</th><th>Customer</th><th>Status</th></tr></thead><tbody><tr v-for="activity in recentActivity" :key="`${activity.module}-${activity.reference}`"><td class="text-nowrap">{{ formatDate(activity.created_at) }}</td><td>{{ activity.module }}</td><td>{{ activity.reference }}</td><td>{{ activity.customer || 'Guest' }}</td><td><span class="badge bg-light text-dark">{{ activity.status }}</span></td></tr></tbody></table></div><div v-else class="card-body text-muted small">No booking activity in this period.</div></div>
    </AdminLayout>
</template>
