<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PropertyForm from '../../../../Components/Hotel/PropertyForm.vue';
import { appUrl } from '../../../../appUrl';
const props = defineProps({ property: Object, types: Array, amenities: Array, countries: Array, states: Array, vendors: Array, statuses: Array, canPublish: Boolean, customFields: Array });
const base = props.property ? `/admin/hotel/properties/${props.property.id}` : '/admin/hotel/properties';
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
<template><AdminLayout><div class="container-fluid py-3">
<Link :href="appUrl('/admin/hotel/properties')">← All properties</Link>
<h2 class="my-3">{{ property ? `Edit · ${property.name}` : 'New property' }}</h2>
<div class="card p-3 mb-3"><PropertyForm
:property="property" :types="types" :amenities="amenities" :countries="countries ?? []" :states="states" :vendors="vendors" :statuses="statuses"
:submit-url="appUrl('/admin/hotel/properties')" :update-url="property ? appUrl(`${base}`) : ''"
:show-vendor="true" :show-status="!!property" :show-featured="true" :cities-url="appUrl('/admin/hotel/cities')" :destinations-url="appUrl('/admin/hotel/destinations')" :custom-fields="customFields ?? []" /></div>
<div v-if="property" class="row g-3">
<div class="col-lg-8"><div class="card p-3"><h5>Gallery</h5>
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
</div></div>
<div class="col-lg-4"><div class="card p-3"><h5>Publishing</h5><p>Status: <strong>{{ property.status }}</strong></p>
<div v-if="canPublish" class="d-flex flex-wrap gap-2">
<button class="btn btn-sm btn-success" @click="router.post(appUrl(`${base}/publish`))">Publish</button>
<button class="btn btn-sm btn-outline-warning" @click="router.post(appUrl(`${base}/reject`), { note: 'Does not meet listing standards.' })">Reject</button>
<button class="btn btn-sm btn-outline-secondary" @click="router.post(appUrl(`${base}/deactivate`))">Deactivate</button>
<button class="btn btn-sm btn-outline-danger" @click="router.delete(appUrl(`${base}`))">Archive</button>
</div></div></div>
</div>
</div></AdminLayout></template>
