<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({
    roles: { type: Array, default: () => [] },
});

function destroy(role) {
    if (!confirm(`Delete role "${role.name}"? Members lose the permissions it granted.`)) return;
    router.delete(appUrl(`/admin/roles/${role.id}`));
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Roles & Permissions</h2>
                <p class="text-muted mb-0">Hiding navigation is not security — every route also enforces its permission server-side. The Super Admin role is protected and cannot be deleted.</p>
            </div>
            <Link :href="appUrl('/admin/roles/create')" class="btn btn-svtp"><i class="bi bi-plus-lg me-1"></i>New Role</Link>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Role</th><th>Description</th><th>Permissions</th><th>Members</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="role in roles" :key="role.id">
                        <td>
                            <span class="fw-semibold">{{ role.name }}</span>
                            <span v-if="role.is_protected" class="badge bg-danger ms-1">Protected</span>
                        </td>
                        <td class="small text-muted">{{ role.description || '—' }}</td>
                        <td><span class="badge bg-secondary">{{ role.permissions.length }}</span></td>
                        <td>{{ role.users_count }}</td>
                        <td class="text-end text-nowrap">
                            <Link :href="appUrl(`/admin/roles/${role.id}/edit`)" class="btn btn-sm btn-outline-primary me-1">Edit</Link>
                            <button v-if="!role.is_protected" type="button" class="btn btn-sm btn-outline-danger" @click="destroy(role)">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
