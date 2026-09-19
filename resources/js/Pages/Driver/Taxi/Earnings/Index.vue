<script setup>
import { appUrl } from '../../../../appUrl';
import { Link } from '@inertiajs/vue3';
import DriverLayout from '../../../../Layouts/DriverLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
defineProps({ earnings: Object, payouts: Object, summary: Object, summaries: Array });
</script>
<template><DriverLayout><h2 class="my-3">My earnings</h2>
 <div v-for="balance in (summaries?.length ? summaries : [summary])" :key="balance.currency" class="card p-3 mb-3"><h5>{{ balance.currency }}</h5><div class="row g-3"><div class="col-sm-4">Earned today<strong class="d-block">{{ balance.earned_today }}</strong></div><div class="col-sm-4">Payable balance<strong class="d-block">{{ balance.payable_balance }}</strong></div><div class="col-sm-4">Paid this month<strong class="d-block">{{ balance.paid_this_month }}</strong></div></div></div>
 <h4>Earning history</h4><div class="card mb-4"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Trip / Earning</th><th>Net</th><th>Status</th></tr></thead><tbody><tr v-for="earning in earnings.data" :key="earning.id"><td><Link :href="appUrl(`/driver/taxi/earnings/${earning.id}`)">{{ earning.booking?.reference }} / {{ earning.earning_number }}</Link></td><td>{{ earning.currency }} {{ earning.net_earning }}</td><td>{{ earning.status }}</td></tr><tr v-if="!earnings.data.length"><td colspan="3">No earnings yet.</td></tr></tbody></table></div><Pagination :links="earnings.links" /></div>
 <h4>Payout history</h4><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Payout</th><th>Amount</th><th>Status</th><th>Paid date / Reference</th></tr></thead><tbody><tr v-for="payout in payouts.data" :key="payout.id"><td>{{ payout.payout_number }}</td><td>{{ payout.currency }} {{ payout.amount }}</td><td>{{ payout.status }}</td><td>{{ payout.paid_at?.slice(0,10) ?? '—' }} / {{ payout.payment_reference ?? '—' }}</td></tr><tr v-if="!payouts.data.length"><td colspan="4">No payouts yet.</td></tr></tbody></table></div><Pagination :links="payouts.links" /></div>
</DriverLayout></template>
