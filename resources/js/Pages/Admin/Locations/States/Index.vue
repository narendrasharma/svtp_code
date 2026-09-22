<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    states: { type: Object, required: true },
    countries: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
});

const localFilters = ref({
    search: props.filters.search ?? '',
    country: props.filters.country ?? '',
    status: props.filters.status ?? 'all',
    per_page: props.filters.per_page ?? 15,
});

let debounceTimer = null;
function applyFilters() {
    const query = {};
    if (localFilters.value.search) query.search = localFilters.value.search;
    if (localFilters.value.country) query.country = localFilters.value.country;
    if (localFilters.value.status && localFilters.value.status !== 'all') query.status = localFilters.value.status;
    if (localFilters.value.per_page) query.per_page = localFilters.value.per_page;
    router.get(window.location.pathname, query, { preserveState: true, replace: true });
}
function debouncedApply() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
}
function resetFilters() {
    localFilters.value = { search: '', country: '', status: 'all', per_page: 15 };
    applyFilters();
}
function removeState(state) {
    if (window.confirm(`Remove ${state.name}? Only unreferenced states can be deleted.`)) {
        router.delete(`${appUrl('/admin/states')}/${state.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">States &amp; Regions</h2>
            <Link :href="appUrl('/admin/states/create')" class="btn btn-svtp">+ New State</Link>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <div class="flex-grow-1" style="min-width: 200px;">
                <input type="text" class="form-control form-control-sm" placeholder="Search name, slug, code..."
                    v-model="localFilters.search" @input="debouncedApply" />
            </div>
            <select class="form-select form-select-sm" v-model="localFilters.country" @change="applyFilters" style="width: 180px;">
                <option value="">All countries</option>
                <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.name }}</option>
            </select>
            <select class="form-select form-select-sm" v-model="localFilters.status" @change="applyFilters" style="width: 120px;">
                <option value="all">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select class="form-select form-select-sm" v-model.number="localFilters.per_page" @change="applyFilters" style="width: 100px;">
                <option :value="10">10</option>
                <option :value="15">15</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>
            <button class="btn btn-outline-secondary btn-sm" @click="resetFilters">Reset</button>
        </div>

        <div class="table-responsive mt-3">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Country</th>
                        <th>Code</th>
                        <th>Cities</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(state, index) in states.data" :key="state.id">
                        <td>{{ states.from + index }}</td>
                        <td><strong>{{ state.name }}</strong><br /><small class="text-muted">{{ state.slug }}</small></td>
                        <td>{{ state.country?.name || '—' }}</td>
                        <td>{{ state.code || '—' }}</td>
                        <td>{{ state.cities_count }}</td>
                        <td>
                            <span class="badge" :class="state.is_active ? 'bg-success' : 'bg-secondary'">
                                {{ state.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <Link :href="`${appUrl('/admin/states')}/${state.id}/edit`" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="bi bi-pencil"></i>
                            </Link>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeState(state)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!states.data.length">
                        <td colspan="7" class="text-center text-muted py-4">No states added yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between">
            <Pagination :links="states.links" />
        </div>
    </AdminLayout>
</template>
