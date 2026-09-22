<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import DailyRateCalendar from '../../../../Components/Hotel/DailyRateCalendar.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ plans: Array, plan: Object, planId: Number, start: String, end: String, rows: Array, maxBulkDays: Number });
</script>
<template><AdminLayout><div class="container-fluid py-3">
<Link :href="appUrl('/admin/hotel/rate-plans')">← Rate plans</Link>
<h2 class="my-3">Daily rates</h2>
<p class="text-muted">Per-date price overrides and plan restrictions. Pricing only — room quantities live under Inventory.</p>
<DailyRateCalendar :plans="plans" :plan="plan" :plan-id="planId" :start="start" :end="end" :rows="rows" :max-bulk-days="maxBulkDays" base-path="/admin/hotel/daily-rates" plans-path="/admin/hotel/rate-plans" />
</div></AdminLayout></template>
