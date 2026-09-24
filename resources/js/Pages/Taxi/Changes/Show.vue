<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import LocalDateTimeField from '../../../Components/Public/Search/LocalDateTimeField.vue';
import { appUrl } from '../../../appUrl';
const props = defineProps({ portal: String, booking: Object, summary: Object, cancellation: Object, refunds: Array, reschedules: Array, canCancel: Boolean, canNoShow: Boolean, canReschedule: Boolean, canRefund: Boolean, reasons: Array, paymentMethods: Array });
const layout = computed(() => ({ admin: AdminLayout, vendor: VendorLayout, account: AppLayout })[props.portal]);
const endpoint = computed(() => appUrl(`/${props.portal}/taxi/changes/${props.booking.id}`));
const quote = ref(null), rescheduleQuote = ref(null), previewError = ref(''), loading = ref(false);
const cancellationForm = useForm({ reason_code: 'customer_request', reason: '', confirmed: false });
const rescheduleForm = useForm({ pickup_at: '', return_at: '', reason: '', confirmed: false });
const refundForm = useForm({ amount: '', reason: '', request_key: crypto.randomUUID() });
const processForm = useForm({ method: 'bank_transfer', reference: '', confirmed: false });
const selectedRefund = ref('');
watch(() => cancellationForm.reason_code, () => { quote.value = null; cancellationForm.confirmed = false; });
watch(() => [rescheduleForm.pickup_at, rescheduleForm.return_at], () => { rescheduleQuote.value = null; rescheduleForm.confirmed = false; });
async function preview(kind) {
    loading.value = true; previewError.value = '';
    try {
        if (kind === 'cancel') quote.value = (await axios.get(`${endpoint.value}/quote`, { params: { no_show: cancellationForm.reason_code === 'no_show' ? 1 : 0 } })).data;
        else rescheduleQuote.value = (await axios.post(`${endpoint.value}/reschedule-quote`, rescheduleForm.data())).data;
    } catch (error) { previewError.value = Object.values(error.response?.data?.errors ?? {}).flat().join(' ') || 'Preview unavailable. Refresh and try again.'; }
    finally { loading.value = false; }
}
function reserveRefund() {
    refundForm.post(`${endpoint.value}/refunds`, { onSuccess: () => { refundForm.reset(); refundForm.request_key = crypto.randomUUID(); } });
}
</script>
<template><component :is="layout"><div class="container-fluid py-3">
    <Link :href="appUrl(portal === 'account' ? '/account/taxi/bookings' : `/${portal}/taxi/bookings/${booking.id}`)">← Booking</Link>
    <div class="d-flex flex-wrap align-items-center gap-3 my-3"><h2 class="me-auto mb-0">{{ booking.reference }} — Booking changes</h2><Link v-if="portal !== 'account'" :href="appUrl(`/${portal}/taxi/cancellation-policies`)" class="btn btn-outline-secondary">Cancellation policies</Link></div>
    <p>Status: <strong>{{ booking.status }}</strong> · Pickup: {{ booking.pickup_at }} · {{ booking.currency }}</p>
    <div class="card p-3 mb-3"><div class="row g-3"><div v-for="item in [['total','Booking total'],['paid','Paid'],['refunded','Refunded'],['net_paid','Net paid'],['refundable_remaining','Refundable remaining'],['due','Amount due']]" :key="item[0]" class="col-6 col-lg-2"><span class="small text-muted">{{ item[1] }}</span><strong class="d-block">{{ booking.currency }} {{ Number(summary[item[0]] ?? 0).toFixed(2) }}</strong></div></div></div>
    <div v-if="previewError" class="alert alert-danger">{{ previewError }}</div>
    <div class="row g-3">
      <div v-if="canCancel || canNoShow" class="col-lg-6"><form class="card p-3 h-100" @submit.prevent="cancellationForm.post(`${endpoint}/cancel`)"><h4>Cancel booking / no-show</h4>
        <div v-for="(error,key) in cancellationForm.errors" :key="key" class="text-danger">{{ error }}</div>
        <label for="cancel-code" class="form-label">Reason</label><select id="cancel-code" v-model="cancellationForm.reason_code" class="form-select mb-2"><option v-for="reason in reasons.filter(r => r !== 'no_show' || canNoShow)" :key="reason" :value="reason">{{ reason.replaceAll('_',' ') }}</option></select>
        <label for="cancel-note" class="form-label">Explanation</label><textarea id="cancel-note" v-model="cancellationForm.reason" maxlength="500" class="form-control mb-3" />
        <button type="button" class="btn btn-outline-secondary" :disabled="loading" @click="preview('cancel')">Preview fee and refund</button>
        <div v-if="quote" class="mt-3"><p>Cancellation fee: <strong>{{ quote.currency }} {{ quote.cancellation_fee }}</strong><br />Estimated refund: <strong>{{ quote.currency }} {{ quote.refundable_amount }}</strong><br /><span v-if="quote.policy">Policy: {{ quote.policy.name }} · </span>{{ quote.cutoff_state }}</p><p class="small text-muted">The final amount is recalculated when you confirm. Refund settlement is a separate action.</p><label class="d-block mb-3"><input v-model="cancellationForm.confirmed" type="checkbox" /> I confirm this cancellation and its fee.</label><button class="btn btn-danger" :disabled="!cancellationForm.confirmed || cancellationForm.processing">Confirm cancellation</button></div>
      </form></div>
      <div v-if="canReschedule" class="col-lg-6"><form class="card p-3 h-100" @submit.prevent="rescheduleForm.post(`${endpoint}/reschedule`)"><h4>Reschedule</h4><p class="small">The agreed price stays unchanged. Unavailable assignments will be released and pending offers cancelled.</p><div v-for="(error,key) in rescheduleForm.errors" :key="key" class="text-danger">{{ error }}</div>
        <template v-if="portal === 'account'"><LocalDateTimeField id="new-pickup" v-model="rescheduleForm.pickup_at" date-label="New pickup date" time-label="New pickup time" required /><LocalDateTimeField id="new-return" v-model="rescheduleForm.return_at" date-label="New return date (where applicable)" time-label="New return time (where applicable)" /></template><template v-else><label for="new-pickup" class="form-label">New pickup</label><input id="new-pickup" v-model="rescheduleForm.pickup_at" type="datetime-local" required class="form-control mb-2" /><label for="new-return" class="form-label">New return (where applicable)</label><input id="new-return" v-model="rescheduleForm.return_at" type="datetime-local" class="form-control mb-2" /></template><label for="reschedule-reason" class="form-label">Reason</label><input id="reschedule-reason" v-model="rescheduleForm.reason" required maxlength="500" class="form-control mb-3" /><button type="button" class="btn btn-outline-secondary" :disabled="loading" @click="preview('reschedule')">Preview reschedule</button>
        <div v-if="rescheduleQuote" class="mt-3"><p>{{ rescheduleQuote.pricing_impact }}<br />Assignment: {{ rescheduleQuote.assignment_impact }}</p><label class="d-block mb-3"><input v-model="rescheduleForm.confirmed" type="checkbox" /> Confirm the new pickup time.</label><button class="btn btn-svtp" :disabled="!rescheduleForm.confirmed || rescheduleForm.processing">Confirm reschedule</button></div>
      </form></div>
    </div>
    <div v-if="cancellation" class="card p-3 mt-3"><h4>Cancellation record</h4><p>{{ cancellation.status }} · {{ cancellation.cancelled_at }}<br />Fee: {{ cancellation.currency }} {{ cancellation.cancellation_fee }} · Refund entitlement: {{ cancellation.currency }} {{ cancellation.refundable_amount }}</p><p v-if="cancellation.calculation_snapshot?.driver_earning_review_required" class="alert alert-warning">An existing driver earning requires manual review. Earnings and paid payouts have not been reversed.</p><details v-if="portal !== 'account'"><summary>Original calculation</summary><pre class="small text-wrap">{{ JSON.stringify(cancellation.calculation_snapshot, null, 2) }}</pre></details></div>
    <div class="card p-3 mt-3"><h4>Refund history</h4><p class="small text-muted">Processed means an operator recorded an off-platform refund. This page does not transfer money.</p><div class="table-responsive"><table class="table"><thead><tr><th>Refund</th><th>Amount</th><th>Status</th><th>Date / Reference</th></tr></thead><tbody><tr v-for="refund in refunds" :key="refund.refund_number"><td>{{ refund.refund_number }}<div v-for="item in refund.items" :key="item.id" class="small text-muted">{{ item.payment_reference }}: {{ item.amount }}</div></td><td>{{ refund.currency }} {{ refund.amount }}</td><td>{{ refund.status }}</td><td>{{ refund.refunded_at ?? '—' }} / {{ refund.reference ?? '—' }}</td></tr><tr v-if="!refunds.length"><td colspan="4">No refunds.</td></tr></tbody></table></div>
      <div v-if="canRefund" class="row g-3"><form class="col-md-6" @submit.prevent="reserveRefund"><h5>Reserve refund</h5><div v-for="(error,key) in refundForm.errors" :key="key" class="text-danger">{{ error }}</div><label for="refund-amount" class="form-label">Amount</label><input id="refund-amount" v-model="refundForm.amount" type="number" step="0.01" min="0.01" :max="summary.refundable_remaining" required class="form-control mb-2" /><label for="refund-reason" class="form-label">Reason</label><input id="refund-reason" v-model="refundForm.reason" required maxlength="500" class="form-control mb-3" /><button class="btn btn-outline-primary" :disabled="refundForm.processing || summary.refundable_remaining <= 0">Reserve refund amount</button></form>
      <form class="col-md-6" @submit.prevent="processForm.post(`${endpoint}/refunds/${selectedRefund}/process`)"><h5>Record completed refund</h5><div v-for="(error,key) in processForm.errors" :key="key" class="text-danger">{{ error }}</div><label for="refund-selection" class="form-label">Pending refund</label><select id="refund-selection" v-model="selectedRefund" required class="form-select mb-2"><option value="">Choose refund</option><option v-for="refund in refunds.filter(r => r.status === 'pending')" :key="refund.id" :value="refund.id">{{ refund.refund_number }} — {{ refund.amount }}</option></select><label for="refund-method" class="form-label">Method</label><select id="refund-method" v-model="processForm.method" class="form-select mb-2"><option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option></select><label for="refund-reference" class="form-label">External reference</label><input id="refund-reference" v-model="processForm.reference" required maxlength="100" class="form-control mb-2" /><label class="d-block mb-3"><input v-model="processForm.confirmed" type="checkbox" /> I confirm the funds have been returned.</label><button class="btn btn-success" :disabled="processForm.processing || !selectedRefund || !processForm.confirmed">Record refund settlement</button></form></div>
    </div>
    <div class="card p-3 mt-3"><h4>Reschedule history</h4><p v-for="change in reschedules" :key="change.created_at">{{ change.old_pickup_at }} → {{ change.new_pickup_at }}<span v-if="change.reason"> · {{ change.reason }}</span></p><p v-if="!reschedules.length">No reschedules.</p></div>
</div></component></template>
