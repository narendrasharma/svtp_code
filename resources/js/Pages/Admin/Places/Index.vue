<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    places:       { type: Object, required: true },
    destinations: { type: Array, required: true },
    cities:       { type: Array, required: true },
    filters:      { type: Object, required: true },
});

// Reactive copy of incoming filters for UI binding
const localFilters = ref({
    search:       props.filters.search ?? '',
    destination:  props.filters.destination ?? '',
    city:         props.filters.city ?? '',
    per_page:     props.filters.per_page ?? 15,
    sort:         props.filters.sort ?? '',
    direction:    props.filters.direction ?? '',
});

// ---------------------------------------------------------------------
// Debounce helper
// ---------------------------------------------------------------------
let debounceTimer = null;
function debounce(fn, delay = 300) {
    return (...args) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fn(...args), delay);
    };
}

// ---------------------------------------------------------------------
// Apply filters – Inertia GET preserving state
// ---------------------------------------------------------------------
function applyFilters() {
    const query = {};

    if (localFilters.value.search) query.search = localFilters.value.search;
    if (localFilters.value.destination) query.destination = localFilters.value.destination;
    if (localFilters.value.city) query.city = localFilters.value.city;
    if (localFilters.value.per_page) query.per_page = localFilters.value.per_page;
    if (localFilters.value.sort) query.sort = localFilters.value.sort;
    if (localFilters.value.direction) query.direction = localFilters.value.direction;

    router.get(window.location.pathname, query, {
        preserveState: true,
        replace: true,
    });
}

// Debounced search
const debouncedApplyFilters = debounce(applyFilters, 300);

// ---------------------------------------------------------------------
// Sorting handler
// ---------------------------------------------------------------------
function sort(column) {
    if (localFilters.value.sort === column) {
        localFilters.value.direction =
            localFilters.value.direction === 'asc' ? 'desc' : 'asc';
    } else {
        localFilters.value.sort = column;
        localFilters.value.direction = 'asc';
    }
    applyFilters();
}

// ---------------------------------------------------------------------
// Reset filters
// ---------------------------------------------------------------------
function resetFilters() {
    localFilters.value = {
        search: '',
        destination: '',
        city: '',
        per_page: 15,
        sort: '',
        direction: '',
    };
    applyFilters();
}

// ---------------------------------------------------------------------
// Delete handler (already defined)
 // ---------------------------------------------------------------------
function removePlace(place) {
    if (window.confirm(`Remove ${place.name}?`)) router.delete(`${appUrl('/admin/places')}/${place.id}`);
}
</script>

<template>
    <AdminLayout>
        <!-- Heading + New Place button -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Places / Attractions</h2>
            <Link :href="appUrl('/admin/places/create')" class="btn btn-svtp">+ New Place</Link>
        </div>

        <!-- Filter toolbar -->
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <!-- Search -->
            <div class="flex-grow-1" style="min-width: 200px;">
                <input
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Search name, slug..."
                    v-model="localFilters.search"
                    @input="debouncedApplyFilters"
                />
            </div>

            <!-- Destination filter -->
            <select
                class="form-select form-select-sm"
                v-model="localFilters.destination"
                @change="applyFilters"
                style="width: 150px;"
            >
                <option value="">All destinations</option>
                <option v-for="dest in destinations" :key="dest.id" :value="dest.id">
                    {{ dest.name }}
                </option>
            </select>

            <!-- City filter -->
            <select
                class="form-select form-select-sm"
                v-model="localFilters.city"
                @change="applyFilters"
                style="width: 150px;"
            >
                <option value="">All cities</option>
                <option v-for="city in cities" :key="city.id" :value="city.id">
                    {{ city.name }}
                </option>
            </select>

            <!-- Per‑page selector -->
            <select
                class="form-select form-select-sm"
                v-model.number="localFilters.per_page"
                @change="applyFilters"
                style="width: 100px;"
            >
                <option :value="10">10</option>
                <option :value="15">15</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>

            <!-- Reset button -->
            <button class="btn btn-outline-secondary btn-sm" @click="resetFilters">
                Reset
            </button>
        </div>

        <!-- Table -->
        <div class="table-responsive mt-3">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="cursor-pointer" @click="sort('name')">
                            Name
                            <span v-if="localFilters.sort === 'name'">
                                {{ localFilters.direction === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th>Slug</th>
                        <th>Destination</th>
                        <th>City</th>
                        <th>Tours</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(place, index) in places.data" :key="place.id">
                        <td>{{ places.from + index }}</td>
                        <td><strong>{{ place.name }}</strong></td>
                        <td>{{ place.slug }}</td>
                        <td>{{ place.destination?.name }}</td>
                        <td>{{ place.destination?.city?.name || '—' }}</td>
                        <td>{{ place.tour_packages_count }}</td>
                        <td class="text-end">
                            <Link
                                :href="`${appUrl('/admin/places')}/${place.id}/edit`"
                                class="btn btn-sm btn-outline-secondary me-2"
                            >
                                <i class="bi bi-pencil"></i>
                            </Link>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                @click="removePlace(place)"
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!places.data.length">
                        <td colspan="7" class="text-center text-muted py-4">No places added yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between">
            <Pagination :links="places.links" />
        </div>
    </AdminLayout>
</template>
