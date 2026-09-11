<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ place: { type: Object, default: null }, destinations: Array });
const form = useForm({
    name: props.place?.name ?? '',
    slug: props.place?.slug ?? '',
    destination_id: props.place?.destination_id ?? '',
    description: props.place?.description ?? '',
    image_upload: null,
    remove_image: false,
    meta_description: props.place?.meta_description ?? '',
    meta_title: props.place?.meta_title ?? '',
});

watch(() => form.name, (name) => {
    if (!props.place) form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

function submit() {
    const url = props.place ? `${appUrl('/admin/places')}/${props.place.id}` : appUrl('/admin/places');
    if (props.place) {
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
        <h2>{{ place ? 'Edit' : 'New' }} Place / Attraction</h2>
        <form class="mt-3" style="max-width: 640px;" @submit.prevent="submit">
            <label class="form-label">Name</label>
            <input v-model="form.name" class="form-control" required>
            <small class="text-danger">{{ form.errors.name }}</small>

            <label class="form-label mt-3">Slug</label>
            <input v-model="form.slug" class="form-control" required>
            <small class="text-danger">{{ form.errors.slug }}</small>

            <label class="form-label mt-3">Destination</label>
            <select v-model="form.destination_id" class="form-select" required>
                <option value="" disabled>Select Destination</option>
                <option v-for="destination in destinations" :key="destination.id" :value="destination.id">{{ destination.name }}</option>
            </select>
            <small class="text-danger">{{ form.errors.destination_id }}</small>

            <label class="form-label mt-3">Description</label>
            <textarea v-model="form.description" class="form-control" rows="5" placeholder="Describe this temple, landmark, or attraction."></textarea>
            <small class="text-danger">{{ form.errors.description }}</small>

            <div class="mt-3">
                <label class="form-label">Image</label>
                <div v-if="place?.image && !form.remove_image" class="mb-2">
                    <img :src="place.image" :alt="`${place.name} current image`" class="rounded border" style="width: 180px; height: 110px; object-fit: cover;">
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-danger mt-2" @click="form.remove_image = true">Remove image</button>
                    </div>
                </div>
                <p v-else-if="place?.image && form.remove_image" class="small text-muted mb-2">The current image will be removed when you save.</p>
                <input type="file" class="form-control" accept="image/jpeg,image/png,image/webp" @change="selectImage">
                <small class="d-block text-muted mt-1">JPG, PNG, or WebP; up to 5 MB.</small>
                <small class="text-danger">{{ form.errors.image_upload }}</small>
            </div>



            <div class="card mt-4">
                <div class="card-header">
                    <strong>
                        <i class="bi bi-search me-2"></i>
                        SEO Settings
                    </strong>
                </div>

                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label">
                            Meta Title
                        </label>

                        <input
                            v-model="form.meta_title"
                            type="text"
                            class="form-control"
                            maxlength="70"
                            placeholder="Leave blank to generate automatically"
                        >

                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">
                                If empty, the place name will be used automatically.
                            </small>

                            <small class="text-muted">
                                {{ form.meta_title.length }}/70
                            </small>
                        </div>

                        <small class="text-danger">
                            {{ form.errors.meta_title }}
                        </small>
                    </div>


                    <div>
                        <label class="form-label">
                            Meta Description
                        </label>

                        <textarea
                            v-model="form.meta_description"
                            class="form-control"
                            rows="3"
                            maxlength="170"
                            placeholder="Describe this destination for search engines"
                        ></textarea>

                        <div class="d-flex justify-content-between mt-1">
                            <small class="text-muted">
                                If empty, the place description will be used automatically.
                            </small>

                            <small class="text-muted">
                                {{ form.meta_description.length }}/170
                            </small>
                        </div>

                        <small class="text-danger">
                            {{ form.errors.meta_description }}
                        </small>
                    </div>

                </div>
            </div>


            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">Save Place</button>
                <Link :href="appUrl('/admin/places')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
