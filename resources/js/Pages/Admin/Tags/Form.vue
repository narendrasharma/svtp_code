<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ tag: { type: Object, default: null } });
const form = useForm({
    name: props.tag?.name ?? '',
    slug: props.tag?.slug ?? '',
    is_active: props.tag?.is_active ?? true,
});

watch(() => form.name, (name) => {
    if (!props.tag) form.slug = name.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
});

function submit() {
    const url = props.tag ? `${appUrl('/admin/tags')}/${props.tag.id}` : appUrl('/admin/tags');
    props.tag ? form.put(url) : form.post(url);
}
</script>

<template>
    <AdminLayout>
        <h2>{{ tag ? 'Edit' : 'New' }} Tag</h2>
        <form class="mt-3" style="max-width: 640px;" @submit.prevent="submit">
            <label class="form-label">Name</label>
            <input v-model="form.name" class="form-control" required>
            <small class="text-danger">{{ form.errors.name }}</small>

            <label class="form-label mt-3">Slug</label>
            <input v-model="form.slug" class="form-control" required>
            <small class="text-danger">{{ form.errors.slug }}</small>

            <div class="form-check mt-3">
                <input id="tag-active" v-model="form.is_active" type="checkbox" class="form-check-input">
                <label for="tag-active" class="form-check-label">Active</label>
            </div>
            <small class="text-danger">{{ form.errors.is_active }}</small>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">Save Tag</button>
                <Link :href="appUrl('/admin/tags')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
