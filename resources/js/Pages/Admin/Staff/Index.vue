<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    staff: Object,
    filters: { type: Object, default: () => ({}) },
});

const endpoint = appUrl('/admin/staff');
const filters = useForm({ search: props.filters.search ?? '' });

function applyFilters() {
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Staff</h2>
                <p class="text-muted mb-0">Internal team only — customers and vendors are managed under All Users, never here.</p>
            </div>
            <Link :href="appUrl('/admin/staff/create')" class="btn btn-svtp"><i class="bi bi-plus-lg me-1"></i>New Staff</Link>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label for="staff-search" class="form-label small">Search</label>
                    <input id="staff-search" v-model="filters.search" class="form-control form-control-sm" placeholder="Name or email" />
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-svtp w-100">Search</button>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" @click="router.get(endpoint)">Clear</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Joined</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="member in staff.data" :key="member.id">
                        <td>
                            {{ member.name }}
                            <span v-if="member.is_super_admin" class="badge bg-danger ms-1">Super Admin</span>
                            <span v-else-if="member.is_legacy_admin" class="badge bg-warning text-dark ms-1">Full access (pre-RBAC)</span>
                        </td>
                        <td>{{ member.email }}</td>
                        <td>
                            <span v-for="role in member.roles" :key="role" class="badge bg-secondary me-1">{{ role }}</span>
                            <span v-if="!member.roles.length" class="text-muted small">—</span>
                        </td>
                        <td class="small">{{ member.created_at ? new Date(member.created_at).toLocaleDateString() : '—' }}</td>
                        <td class="text-end">
                            <Link :href="appUrl(`/admin/staff/${member.id}/edit`)" class="btn btn-sm btn-outline-primary">Manage</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!staff.data.length" class="text-muted">No staff members found.</p>
        <Pagination :links="staff.links" />
    </AdminLayout>
</template>
