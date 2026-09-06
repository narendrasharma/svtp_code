<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import Pagination from "@/Components/Pagination.vue";

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
                <thead><tr><th>#</th><th>Name</th><th>Status</th><th>Tours</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <tr v-for="(tag,index) in tags.data" :key="tag.id">
                        <td>{{tags.from+index}}</td>
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
            <Pagination :links="tags.links" />
        </div>
    </AdminLayout>
</template>
