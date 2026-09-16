<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import Pagination from "@/Components/Pagination.vue";

const props = defineProps({
    destinations: { type: Object, required: true },
    filters: { type: Object, required: true },
});

// Reactive copy of incoming filters
const localFilters = ref({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    per_page: props.filters.per_page ?? 10,
    sort: props.filters.sort ?? '',
    direction: props.filters.direction ?? '',
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
// Apply filters – Inertia GET
// ---------------------------------------------------------------------
function applyFilters() {
    const query = {};

    if (localFilters.value.search) query.search = localFilters.value.search;
    if (localFilters.value.status && localFilters.value.status !== 'all')
        query.status = localFilters.value.status;
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
        status: 'all',
        per_page: 10,
        sort: '',
        direction: '',
    };
    applyFilters();
}

// ---------------------------------------------------------------------
// Delete handler (already defined)
 // ---------------------------------------------------------------------
function removeDestination(destination) {
    if (window.confirm(`Remove ${destination.name} and its places?`)) {
        router.delete(`${appUrl('/admin/destinations')}/${destination.id}`);
    }
}

// ---------------------------------------------------------------------
// Human‑readable date (if needed later)
// ---------------------------------------------------------------------
function formatCreated(dateString) {
    if (!dateString) return '';
    const d = new Date(dateString);
    return d.toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        hour12: true,
    });
}
</script>

<template>
    <AdminLayout>
        <!-- Heading + New Destination button -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Destinations</h2>
            <Link :href="appUrl('/admin/destinations/create')" class="btn btn-svtp">+ New Destination</Link>
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

            <!-- Status filter -->
            <select
                class="form-select form-select-sm"
                v-model="localFilters.status"
                @change="applyFilters"
                style="width: 120px;"
            >
                <option value="all">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <!-- Per‑page selector -->
            <select
                class="form-select form-select-sm"
                v-model.number="localFilters.per_page"
                @change="applyFilters"
                style="width: 100px;"
            >
                <option :value="10">10</option>
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
                        <th>City</th>
                        <th>Places</th>
                        <th>Tours</th>
                        <th class="cursor-pointer" @click="sort('is_active')">
                            Status
                            <span v-if="localFilters.sort === 'is_active'">
                                {{ localFilters.direction === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(destination, index) in destinations.data" :key="destination.id">
                        <td>{{ destinations.from + index }}</td>
                        <td>
                            <strong>{{ destination.name }}</strong><br />
                            <small class="text-muted">{{ destination.slug }}</small>
                        </td>
                        <td>{{ destination.city?.name || '—' }}</td>
                        <td>{{ destination.places_count }}</td>
                        <td>{{ destination.tour_packages_count }}</td>
                        <td>
                            <span class="badge" :class="destination.is_active ? 'bg-success' : 'bg-secondary'">
                                {{ destination.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <Link
                                :href="`${appUrl('/admin/destinations')}/${destination.id}/edit`"
                                class="btn btn-sm btn-outline-secondary me-2"
                            >
                                <i class="bi bi-pencil"></i>
                            </Link>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                @click="removeDestination(destination)"
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!destinations.data.length">
                        <td colspan="7" class="text-center text-muted py-4">No destinations added yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between">
            <Pagination :links="destinations.links" />
        </div>
    </AdminLayout>
</template>
