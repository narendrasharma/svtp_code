<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    state: { type: Object, default: null },
    countries: { type: Array, default: () => [] },
});
const form = useForm({
    country_id: props.state?.country_id ?? '',
    name: props.state?.name ?? '',
    code: props.state?.code ?? '',
    slug: props.state?.slug ?? '',
    is_active: props.state?.is_active ?? true,
    sort_order: props.state?.sort_order ?? 0,
});

watch(() => form.name, (name) => {
    if (!props.state) form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

function submit() {
    const url = props.state ? `${appUrl('/admin/states')}/${props.state.id}` : appUrl('/admin/states');
    const payload = (data) => {
        const out = { ...data };
        if (out.country_id === '') out.country_id = null;
        if (!out.code) out.code = null;
        return out;
    };
    if (props.state) {
        form.transform((data) => ({ ...payload(data), _method: 'put' })).put(url);
    } else {
        form.transform(payload).post(url);
    }
}
</script>

<template>
    <AdminLayout>
        <h2>{{ state ? 'Edit' : 'New' }} State / Region</h2>
        <form class="mt-3" style="max-width: 640px;" @submit.prevent="submit">
            <label class="form-label">Country (optional)</label>
            <select v-model="form.country_id" class="form-select">
                <option value="">No country</option>
                <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.name }}</option>
            </select>
            <small class="text-danger">{{ form.errors.country_id }}</small>

            <label class="form-label mt-3">Name</label>
            <input v-model="form.name" class="form-control" required>
            <small class="text-danger">{{ form.errors.name }}</small>

            <label class="form-label mt-3">Slug</label>
            <input v-model="form.slug" class="form-control" required>
            <small class="text-danger">{{ form.errors.slug }}</small>

            <div class="row">
                <div class="col-md-6">
                    <label class="form-label mt-3">Code (optional)</label>
                    <input v-model="form.code" class="form-control" maxlength="10" placeholder="e.g. MH">
                    <small class="text-danger">{{ form.errors.code }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label mt-3">Sort order</label>
                    <input v-model="form.sort_order" type="number" min="0" class="form-control">
                    <small class="text-danger">{{ form.errors.sort_order }}</small>
                </div>
            </div>

            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" id="stateActiveSwitch" v-model="form.is_active">
                <label class="form-check-label" for="stateActiveSwitch">
                    {{ form.is_active ? 'Active' : 'Inactive' }}
                </label>
                <small class="text-danger">{{ form.errors.is_active }}</small>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">Save State</button>
                <Link :href="appUrl('/admin/states')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
