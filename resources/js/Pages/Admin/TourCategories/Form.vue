<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ category: { type: Object, default: null } });
const form = useForm({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    icon: props.category?.icon ?? '',
    description: props.category?.description ?? '',
    sort_order: props.category?.sort_order ?? 0,
    is_active: props.category?.is_active ?? true,
});

watch(() => form.name, (name) => {
    if (!props.category) form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

function submit() {
    const url = props.category ? `${appUrl('/admin/tour-categories')}/${props.category.id}` : appUrl('/admin/tour-categories');
    props.category ? form.put(url) : form.post(url);
}
</script>

<template>
    <AdminLayout>
        <h2>{{ category ? 'Edit' : 'New' }} Tour Category</h2>
        <form class="mt-3" style="max-width: 640px;" @submit.prevent="submit">
            <label class="form-label">Name</label>
            <input v-model="form.name" class="form-control" required>
            <small class="text-danger">{{ form.errors.name }}</small>

            <label class="form-label mt-3">Slug</label>
            <input v-model="form.slug" class="form-control" required>
            <small class="text-danger">{{ form.errors.slug }}</small>

            <label class="form-label mt-3">Bootstrap icon class <span class="text-muted">(optional)</span></label>
            <input v-model="form.icon" class="form-control" placeholder="e.g. bi-map">
            <small class="text-danger">{{ form.errors.icon }}</small>

            <label class="form-label mt-3">Description <span class="text-muted">(optional)</span></label>
            <textarea v-model="form.description" class="form-control" rows="3"></textarea>
            <small class="text-danger">{{ form.errors.description }}</small>

            <label class="form-label mt-3">Display order</label>
            <input v-model.number="form.sort_order" type="number" min="0" class="form-control">
            <small class="text-danger">{{ form.errors.sort_order }}</small>

            <div class="form-check mt-3">
                <input id="category-active" v-model="form.is_active" type="checkbox" class="form-check-input">
                <label for="category-active" class="form-check-label">Active</label>
            </div>
            <small class="text-danger">{{ form.errors.is_active }}</small>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">Save Category</button>
                <Link :href="appUrl('/admin/tour-categories')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
