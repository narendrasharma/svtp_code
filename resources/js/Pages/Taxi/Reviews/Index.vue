<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AccountNav from '../../../Components/AccountNav.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    portal: { type: String, required: true },
    reviews: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    vendors: { type: Array, default: () => [] },
    summary: { type: Object, default: null },
    canModerate: { type: Boolean, default: false },
    lowRatingThreshold: { type: Number, default: 2 },
});

const layout = computed(() => ({ admin: AdminLayout, vendor: VendorLayout, account: AppLayout })[props.portal]);
const base = computed(() => (props.portal === 'account' ? '/account/taxi/reviews' : `/${props.portal}/taxi/reviews`));

function filterHref(patch) {
    const params = new URLSearchParams({ ...props.filters, ...patch });
    for (const [k, v] of [...params]) if (!v) params.delete(k);
    return appUrl(`${base.value}?${params.toString()}`);
}

function stars(n) {
    return '★'.repeat(Number(n) || 0) + '☆'.repeat(5 - (Number(n) || 0));
}
</script>
<template>
<component :is="layout">
<div v-if="portal === 'account'" class="container py-4">
<AccountNav active="taxi" />
<h2>My trip reviews</h2>
<div class="card table-responsive"><table class="table mb-0"><thead><tr><th>Trip</th><th>Rating</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
<tr v-for="row in reviews.data" :key="row.id"><td><Link :href="appUrl(`${base}/${row.id}`)">{{ row.booking_reference }}</Link></td><td :title="`${row.overall_rating}/5`">{{ stars(row.overall_rating) }}</td><td>{{ row.status }}</td><td>{{ row.submitted_at?.slice(0, 10) }}</td></tr>
<tr v-if="!reviews.data.length"><td colspan="4">No reviews yet. Completed trips can be rated from your bookings.</td></tr>
</tbody></table><Pagination :links="reviews.links" /></div>
</div>
<div v-else class="container-fluid py-3">
<div class="d-flex flex-wrap align-items-center gap-2 my-3"><h2 class="me-auto mb-0">Taxi reviews</h2></div>
<div v-if="summary" class="card p-3 mb-3"><div class="row g-3 text-center">
<div class="col-6 col-lg-2"><span class="small text-muted">Approved reviews</span><strong class="d-block fs-4">{{ summary.count }}</strong></div>
<div class="col-6 col-lg-2"><span class="small text-muted">Average overall</span><strong class="d-block fs-4">{{ summary.overall_avg ?? '—' }}</strong></div>
<div class="col-6 col-lg-4"><span class="small text-muted">Distribution (5→1)</span><strong class="d-block">{{ [5,4,3,2,1].map(s => `${s}:${summary.distribution?.[s] ?? 0}`).join(' · ') }}</strong></div>
<div class="col-6 col-lg-2"><span class="small text-muted">Low-rated (≤{{ lowRatingThreshold }})</span><strong class="d-block fs-4">{{ summary.low_rated_count }}</strong></div>
<div v-if="summary.driver_avg !== undefined" class="col-6 col-lg-2"><span class="small text-muted">Driver average</span><strong class="d-block fs-4">{{ summary.driver_avg ?? '—' }}</strong></div>
</div></div>
<div class="card p-3 mb-3 d-flex flex-wrap gap-2">
<span class="small text-muted w-100">Filters:</span>
<Link :href="filterHref({ status: '' })" class="btn btn-sm btn-outline-secondary">All statuses</Link>
<Link v-for="s in statuses" :key="s" :href="filterHref({ status: s })" class="btn btn-sm btn-outline-secondary">{{ s }}</Link>
<Link v-for="r in [5,4,3,2,1]" :key="String(r)" :href="filterHref({ rating: r })" class="btn btn-sm btn-outline-secondary">{{ r }}★</Link>
<Link :href="filterHref({ rating: '' })" class="btn btn-sm btn-outline-secondary">Any rating</Link>
</div>
<div class="card table-responsive"><table class="table mb-0"><thead><tr><th>Trip</th><th>Customer</th><th v-if="portal === 'admin'">Vendor</th><th>Driver</th><th>Rating</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
<tr v-for="row in reviews.data" :key="row.id">
<td><Link :href="appUrl(`${base}/${row.id}`)">{{ row.booking_reference }}</Link><span v-if="row.low" class="badge bg-warning ms-1">low</span><span v-if="row.flagged" class="badge bg-danger ms-1">flagged</span></td>
<td>{{ row.customer }}</td><td v-if="portal === 'admin'">{{ row.vendor }}</td><td>{{ row.driver ?? '—' }}</td>
<td :title="`${row.overall_rating}/5`">{{ stars(row.overall_rating) }}</td><td>{{ row.status }}</td><td>{{ row.submitted_at?.slice(0, 10) }}</td>
</tr>
<tr v-if="!reviews.data.length"><td colspan="7">No reviews match these filters.</td></tr>
</tbody></table><Pagination :links="reviews.links" /></div>
</div>
</component>
</template>
