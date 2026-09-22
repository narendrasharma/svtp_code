<script setup>
import { Link } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import DailyRateCalendar from '../../../../Components/Hotel/DailyRateCalendar.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ plans: Array, plan: Object, planId: Number, start: String, end: String, rows: Array, maxBulkDays: Number });
</script>
<template><VendorLayout><div class="container-fluid py-3">
<Link :href="appUrl('/vendor/hotel/rate-plans')">← Rate plans</Link>
<h2 class="my-3">Daily rates</h2>
<p class="text-muted">Per-date price overrides and plan restrictions for your own listings.</p>
<DailyRateCalendar :plans="plans" :plan="plan" :plan-id="planId" :start="start" :end="end" :rows="rows" :max-bulk-days="maxBulkDays" base-path="/vendor/hotel/daily-rates" plans-path="/vendor/hotel/rate-plans" />
</div></VendorLayout></template>
