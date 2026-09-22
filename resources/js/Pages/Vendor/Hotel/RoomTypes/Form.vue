<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import RoomTypeForm from '../../../../Components/Hotel/RoomTypeForm.vue';
import { appUrl } from '../../../../appUrl';
const props = defineProps({ property: Object, roomType: Object, bedTypes: Array, amenities: Array, statuses: Array, inventoryModes: Array, sizeUnits: Array, showSize: Boolean, showBeds: Boolean, unitsEnabled: Boolean, customFields: Array });
const base = props.roomType ? `/vendor/hotel/room-types/${props.roomType.id}` : `/vendor/hotel/properties/${props.property.id}/room-types`;
const imageForm = useForm({ image: null, alt_text: '' });
function uploadImage(e) {
    imageForm.image = e.target.files[0];
    imageForm.post(appUrl(`${base}/images`), { onSuccess: () => imageForm.reset() });
}
</script>
<template><VendorLayout><div class="container-fluid py-3">
<Link :href="appUrl(`/vendor/hotel/properties/${property.id}/room-types`)">← Room types · {{ property.name }}</Link>
<h2 class="my-3">{{ roomType ? `Edit · ${roomType.name}` : `New room type · ${property.name}` }}</h2>
<div class="card p-3 mb-3"><RoomTypeForm
:property="property" :room-type="roomType" :bed-types="bedTypes" :amenities="amenities" :statuses="statuses"
:inventory-modes="inventoryModes" :size-units="sizeUnits"
:submit-url="appUrl(`/vendor/hotel/properties/${property.id}/room-types`)" :update-url="roomType ? appUrl(`${base}`) : ''" :custom-fields="customFields ?? []" /></div>
<div v-if="roomType" class="card p-3"><h5>Gallery</h5>
<div v-if="!roomType.images?.length" class="text-muted">No images yet.</div>
<div v-for="img in roomType.images" :key="img.id" class="d-flex align-items-center gap-2 border-bottom py-2">
<img :src="appUrl(`/storage/${img.path}`)" :alt="img.alt_text ?? roomType.name" width="90" class="rounded" />
<span class="small">{{ img.alt_text }} <span v-if="img.is_primary" class="badge bg-success">cover</span></span>
<span class="ms-auto d-flex gap-1">
<button class="btn btn-sm btn-outline-secondary" @click="router.patch(appUrl(`${base}/images/${img.id}/primary`))">Cover</button>
<button class="btn btn-sm btn-outline-danger" @click="router.delete(appUrl(`${base}/images/${img.id}`))">Remove</button>
</span></div>
<label class="btn btn-outline-primary mt-3">Add image<input type="file" accept="image/*" class="d-none" @change="uploadImage" /></label>
</div>
</div></VendorLayout></template>
