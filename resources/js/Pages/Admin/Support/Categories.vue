<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({
    categories: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/support-categories');
const createForm = useForm({ name: '', is_active: true, sort_order: 0 });
const editingId = ref(null);
const editForm = useForm({ name: '', is_active: true, sort_order: 0 });

function create() {
    createForm.post(endpoint, { preserveScroll: true, onSuccess: () => createForm.reset() });
}
function startEdit(category) {
    editingId.value = category.id;
    editForm.name = category.name;
    editForm.is_active = !!category.is_active;
    editForm.sort_order = category.sort_order ?? 0;
}
function saveEdit(id) {
    editForm.put(`${endpoint}/${id}`, { preserveScroll: true, onSuccess: () => { editingId.value = null; } });
}
function destroy(category) {
    if (!window.confirm(`Remove category "${category.name}"? Only unused categories can be deleted.`)) {
        return;
    }

    router.delete(`${endpoint}/${category.id}`, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <h2 class="mt-2 mb-1">Ticket Categories</h2>
        <p class="text-muted mb-3">Configurable — never hardcoded in code.</p>

        <form class="card p-3 mb-3" @submit.prevent="create">
            <div class="row g-2 align-items-end">
                <div class="col-md-5"><label for="category-name" class="form-label small">Name*</label><input id="category-name" v-model="createForm.name" class="form-control form-control-sm" required maxlength="100" /></div>
                <div class="col-md-2"><label for="category-order" class="form-label small">Order</label><input id="category-order" v-model.number="createForm.sort_order" type="number" min="0" class="form-control form-control-sm" /></div>
                <div class="col-md-2"><div class="form-check mt-4"><input id="category-active" v-model="createForm.is_active" type="checkbox" class="form-check-input" /><label for="category-active" class="form-check-label small">Active</label></div></div>
                <div class="col-md-3"><button class="btn btn-sm btn-svtp w-100" :disabled="createForm.processing">Add Category</button></div>
            </div>
            <div v-if="createForm.hasErrors" class="text-danger small mt-2"><div v-for="(e, k) in createForm.errors" :key="k">{{ e }}</div></div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Name</th><th>Slug</th><th class="text-end">Tickets</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="category in categories" :key="category.id">
                        <td>
                            <span v-if="editingId !== category.id">{{ category.name }}</span>
                            <input v-else v-model="editForm.name" class="form-control form-control-sm" maxlength="100" />
                        </td>
                        <td class="text-muted small">{{ category.slug }}</td>
                        <td class="text-end">{{ category.tickets_count }}</td>
                        <td><span class="badge" :class="category.is_active ? 'bg-success' : 'bg-secondary'">{{ category.is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td class="text-end text-nowrap">
                            <template v-if="editingId === category.id">
                                <button type="button" class="btn btn-sm btn-svtp me-1" @click="saveEdit(category.id)">Save</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="editingId = null">Cancel</button>
                            </template>
                            <template v-else>
                                <button type="button" class="btn btn-sm btn-outline-primary me-1" @click="startEdit(category)">Edit</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="destroy(category)">Delete</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
