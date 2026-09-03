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
    image: props.place?.image ?? '',
    meta_description: props.place?.meta_description ?? '',
});

watch(() => form.name, (name) => {
    if (!props.place) form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

function submit() {
    const url = props.place ? `${appUrl('/admin/places')}/${props.place.id}` : appUrl('/admin/places');
    props.place ? form.put(url) : form.post(url);
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

            <label class="form-label mt-3">Image URL or public path</label>
            <input v-model="form.image" class="form-control" placeholder="https://... or /images/prem-mandir.jpg">
            <small class="text-danger">{{ form.errors.image }}</small>

            <label class="form-label mt-3">SEO description</label>
            <textarea v-model="form.meta_description" class="form-control" rows="2" maxlength="255" placeholder="Optional search-engine description."></textarea>
            <small class="text-danger">{{ form.errors.meta_description }}</small>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">Save Place</button>
                <Link :href="appUrl('/admin/places')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
