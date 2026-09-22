<script setup>
import { Link } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import InventoryCalendar from '../../../../Components/Hotel/InventoryCalendar.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ properties: Array, roomTypes: Array, propertyId: Number, roomTypeId: Number, start: String, end: String, structuralCapacity: Number, inventoryMode: String, rows: Array, maxBulkDays: Number });
</script>
<template><VendorLayout><div class="container-fluid py-3">
<Link :href="appUrl('/vendor/hotel/properties')">← My properties</Link>
<h2 class="my-3">Room inventory</h2>
<p class="text-muted">Manage sellable rooms per date for your own listings. Availability only — no pricing.</p>
<InventoryCalendar :properties="properties" :room-types="roomTypes" :property-id="propertyId" :room-type-id="roomTypeId" :start="start" :end="end" :structural-capacity="structuralCapacity" :inventory-mode="inventoryMode" :rows="rows" :max-bulk-days="maxBulkDays" base-path="/vendor/hotel/inventory" />
</div></VendorLayout></template>
