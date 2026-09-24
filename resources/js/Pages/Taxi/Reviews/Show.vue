<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    portal: { type: String, required: true },
    review: { type: Object, required: true },
    canModerate: { type: Boolean, default: false },
    canReply: { type: Boolean, default: false },
    flagReasons: { type: Array, default: () => [] },
});

const layout = computed(() => ({ admin: AdminLayout, vendor: VendorLayout, account: AccountLayout })[props.portal]);
const base = computed(() => (props.portal === 'account' ? '/account/taxi/reviews' : `/${props.portal}/taxi/reviews`));
const moderateForm = useForm({ action: 'approve', moderation_note: '' });
const replyForm = useForm({ reply: '' });
const flagForm = useForm({ reason: 'spam', note: '' });

const dimensions = computed(() => [
    ['Overall', props.review.overall_rating],
    ['Driver', props.review.driver_rating],
    ['Vehicle', props.review.vehicle_rating],
    ['Service', props.review.service_rating],
    ['Punctuality', props.review.punctuality_rating],
    ['Cleanliness', props.review.cleanliness_rating],
]);

function stars(n) {
    if (n === null || n === undefined) return '—';
    return '★'.repeat(Number(n)) + '☆'.repeat(5 - Number(n));
}
</script>
<template>
<component :is="layout">
<div class="container-fluid py-3">
<Link :href="appUrl(base)">← All reviews</Link>
<component :is="portal === 'account' ? 'h1' : 'h2'" class="my-3">Trip review · {{ review.booking_reference ?? `#${review.id}` }}</component>
<div class="row g-3">
<div class="col-lg-7">
<div class="card p-3 mb-3">
<p class="mb-1"><strong>{{ review.customer }}</strong> · {{ review.submitted_at?.slice(0, 10) }} · Status: <strong>{{ review.status }}</strong></p>
<div v-for="[label, value] in dimensions" :key="label" class="d-flex justify-content-between border-bottom py-1"><span>{{ label }}</span><span :title="value === null ? '' : `${value}/5`">{{ stars(value) }}</span></div>
<p v-if="review.comment" class="mt-3 mb-0">{{ review.comment }}</p>
<p v-else class="mt-3 mb-0 text-muted">No written comment.</p>
</div>
<div v-if="review.vendor_reply" class="card p-3 mb-3"><h5>Vendor reply</h5><p class="mb-0">{{ review.vendor_reply }}</p><p class="small text-muted mb-0">{{ review.vendor_replied_at?.slice(0, 10) }}</p></div>
<div v-if="portal !== 'account'" class="card p-3 mb-3">
<h5>Trip context</h5>
<p class="mb-1">Driver: {{ review.driver ?? '—' }} · Vehicle: {{ review.vehicle ?? '—' }}</p>
<p v-if="portal === 'admin'" class="mb-1">Vendor: {{ review.vendor ?? '—' }} · Booking status: {{ review.booking_status ?? '—' }}</p>
<p v-if="review.flag_reason" class="mb-1">Flagged: {{ review.flag_reason }} ({{ review.flagged_at?.slice(0, 10) }})</p>
<p v-if="portal === 'admin' && review.moderation_note" class="mb-0">Moderation note: {{ review.moderation_note }}</p>
</div>
</div>
<div class="col-lg-5">
<form v-if="canModerate" class="card p-3 mb-3" @submit.prevent="moderateForm.post(appUrl(`${base}/${review.id}/moderate`))">
<h5>Moderate</h5>
<div v-for="(error, key) in moderateForm.errors" :key="key" class="text-danger">{{ error }}</div>
<label class="form-label" for="mod-action">Action</label>
<select id="mod-action" v-model="moderateForm.action" class="form-select mb-2">
<option value="approve">Approve</option><option value="reject">Reject</option><option value="hide">Hide</option><option value="unhide">Unhide (approve)</option>
</select>
<label class="form-label" for="mod-note">Moderation note (internal)</label>
<input id="mod-note" v-model="moderateForm.moderation_note" maxlength="500" class="form-control mb-3" />
<button class="btn btn-svtp" :disabled="moderateForm.processing">Record moderation</button>
</form>
<form v-if="canReply && review.status === 'approved'" class="card p-3 mb-3" @submit.prevent="replyForm.post(appUrl(`${base}/${review.id}/reply`))">
<h5>{{ review.vendor_reply ? 'Update vendor reply' : 'Reply as vendor' }}</h5>
<div v-for="(error, key) in replyForm.errors" :key="key" class="text-danger">{{ error }}</div>
<label class="form-label" for="vendor-reply">Reply (plain text, max 1000)</label>
<textarea id="vendor-reply" v-model="replyForm.reply" required maxlength="1000" rows="3" class="form-control mb-3" />
<button class="btn btn-outline-primary" :disabled="replyForm.processing">Save reply</button>
</form>
<form v-if="portal !== 'account'" class="card p-3 mb-3" @submit.prevent="flagForm.post(appUrl(`${base}/${review.id}/flag`))">
<h5>Flag for admin moderation</h5>
<p class="small text-muted">Flagging never hides the review by itself — an admin decides.</p>
<div v-for="(error, key) in flagForm.errors" :key="key" class="text-danger">{{ error }}</div>
<label class="form-label" for="flag-reason">Reason</label>
<select id="flag-reason" v-model="flagForm.reason" class="form-select mb-2">
<option v-for="r in flagReasons" :key="r" :value="r">{{ r.replaceAll('_', ' ') }}</option>
</select>
<label class="form-label" for="flag-note">Note (optional)</label>
<input id="flag-note" v-model="flagForm.note" maxlength="500" class="form-control mb-3" />
<button class="btn btn-outline-warning" :disabled="flagForm.processing">Flag review</button>
</form>
</div>
</div>
</div>
</component>
</template>
