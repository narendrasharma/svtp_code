<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    city: { type: Object, default: null },
    countries: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
});
const form = useForm({
    country_id: props.city?.country_id ?? '',
    state_id: props.city?.state_id ?? '',
    name: props.city?.name ?? '',
    slug: props.city?.slug ?? '',
    latitude: props.city?.latitude ?? '',
    longitude: props.city?.longitude ?? '',
    is_active: props.city?.is_active ?? true,
    is_featured: props.city?.is_featured ?? false,
    sort_order: props.city?.sort_order ?? 0,
    image: props.city?.image ?? '',
    meta_title: props.city?.meta_title ?? '',
    meta_description: props.city?.meta_description ?? '',
});

watch(() => form.name, (name) => {
    if (!props.city) form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

const visibleStates = computed(() => {
    if (!form.country_id) return props.states;
    return props.states.filter(s => !s.country_id || String(s.country_id) === String(form.country_id));
});

function submit() {
    const url = props.city ? `${appUrl('/admin/cities')}/${props.city.id}` : appUrl('/admin/cities');
    const payload = (data) => {
        const out = { ...data };
        if (out.country_id === '') out.country_id = null;
        if (out.state_id === '') out.state_id = null;
        if (out.latitude === '') out.latitude = null;
        if (out.longitude === '') out.longitude = null;
        if (!out.image) out.image = null;
        if (!out.meta_title) out.meta_title = null;
        if (!out.meta_description) out.meta_description = null;
        return out;
    };
    if (props.city) {
        form.transform((data) => ({ ...payload(data), _method: 'put' })).put(url);
    } else {
        form.transform(payload).post(url);
    }
}
</script>

<template>
    <AdminLayout>
        <h2>{{ city ? 'Edit' : 'New' }} City</h2>
        <form class="mt-3" style="max-width: 640px;" @submit.prevent="submit">
            <label class="form-label">Country (optional)</label>
            <select v-model="form.country_id" class="form-select">
                <option value="">No country</option>
                <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.name }}</option>
            </select>
            <small class="text-danger">{{ form.errors.country_id }}</small>

            <label class="form-label mt-3">State / region (optional)</label>
            <select v-model="form.state_id" class="form-select">
                <option value="">No state</option>
                <option v-for="state in visibleStates" :key="state.id" :value="state.id">{{ state.name }}</option>
            </select>
            <small class="d-block text-muted mt-1">States are optional in several countries — leave blank where they don't apply.</small>
            <small class="text-danger">{{ form.errors.state_id }}</small>

            <label class="form-label mt-3">Name</label>
            <input v-model="form.name" class="form-control" required>
            <small class="text-danger">{{ form.errors.name }}</small>

            <label class="form-label mt-3">Slug</label>
            <input v-model="form.slug" class="form-control" required>
            <small class="text-danger">{{ form.errors.slug }}</small>

            <div class="row">
                <div class="col-md-6">
                    <label class="form-label mt-3">Latitude</label>
                    <input v-model="form.latitude" type="number" step="0.0000001" min="-90" max="90" class="form-control">
                    <small class="text-danger">{{ form.errors.latitude }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label mt-3">Longitude</label>
                    <input v-model="form.longitude" type="number" step="0.0000001" min="-180" max="180" class="form-control">
                    <small class="text-danger">{{ form.errors.longitude }}</small>
                </div>
            </div>

            <label class="form-label mt-3">Image URL (optional)</label>
            <input v-model="form.image" class="form-control" maxlength="255" placeholder="https://…">
            <small class="text-danger">{{ form.errors.image }}</small>

            <label class="form-label mt-3">Meta title</label>
            <input v-model="form.meta_title" class="form-control" maxlength="70" placeholder="Leave blank to generate automatically">
            <small class="text-danger">{{ form.errors.meta_title }}</small>

            <label class="form-label mt-3">Meta description</label>
            <textarea v-model="form.meta_description" class="form-control" rows="2" maxlength="170"></textarea>
            <small class="text-danger">{{ form.errors.meta_description }}</small>

            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" id="cityActiveSwitch" v-model="form.is_active">
                <label class="form-check-label" for="cityActiveSwitch">
                    {{ form.is_active ? 'Active' : 'Inactive' }}
                </label>
                <small class="text-danger">{{ form.errors.is_active }}</small>
            </div>

            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" id="cityFeaturedSwitch" v-model="form.is_featured">
                <label class="form-check-label" for="cityFeaturedSwitch">
                    {{ form.is_featured ? 'Featured' : 'Not featured' }}
                </label>
                <small class="text-danger">{{ form.errors.is_featured }}</small>
            </div>

            <label class="form-label mt-3">Sort order</label>
            <input v-model="form.sort_order" type="number" min="0" class="form-control">
            <small class="text-danger">{{ form.errors.sort_order }}</small>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">Save City</button>
                <Link :href="appUrl('/admin/cities')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
