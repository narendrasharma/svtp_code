<script setup>
import { Link, router } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ property: Object });
</script>
<template><VendorLayout><div class="container-fluid py-3">
<Link :href="appUrl('/vendor/hotel/properties')">← My properties</Link>
<Link :href="appUrl(`/vendor/hotel/reviews?property_id=${property.id}`)" class="btn btn-sm btn-outline-primary ms-2">Guest reviews</Link>
<Link :href="appUrl(`/vendor/hotel/properties/${property.id}/edit`)" class="btn btn-sm btn-outline-secondary ms-2">Edit</Link>
<Link :href="appUrl(`/vendor/hotel/properties/${property.id}/room-types`)" class="btn btn-sm btn-outline-primary ms-2">Room types</Link>
<Link :href="appUrl(`/vendor/hotel/properties/${property.id}/units`)" class="btn btn-sm btn-outline-primary ms-2">Room units</Link>
<h2 class="my-3">{{ property.name }} <span class="badge bg-secondary">{{ property.status }}</span></h2>
<div class="row g-3">
<div class="col-lg-8">
<div class="card p-3 mb-3"><h5>Overview</h5>
<p class="mb-1"><strong>{{ property.property_type?.name }}</strong></p>
<p class="mb-1">{{ property.address_line_1 }}<span v-if="property.address_line_2">, {{ property.address_line_2 }}</span></p>
<p class="mb-1">{{ property.city?.name }}<span v-if="property.state">, {{ property.state.name }}</span> {{ property.country_code }}</p>
<p v-if="property.short_description" class="mt-2">{{ property.short_description }}</p>
<div v-if="property.description" v-html="property.description" class="mt-2"></div>
</div>
<div class="card p-3 mb-3"><h5>Amenities</h5><p class="mb-0">{{ (property.amenities ?? []).map(a => a.name).join(' · ') || 'None attached.' }}</p></div>
<div class="card p-3 mb-3"><h5>Gallery</h5><div class="d-flex flex-wrap gap-2"><img v-for="img in property.images" :key="img.id" :src="appUrl(`/storage/${img.path}`)" :alt="img.alt_text ?? property.name" width="140" class="rounded" /></div><p v-if="!property.images?.length" class="text-muted mb-0">No images.</p></div>
</div>
<div class="col-lg-4"><div class="card p-3"><h5>Listing</h5>
<p>Status: <strong>{{ property.status }}</strong></p>
<button v-if="['draft', 'rejected'].includes(property.status)" class="btn btn-success w-100 mb-2" @click="router.post(appUrl(`/vendor/hotel/properties/${property.id}/submit`))">Submit for review</button>
<button class="btn btn-outline-danger w-100" @click="router.delete(appUrl(`/vendor/hotel/properties/${property.id}`))">Archive property</button>
</div></div>
</div>
</div></VendorLayout></template>
