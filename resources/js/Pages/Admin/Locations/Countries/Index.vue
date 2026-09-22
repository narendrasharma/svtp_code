<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    countries: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const localFilters = ref({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    per_page: props.filters.per_page ?? 15,
});

let debounceTimer = null;
function applyFilters() {
    const query = {};
    if (localFilters.value.search) query.search = localFilters.value.search;
    if (localFilters.value.status && localFilters.value.status !== 'all') query.status = localFilters.value.status;
    if (localFilters.value.per_page) query.per_page = localFilters.value.per_page;
    router.get(window.location.pathname, query, { preserveState: true, replace: true });
}
function debouncedApply() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
}
function resetFilters() {
    localFilters.value = { search: '', status: 'all', per_page: 15 };
    applyFilters();
}
function removeCountry(country) {
    if (window.confirm(`Remove ${country.name}? Only unreferenced countries can be deleted.`)) {
        router.delete(`${appUrl('/admin/countries')}/${country.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Countries</h2>
            <Link :href="appUrl('/admin/countries/create')" class="btn btn-svtp">+ New Country</Link>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <div class="flex-grow-1" style="min-width: 200px;">
                <input type="text" class="form-control form-control-sm" placeholder="Search name, ISO2, ISO3..."
                    v-model="localFilters.search" @input="debouncedApply" />
            </div>
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
                        <th>ISO</th>
                        <th>Phone</th>
                        <th>Currency</th>
                        <th>States</th>
                        <th>Cities</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(country, index) in countries.data" :key="country.id">
                        <td>{{ countries.from + index }}</td>
                        <td><strong>{{ country.name }}</strong></td>
                        <td><small class="text-muted">{{ country.iso2 }}{{ country.iso3 ? ` / ${country.iso3}` : '' }}</small></td>
                        <td>{{ country.phone_code || '—' }}</td>
                        <td>{{ country.currency_code || '—' }}</td>
                        <td>{{ country.states_count }}</td>
                        <td>{{ country.cities_count }}</td>
                        <td>
                            <span class="badge" :class="country.is_active ? 'bg-success' : 'bg-secondary'">
                                {{ country.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <Link :href="`${appUrl('/admin/countries')}/${country.id}/edit`" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="bi bi-pencil"></i>
                            </Link>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeCountry(country)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!countries.data.length">
                        <td colspan="9" class="text-center text-muted py-4">No countries added yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between">
            <Pagination :links="countries.links" />
        </div>
    </AdminLayout>
</template>
