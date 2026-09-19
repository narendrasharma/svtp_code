<script setup>
import { appUrl } from '../../appUrl';
import { Link, useForm } from '@inertiajs/vue3';
import Pagination from '../Pagination.vue';
const props = defineProps({ payouts: Object, filters: Object, statuses: Array, portal: String });
const form = useForm({ search: props.filters?.search ?? '', status: props.filters?.status ?? '' });
</script>
<template><div>
 <div class="d-flex flex-wrap gap-2 align-items-center mb-3"><h2 class="me-auto">Driver payouts</h2><Link :href="appUrl(`/${portal}/taxi/earnings`)" class="btn btn-outline-secondary">Earnings</Link><Link :href="appUrl(`/${portal}/taxi/payouts/create`)" class="btn btn-svtp">Create payout</Link></div>
 <form class="card p-3 mb-3 d-flex flex-row flex-wrap gap-2" @submit.prevent="form.get(appUrl(`/${portal}/taxi/payouts`))"><input v-model="form.search" aria-label="Search payouts" class="form-control w-auto" placeholder="Payout, driver or reference" /><select v-model="form.status" aria-label="Payout status" class="form-select w-auto"><option value="">All statuses</option><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select><button class="btn btn-outline-secondary" :disabled="form.processing">Filter</button></form>
 <div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Payout</th><th>Driver</th><th>Amount</th><th>Status</th><th>Paid / Reference</th></tr></thead><tbody><tr v-for="payout in payouts.data" :key="payout.id"><td><Link :href="appUrl(`/${portal}/taxi/payouts/${payout.id}`)">{{ payout.payout_number }}</Link></td><td>{{ payout.driver?.first_name }} {{ payout.driver?.last_name }}</td><td>{{ payout.currency }} {{ payout.amount }}</td><td>{{ payout.status }}</td><td>{{ payout.paid_at?.slice(0,10) ?? '—' }} / {{ payout.payment_reference ?? '—' }}</td></tr><tr v-if="!payouts.data.length"><td colspan="5" class="text-center p-4">No payouts yet.</td></tr></tbody></table></div><Pagination :links="payouts.links" /></div>
</div></template>
