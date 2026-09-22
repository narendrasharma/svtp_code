<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    cities: { type: Object, required: true },
    countries: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
});

const localFilters = ref({
    search: props.filters.search ?? '',
    country: props.filters.country ?? '',
    state: props.filters.state ?? '',
    status: props.filters.status ?? 'all',
    featured: props.filters.featured ?? '',
    per_page: props.filters.per_page ?? 15,
});

const visibleStates = computed(() => {
    if (!localFilters.value.country) return props.states;
    return props.states.filter(s => String(s.country_id) === String(localFilters.value.country));
});

let debounceTimer = null;
function applyFilters() {
    const query = {};
    if (localFilters.value.search) query.search = localFilters.value.search;
    if (localFilters.value.country) query.country = localFilters.value.country;
    if (localFilters.value.state) query.state = localFilters.value.state;
    if (localFilters.value.status && localFilters.value.status !== 'all') query.status = localFilters.value.status;
    if (localFilters.value.featured) query.featured = localFilters.value.featured;
    if (localFilters.value.per_page) query.per_page = localFilters.value.per_page;
    router.get(window.location.pathname, query, { preserveState: true, replace: true });
}
function debouncedApply() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applyFilters, 300);
}
function resetFilters() {
    localFilters.value = { search: '', country: '', state: '', status: 'all', featured: '', per_page: 15 };
    applyFilters();
}
function removeCity(city) {
    if (window.confirm(`Remove ${city.name}? Only unreferenced cities can be deleted.`)) {
        router.delete(`${appUrl('/admin/cities')}/${city.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Cities</h2>
            <Link :href="appUrl('/admin/cities/create')" class="btn btn-svtp">+ New City</Link>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <div class="flex-grow-1" style="min-width: 200px;">
                <input type="text" class="form-control form-control-sm" placeholder="Search name, slug..."
                    v-model="localFilters.search" @input="debouncedApply" />
            </div>
            <select class="form-select form-select-sm" v-model="localFilters.country" @change="applyFilters" style="width: 170px;">
                <option value="">All countries</option>
                <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.name }}</option>
            </select>
            <select class="form-select form-select-sm" v-model="localFilters.state" @change="applyFilters" style="width: 170px;">
                <option value="">All states</option>
                <option v-for="state in visibleStates" :key="state.id" :value="state.id">{{ state.name }}</option>
            </select>
            <select class="form-select form-select-sm" v-model="localFilters.status" @change="applyFilters" style="width: 120px;">
                <option value="all">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <select class="form-select form-select-sm" v-model="localFilters.featured" @change="applyFilters" style="width: 130px;">
                <option value="">All cities</option>
                <option value="yes">Featured</option>
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
                        <th>State</th>
                        <th>Destinations</th>
                        <th>Properties</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(city, index) in cities.data" :key="city.id">
                        <td>{{ cities.from + index }}</td>
                        <td>
                            <strong>{{ city.name }}</strong><br /><small class="text-muted">{{ city.slug }}</small>
                            <span v-if="city.is_featured" class="badge bg-warning text-dark ms-1">Featured</span>
                        </td>
                        <td>{{ city.country?.name || '—' }}</td>
                        <td>{{ city.state?.name || '—' }}</td>
                        <td>{{ city.destinations_count }}</td>
                        <td>{{ city.properties_count }}</td>
                        <td>
                            <span class="badge" :class="city.is_active ? 'bg-success' : 'bg-secondary'">
                                {{ city.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <Link :href="`${appUrl('/admin/cities')}/${city.id}/edit`" class="btn btn-sm btn-outline-secondary me-2">
                                <i class="bi bi-pencil"></i>
                            </Link>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeCity(city)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!cities.data.length">
                        <td colspan="8" class="text-center text-muted py-4">No cities added yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between">
            <Pagination :links="cities.links" />
        </div>
    </AdminLayout>
</template>
