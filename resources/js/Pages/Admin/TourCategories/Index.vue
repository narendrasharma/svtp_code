<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({ categories: Object });

function removeCategory(category) {
    if (window.confirm(`Remove ${category.name}? Packages will become uncategorized.`)) {
        router.delete(`${appUrl('/admin/tour-categories')}/${category.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center">
            <h2>Tour Categories</h2>
            <Link :href="appUrl('/admin/tour-categories/create')" class="btn btn-svtp">+ New Category</Link>
        </div>
        <div class="table-responsive mt-3">
            <table class="table align-middle">
                <thead><tr><th>Name</th><th>Order</th><th>Status</th><th>Tours</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <tr v-for="category in categories.data" :key="category.id">
                        <td>
                            <i v-if="category.icon" class="bi me-1" :class="category.icon"></i>
                            <strong>{{ category.name }}</strong><br><small class="text-muted">{{ category.slug }}</small>
                        </td>
                        <td>{{ category.sort_order }}</td>
                        <td><span class="badge" :class="category.is_active ? 'bg-success' : 'bg-secondary'">{{ category.is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td>{{ category.tour_packages_count }}</td>
                        <td class="text-end">
                            <Link :href="`${appUrl('/admin/tour-categories')}/${category.id}/edit`" class="btn btn-sm btn-outline-secondary me-2">Edit</Link>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeCategory(category)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!categories.data.length"><td colspan="5" class="text-center text-muted py-4">No tour categories added yet.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between">
            <Link v-if="categories.prev_page_url" :href="categories.prev_page_url" class="btn btn-outline-secondary">Previous</Link><span v-else></span>
            <Link v-if="categories.next_page_url" :href="categories.next_page_url" class="btn btn-outline-secondary">Next</Link>
        </div>
    </AdminLayout>
</template>
