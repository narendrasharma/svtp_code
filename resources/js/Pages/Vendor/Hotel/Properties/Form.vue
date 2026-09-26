<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import PropertyForm from '../../../../Components/Hotel/PropertyForm.vue';
import { appUrl } from '../../../../appUrl';
const props = defineProps({ property: Object, types: Array, amenities: Array, countries: Array, states: Array, requireApproval: Boolean, customFields: Array });
const base = props.property ? `/vendor/hotel/properties/${props.property.id}` : '/vendor/hotel/properties';
const imageForm = useForm({ image: null, alt_text: '' });
function uploadImage(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    imageForm.clearErrors();
    if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        imageForm.setError('image', 'Choose a JPG, PNG, WebP or GIF image under 5 MB.');
        e.target.value = '';
        return;
    }
    imageForm.image = file;
    imageForm.post(appUrl(`${base}/images`), { forceFormData: true, onSuccess: () => { imageForm.reset(); e.target.value = ''; } });
}
</script>
<template><VendorLayout><div class="container-fluid py-3">
<Link :href="appUrl('/vendor/hotel/properties')">← My properties</Link>
<h2 class="my-3">{{ property ? `Edit · ${property.name}` : 'New property' }}</h2>
<div class="card p-3 mb-3"><PropertyForm
:property="property" :types="types" :amenities="amenities" :countries="countries ?? []" :states="states"
:submit-url="appUrl('/vendor/hotel/properties')" :update-url="property ? appUrl(`${base}`) : ''"
:show-status="!!property" :cities-url="appUrl('/vendor/hotel/cities')" :destinations-url="appUrl('/vendor/hotel/destinations')" :custom-fields="customFields ?? []" :ai-endpoint="appUrl('/vendor/ai/content')">
<template #actions>
<button v-if="property && ['draft', 'rejected'].includes(property.status)" type="button" class="btn btn-outline-success" @click="router.post(appUrl(`${base}/submit`))">{{ requireApproval ? 'Submit for review' : 'Publish now' }}</button>
</template>
</PropertyForm></div>
<div v-if="property" class="card p-3"><h5>Gallery</h5>
<div v-if="!property.images?.length" class="text-muted">No images yet.</div>
<div v-for="img in property.images" :key="img.id" class="d-flex align-items-center gap-2 border-bottom py-2">
<img :src="appUrl(`/storage/${img.path}`)" :alt="img.alt_text ?? property.name" width="90" class="rounded" />
<span class="small">{{ img.alt_text }} <span v-if="img.is_primary" class="badge bg-success">cover</span></span>
<span class="ms-auto d-flex gap-1">
<button class="btn btn-sm btn-outline-secondary" @click="router.patch(appUrl(`${base}/images/${img.id}/primary`))">Cover</button>
<button class="btn btn-sm btn-outline-danger" @click="router.delete(appUrl(`${base}/images/${img.id}`))">Remove</button>
</span></div>
<label class="btn btn-outline-primary mt-3">Add image<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="d-none" @change="uploadImage" /></label>
<small v-if="imageForm.errors.image" class="text-danger d-block mt-2" role="alert">{{ imageForm.errors.image }}</small>
</div>
</div></VendorLayout></template>
