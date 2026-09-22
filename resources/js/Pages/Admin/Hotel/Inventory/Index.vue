<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import InventoryCalendar from '../../../../Components/Hotel/InventoryCalendar.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ properties: Array, roomTypes: Array, propertyId: Number, roomTypeId: Number, start: String, end: String, structuralCapacity: Number, inventoryMode: String, rows: Array, maxBulkDays: Number });
</script>
<template><AdminLayout><div class="container-fluid py-3">
<Link :href="appUrl('/admin/hotel/properties')">← All properties</Link>
<h2 class="my-3">Hotel inventory</h2>
<p class="text-muted">Per-date sellable rooms per room type. No pricing here — availability only.</p>
<InventoryCalendar :properties="properties" :room-types="roomTypes" :property-id="propertyId" :room-type-id="roomTypeId" :start="start" :end="end" :structural-capacity="structuralCapacity" :inventory-mode="inventoryMode" :rows="rows" :max-bulk-days="maxBulkDays" base-path="/admin/hotel/inventory" />
</div></AdminLayout></template>
