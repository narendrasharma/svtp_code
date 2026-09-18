<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    users: Object,
    filters: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
    sortOptions: { type: Array, default: () => [] },
    perPageOptions: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/users');
const filters = useForm({
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    sort: props.filters.sort ?? 'newest',
    per_page: props.filters.per_page ?? 15,
});

const hasActiveFilters = computed(() => filters.search !== '' || filters.role !== '');

function applyFilters() {
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}
function clearFilters() {
    router.get(endpoint);
}
function roleBadge(role) {
    return {
        admin: 'bg-danger',
        customer: 'bg-info text-dark',
        vendor: 'bg-success',
    }[role] ?? 'bg-secondary';
}
function impersonate(user) {
    if (!confirm(`Impersonate ${user.name}? You will view the site as this user.`)) return;
    router.post(appUrl(`/admin/users/${user.id}/impersonate`));
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Users</h2>
                <p class="text-muted mb-0">Central directory for all accounts — customers, vendors and admins. Vendor details live in Vendor modules.</p>
            </div>
            <span class="badge bg-secondary">{{ users.total }} total</span>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="filter-search" class="form-label small">Search</label>
                    <input id="filter-search" v-model="filters.search" class="form-control form-control-sm" placeholder="Name, email, phone" />
                </div>
                <div class="col-md-2">
                    <label for="filter-role" class="form-label small">Role</label>
                    <select id="filter-role" v-model="filters.role" class="form-select form-select-sm">
                        <option value="">All roles</option>
                        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filter-sort" class="form-label small">Sort</label>
                    <select id="filter-sort" v-model="filters.sort" class="form-select form-select-sm">
                        <option v-for="o in sortOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter-perpage" class="form-label small">Per page</label>
                    <select id="filter-perpage" v-model="filters.per_page" class="form-select form-select-sm">
                        <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
                        <option :value="15">15 (default)</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex gap-2 align-items-end">
                    <button class="btn btn-sm btn-svtp w-100" :disabled="filters.processing">Apply</button>
                </div>
                <div v-if="hasActiveFilters" class="col-12">
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear filters</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Contact</th>
                        <th>Vendor / KYC</th>
                        <th>Bookings</th>
                        <th>Registered</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users.data" :key="user.id">
                        <td>
                            <strong>{{ user.name }}</strong>
                            <div class="small text-muted">{{ user.email }}</div>
                        </td>
                        <td><span class="badge" :class="roleBadge(user.role)">{{ user.role_label ?? user.role }}</span></td>
                        <td class="small text-muted">{{ user.phone || '—' }}</td>
                        <td class="small">
                            <div v-if="user.role === 'vendor'">
                                <span class="fw-semibold">{{ user.vendor_business_name || 'Vendor' }}</span>
                                <span class="badge bg-secondary ms-1">{{ user.vendor_status }}</span>
                                <div class="text-muted" v-if="user.kyc_status">KYC: {{ user.kyc_status }}</div>
                            </div>
                            <div v-else-if="user.application_status">
                                App: <span class="badge bg-secondary">{{ user.application_status }}</span>
                                <div class="text-muted" v-if="user.kyc_status">KYC: {{ user.kyc_status }}</div>
                            </div>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td class="text-center"><span class="badge bg-secondary">{{ user.bookings_count }}</span></td>
                        <td class="small text-muted text-nowrap">{{ new Date(user.created_at).toLocaleDateString() }}</td>
                        <td class="text-nowrap">
                            <Link :href="`${endpoint}/${user.id}`" class="btn btn-sm btn-outline-primary me-1">View</Link>
                            <button v-if="user.can_impersonate" type="button" class="btn btn-sm btn-outline-warning" @click="impersonate(user)"><i class="bi bi-eye me-1"></i>Impersonate</button>
                            <span v-else class="small text-muted ms-1">No impersonate</span>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length">
                        <td colspan="7" class="text-center text-muted py-4">No users match these filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="users.links" />
        <p class="small text-muted mt-2">Vendor role is granted only via Vendor approval workflow — not via casual role editing. No hard-delete for users with bookings/history.</p>
    </AdminLayout>
</template>
