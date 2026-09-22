<script setup>
import DriverLayout from '../../../../Layouts/DriverLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
defineProps({ summary: { type: Object, required: true }, feedback: { type: Object, required: true } });
function stars(n) {
    if (n === null || n === undefined) return '—';
    return '★'.repeat(Number(n)) + '☆'.repeat(5 - Number(n));
}
</script>
<template><DriverLayout><div class="container-fluid py-3">
<h2 class="my-3">My ratings &amp; feedback</h2>
<div class="card p-3 mb-3"><div class="row g-3 text-center">
<div class="col-6 col-md-2"><span class="small text-muted">Approved reviews</span><strong class="d-block fs-4">{{ summary.count }}</strong></div>
<div class="col-6 col-md-2"><span class="small text-muted">Overall</span><strong class="d-block fs-4">{{ summary.overall_avg ?? '—' }}</strong></div>
<div class="col-6 col-md-2"><span class="small text-muted">Driver</span><strong class="d-block fs-4">{{ summary.driver_avg ?? '—' }}</strong></div>
<div class="col-6 col-md-2"><span class="small text-muted">Service</span><strong class="d-block fs-4">{{ summary.service_avg ?? '—' }}</strong></div>
<div class="col-6 col-md-2"><span class="small text-muted">Punctuality</span><strong class="d-block fs-4">{{ summary.punctuality_avg ?? '—' }}</strong></div>
<div class="col-6 col-md-2"><span class="small text-muted">Cleanliness</span><strong class="d-block fs-4">{{ summary.cleanliness_avg ?? '—' }}</strong></div>
</div>
<p class="small text-muted mb-0 mt-2">Distribution (5→1): {{ [5,4,3,2,1].map(s => `${s}:${summary.distribution?.[s] ?? 0}`).join(' · ') }}</p>
</div>
<h4>Recent customer feedback</h4>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Trip</th><th>From</th><th>Rating</th><th>Comment</th></tr></thead><tbody>
<tr v-for="row in feedback.data" :key="row.id"><td>{{ row.booking_reference }}<br /><span class="small text-muted">{{ row.pickup_at?.slice(0, 10) }}</span></td><td>{{ row.customer }}</td><td :title="`${row.overall_rating}/5`">{{ stars(row.overall_rating) }}</td><td>{{ row.comment ?? '—' }}<p v-if="row.vendor_reply" class="small text-muted mb-0">Vendor reply: {{ row.vendor_reply }}</p></td></tr>
<tr v-if="!feedback.data.length"><td colspan="4">No approved feedback yet.</td></tr>
</tbody></table></div><Pagination :links="feedback.links" /></div>
</div></DriverLayout></template>
