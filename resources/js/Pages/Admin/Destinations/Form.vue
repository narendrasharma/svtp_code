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
    image: props.destination?.image ?? '',
    meta_description: props.destination?.meta_description ?? '',
});

watch(() => form.name, (name) => {
    if (!props.destination) form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

function submit() {
    const url = props.destination
        ? `${appUrl('/admin/destinations')}/${props.destination.id}`
        : appUrl('/admin/destinations');
    props.destination ? form.put(url) : form.post(url);
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

            <label class="form-label mt-3">Image URL or public path</label>
            <input v-model="form.image" class="form-control" placeholder="https://... or /images/vrindavan.jpg">
            <small class="text-danger">{{ form.errors.image }}</small>

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
