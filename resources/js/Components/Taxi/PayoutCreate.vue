<script setup>
import { appUrl } from '../../appUrl';
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
const props = defineProps({ driver: Object, drivers: Array, eligible: Array, paymentMethods: Array, portal: String });
const selectedDriver = ref(props.driver?.id ?? '');
const form = useForm({ driver_id: props.driver?.id ?? '', earning_ids: [], payment_method: 'bank_transfer', notes: '', period_start: '', period_end: '' });
function chooseDriver() { router.get(appUrl(`/${props.portal}/taxi/payouts/create`), { driver_id: selectedDriver.value }); }
</script>
<template><div>
 <Link :href="appUrl(`/${portal}/taxi/payouts`)">← Payouts</Link><h2 class="my-3">Create payout</h2>
 <label for="payout-driver" class="form-label">Driver</label><select id="payout-driver" v-model="selectedDriver" class="form-select mb-3" @change="chooseDriver"><option value="">Choose a driver</option><option v-for="person in drivers" :key="person.id" :value="person.id">{{ person.first_name }} {{ person.last_name }}</option></select>
 <form v-if="driver" class="card p-3" @submit.prevent="form.post(appUrl(`/${portal}/taxi/payouts`))">
  <div v-for="(error,key) in form.errors" :key="key" class="alert alert-danger">{{ error }}</div>
  <p>Select payable earnings in one currency. The final amount is calculated when the payout is created.</p>
  <div class="table-responsive"><table class="table"><thead><tr><th>Select</th><th>Earning / Trip</th><th>Unpaid amount</th></tr></thead><tbody><tr v-for="earning in eligible" :key="earning.id"><td><input v-model="form.earning_ids" type="checkbox" :value="earning.id" :aria-label="`Select ${earning.earning_number}`" /></td><td>{{ earning.earning_number }} / {{ earning.booking?.reference }}</td><td>{{ earning.currency }} {{ (Number(earning.net_earning)-Number(earning.paid_amount)).toFixed(2) }}</td></tr><tr v-if="!eligible.length"><td colspan="3">No eligible earnings.</td></tr></tbody></table></div>
  <label for="payout-method" class="form-label">Payment method</label><select id="payout-method" v-model="form.payment_method" class="form-select mb-3"><option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option></select>
  <label for="payout-notes" class="form-label">Notes</label><textarea id="payout-notes" v-model="form.notes" maxlength="2000" class="form-control mb-3" />
  <button class="btn btn-svtp align-self-start" :disabled="form.processing || !form.earning_ids.length">Create payout</button>
 </form>
</div></template>
