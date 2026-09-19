<script setup>
import { appUrl } from '../../appUrl';
import { Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ payout: Object, portal: String });
const paid = useForm({ payment_reference: '', notes: props.payout.notes ?? '' });
const cancel = useForm({ reason: '' });
</script>
<template><div>
 <Link :href="appUrl(`/${portal}/taxi/payouts`)">← Payouts</Link><h2 class="my-3">{{ payout.payout_number }}</h2>
 <div class="card p-3 mb-3"><p class="fs-5">{{ payout.driver?.first_name }} {{ payout.driver?.last_name }} · {{ payout.currency }} {{ payout.amount }} · {{ payout.status }}</p><p>Payment method: {{ payout.payment_method }} · Reference: {{ payout.payment_reference ?? '—' }} · Paid: {{ payout.paid_at ?? '—' }}</p><p class="mb-0">{{ payout.notes }}</p></div>
 <div class="card table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Earning</th><th>Trip</th><th>Allocated amount</th></tr></thead><tbody><tr v-for="item in payout.items" :key="item.id"><td><Link :href="appUrl(`/${portal}/taxi/earnings/${item.earning_id}`)">{{ item.earning?.earning_number }}</Link></td><td>{{ item.earning?.booking?.reference }}</td><td>{{ payout.currency }} {{ item.amount }}</td></tr><tr v-if="!payout.items?.length"><td colspan="3">No active allocations.</td></tr></tbody></table></div>
 <div v-if="['draft','processing'].includes(payout.status)" class="row g-3">
  <form class="col-md-6" @submit.prevent="paid.patch(appUrl(`/${portal}/taxi/payouts/${payout.id}/mark-paid`))"><div class="card p-3"><h5>Record payment</h5><div v-for="(error,key) in paid.errors" :key="key" class="alert alert-danger">{{ error }}</div><label for="payment-reference" class="form-label">Payment reference</label><input id="payment-reference" v-model="paid.payment_reference" maxlength="100" class="form-control mb-3" /><label for="paid-notes" class="form-label">Notes</label><textarea id="paid-notes" v-model="paid.notes" maxlength="2000" class="form-control mb-3" /><button class="btn btn-success" :disabled="paid.processing">Mark paid</button></div></form>
  <form class="col-md-6" @submit.prevent="cancel.patch(appUrl(`/${portal}/taxi/payouts/${payout.id}/cancel`))"><div class="card p-3"><h5>Cancel unpaid payout</h5><div v-for="(error,key) in cancel.errors" :key="key" class="alert alert-danger">{{ error }}</div><label for="cancel-reason" class="form-label">Reason</label><input id="cancel-reason" v-model="cancel.reason" maxlength="500" class="form-control mb-3" /><button class="btn btn-outline-danger" :disabled="cancel.processing">Cancel and release earnings</button></div></form>
 </div>
</div></template>
