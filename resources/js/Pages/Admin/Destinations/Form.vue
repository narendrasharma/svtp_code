<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ destination: { type: Object, default: null }, cities: Array });
const form = useForm({
    name: props.destination?.name ?? '',
    slug: props.destination?.slug ?? '',
    city_id: props.destination?.city_id ?? '',
    description: props.destination?.description ?? '',
    image_upload: null,
    remove_image: false,
    meta_description: props.destination?.meta_description ?? '',
});

watch(() => form.name, (name) => {
    if (!props.destination) form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

function submit() {
    const url = props.destination
        ? `${appUrl('/admin/destinations')}/${props.destination.id}`
        : appUrl('/admin/destinations');
    if (props.destination) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(url, { forceFormData: true });
    } else {
        form.post(url, { forceFormData: true });
    }
}

function selectImage(event) {
    form.image_upload = event.target.files?.[0] ?? null;

    if (form.image_upload) {
        form.remove_image = false;
    }
}
</script>

<template>
    <AdminLayout>
        <h2>{{ destination ? 'Edit' : 'New' }} Destination</h2>
        <form class="mt-3" style="max-width: 640px;" @submit.prevent="submit">
            <label class="form-label">Name</label>
            <input v-model="form.name" class="form-control" required>
            <small class="text-danger">{{ form.errors.name }}</small>

            <label class="form-label mt-3">Slug</label>
            <input v-model="form.slug" class="form-control" required>
            <small class="text-danger">{{ form.errors.slug }}</small>

            <label class="form-label mt-3">City (optional)</label>
            <select v-model="form.city_id" class="form-select">
                <option value="">No city</option>
                <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
            </select>
            <small class="text-danger">{{ form.errors.city_id }}</small>

            <label class="form-label mt-3">Description</label>
            <textarea v-model="form.description" class="form-control" rows="5" placeholder="Introduce this destination to travellers."></textarea>
            <small class="text-danger">{{ form.errors.description }}</small>

            <div class="mt-3">
                <label class="form-label">Image</label>
                <div v-if="destination?.image && !form.remove_image" class="mb-2">
                    <img :src="destination.image" :alt="`${destination.name} current image`" class="rounded border" style="width: 180px; height: 110px; object-fit: cover;">
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2" @click="form.remove_image = true">Remove image</button>
                    </div>
                </div>
                <p v-else-if="destination?.image && form.remove_image" class="small text-muted mb-2">The current image will be removed when you save.</p>
                <input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" @change="selectImage">
                <small class="d-block text-muted mt-1">JPG, PNG, or WebP; up to 5 MB.</small>
                <small class="text-danger">{{ form.errors.image_upload }}</small>
            </div>

            <label class="form-label mt-3">SEO description</label>
            <textarea v-model="form.meta_description" class="form-control" rows="2" maxlength="255" placeholder="Optional search-engine description."></textarea>
            <small class="text-danger">{{ form.errors.meta_description }}</small>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">Save Destination</button>
                <Link :href="appUrl('/admin/destinations')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
