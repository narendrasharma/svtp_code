<script setup>
import { appUrl } from '../../../../appUrl';
import { Link } from '@inertiajs/vue3';
import DriverLayout from '../../../../Layouts/DriverLayout.vue';
defineProps({ earning: Object, unpaidRemainder: [String, Number] });
</script>
<template><DriverLayout><Link :href="appUrl('/driver/taxi/earnings')">← My earnings</Link><h2 class="my-3">{{ earning.earning_number }}</h2>
 <div class="card p-3 mb-3"><h5>Trip {{ earning.booking?.reference }}</h5><p>{{ earning.status }} · {{ earning.currency }}</p><dl class="row"><dt class="col-6">Gross</dt><dd class="col-6">{{ earning.gross_earning }}</dd><dt class="col-6">Adjustments</dt><dd class="col-6">{{ earning.adjustments_total }}</dd><dt class="col-6">Net</dt><dd class="col-6">{{ earning.net_earning }}</dd><dt class="col-6">Unpaid</dt><dd class="col-6">{{ unpaidRemainder }}</dd></dl><p class="mb-0">Plan: {{ earning.calculation_snapshot?.plan?.name }} · {{ earning.calculation_type }}</p></div>
 <div class="card p-3 mb-3"><h5>Calculation summary</h5><dl v-for="(value,key) in earning.calculation_snapshot?.rates" :key="key" class="row mb-1"><dt class="col-6">{{ key.replaceAll('_',' ') }}</dt><dd class="col-6">{{ value }}</dd></dl><p class="mb-0">Minimum applied: {{ earning.calculation_snapshot?.result?.minimum_applied ? 'Yes' : 'No' }}</p></div>
 <div class="card p-3 mb-3"><h5>Adjustments</h5><p v-for="adjustment in earning.adjustments" :key="adjustment.id">{{ adjustment.created_at?.slice(0,10) }} · {{ adjustment.kind }} · {{ adjustment.amount }} · {{ adjustment.reason }}</p><p v-if="!earning.adjustments?.length">No adjustments.</p></div>
 <div class="card p-3"><h5>Payout allocations</h5><p v-for="item in earning.payout_items" :key="item.id">{{ item.payout?.payout_number }} · {{ earning.currency }} {{ item.amount }} · {{ item.payout?.status }} · {{ item.payout?.paid_at?.slice(0,10) ?? 'Unpaid' }} · {{ item.payout?.payment_reference ?? '—' }}</p><p v-if="!earning.payout_items?.length">Not yet allocated.</p></div>
</DriverLayout></template>
