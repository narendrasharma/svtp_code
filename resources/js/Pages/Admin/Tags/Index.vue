<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({ tags: Object });

function removeTag(tag) {
    if (window.confirm(`Remove ${tag.name}?`)) router.delete(`${appUrl('/admin/tags')}/${tag.id}`);
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center">
            <h2>Tags</h2>
            <Link :href="appUrl('/admin/tags/create')" class="btn btn-svtp">+ New Tag</Link>
        </div>
        <div class="table-responsive mt-3">
            <table class="table align-middle">
                <thead><tr><th>Name</th><th>Status</th><th>Tours</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <tr v-for="tag in tags.data" :key="tag.id">
                        <td><strong>{{ tag.name }}</strong><br><small class="text-muted">{{ tag.slug }}</small></td>
                        <td><span class="badge" :class="tag.is_active ? 'bg-success' : 'bg-secondary'">{{ tag.is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>{{ tag.tour_packages_count }}</td>
                        <td class="text-end">
                            <Link :href="`${appUrl('/admin/tags')}/${tag.id}/edit`" class="btn btn-sm btn-outline-secondary me-2">Edit</Link>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeTag(tag)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!tags.data.length"><td colspan="4" class="text-center text-muted py-4">No tags added yet.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between">
            <Link v-if="tags.prev_page_url" :href="tags.prev_page_url" class="btn btn-outline-secondary">Previous</Link><span v-else></span>
            <Link v-if="tags.next_page_url" :href="tags.next_page_url" class="btn btn-outline-secondary">Next</Link>
        </div>
    </AdminLayout>
</template>
