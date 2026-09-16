<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import Pagination from '@/Components/Pagination.vue';

defineProps({ menus: Object });

function removeMenu(menu) {
    if (window.confirm(`Remove ${menu.name}? All its items will be deleted.`)) {
        router.delete(`${appUrl('/admin/menus')}/${menu.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center">
            <h2>Menus</h2>
            <Link :href="appUrl('/admin/menus/create')" class="btn btn-svtp">+ New Menu</Link>
        </div>

        <div class="table-responsive mt-3">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(menu, index) in menus.data" :key="menu.id">
                        <td>{{ menus.from + index }}</td>
                        <td><strong>{{ menu.name }}</strong><br><small class="text-muted">{{ menu.slug }}</small></td>
                        <td>{{ menu.location || 'Not assigned' }}</td>
                        <td>{{ menu.sort_order }}</td>
                        <td>
                            <span class="badge" :class="menu.is_active ? 'bg-success' : 'bg-secondary'">
                                {{ menu.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <!-- Renamed from “Items” to “Manage Menu” -->
                            <Link :href="`${appUrl('/admin/menus')}/${menu.id}/items`" class="btn btn-sm btn-outline-primary me-2">Manage Menu</Link>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeMenu(menu)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!menus.data.length">
                        <td colspan="6" class="text-center text-muted py-4">No menus added yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between">
            <Pagination :links="menus.links" />
        </div>
    </AdminLayout>
</template>
