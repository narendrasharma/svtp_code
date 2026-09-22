<script setup>
import { Link, router } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ roomType: Object });
</script>
<template><VendorLayout><div class="container-fluid py-3">
<Link :href="appUrl(`/vendor/hotel/properties/${roomType.property_id}/room-types`)">← Room types</Link>
<Link :href="appUrl(`/vendor/hotel/room-types/${roomType.id}/edit`)" class="btn btn-sm btn-outline-secondary ms-2">Edit</Link>
<h2 class="my-3">{{ roomType.name }}</h2>
<div class="row g-3">
<div class="col-lg-8">
<div class="card p-3 mb-3"><h5>Overview</h5>
<p class="mb-1">Sleeps {{ roomType.max_adults }} adults<span v-if="roomType.max_children"> + {{ roomType.max_children }} children</span> (max {{ roomType.max_occupancy }}) · Status: <strong>{{ roomType.status }}</strong></p>
<p class="mb-1">Beds: {{ roomType.bed_summary ?? '—' }}</p>
<p class="mb-1">Size: {{ roomType.size_value ? `${roomType.size_value} ${roomType.size_unit ?? ''}` : '—' }} · Mode: {{ roomType.inventory_mode }}</p>
<p v-if="roomType.short_description" class="mt-2">{{ roomType.short_description }}</p>
<div v-if="roomType.description" v-html="roomType.description" class="mt-2"></div>
</div>
<div class="card p-3 mb-3"><h5>Room amenities</h5><p class="mb-0">{{ (roomType.amenities ?? []).map(a => a.name).join(' · ') || 'None attached.' }}</p></div>
</div>
<div class="col-lg-4"><div class="card p-3"><h5>Actions</h5>
<button class="btn btn-outline-danger w-100" @click="router.delete(appUrl(`/vendor/hotel/room-types/${roomType.id}`))">Archive room type</button>
</div></div>
</div>
</div></VendorLayout></template>
