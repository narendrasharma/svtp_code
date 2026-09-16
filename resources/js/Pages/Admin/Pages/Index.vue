<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import Pagination from '@/Components/Pagination.vue';

// Props coming from the controller
const props = defineProps({
    pages: {
        type: Object,
        required: true,
    },
    templates: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

// Reactive copy of the incoming filters so we can bind inputs
const localFilters = ref({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    template: props.filters.template ?? '',
    per_page: props.filters.per_page ?? 25,
    sort: props.filters.sort ?? '',
    direction: props.filters.direction ?? '',
});

// ---------------------------------------------------------------------
// Debounce helper (no external library)
// ---------------------------------------------------------------------
let debounceTimer = null;
function debounce(fn, delay = 300) {
    return (...args) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fn(...args), delay);
    };
}

// ---------------------------------------------------------------------
// Apply filters – performs an Inertia GET request preserving state
// ---------------------------------------------------------------------
function applyFilters() {
    // Build query object, stripping empty values to keep URLs tidy
    const query = {};

    if (localFilters.value.search) query.search = localFilters.value.search;
    if (localFilters.value.status && localFilters.value.status !== 'all')
        query.status = localFilters.value.status;
    if (localFilters.value.template) query.template = localFilters.value.template;
    if (localFilters.value.per_page) query.per_page = localFilters.value.per_page;
    if (localFilters.value.sort) query.sort = localFilters.value.sort;
    if (localFilters.value.direction) query.direction = localFilters.value.direction;

    router.get(window.location.pathname, query, {
        preserveState: true,
        replace: true,
    });
}

// Debounced version for the search input
const debouncedApplyFilters = debounce(applyFilters, 300);

// ---------------------------------------------------------------------
// Sorting handler – toggles direction when same column is clicked
// ---------------------------------------------------------------------
function sort(column) {
    if (localFilters.value.sort === column) {
        // toggle direction
        localFilters.value.direction =
            localFilters.value.direction === 'asc' ? 'desc' : 'asc';
    } else {
        localFilters.value.sort = column;
        localFilters.value.direction = 'asc';
    }
    applyFilters();
}

// ---------------------------------------------------------------------
// Reset / clear all filters
// ---------------------------------------------------------------------
function resetFilters() {
    localFilters.value = {
        search: '',
        status: 'all',
        template: '',
        per_page: 25,
        sort: '',
        direction: '',
    };
    applyFilters();
}

// ---------------------------------------------------------------------
// Date formatting for the Created column
// ---------------------------------------------------------------------
function formatCreated(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    // Example: 14 Sep 2026, 11:28 PM
    return date.toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        hour12: true,
    });
}

// ---------------------------------------------------------------------
// Delete a page – uses Inertia router.delete with confirmation
// ---------------------------------------------------------------------
function removePage(page) {
    if (!window.confirm('Are you sure you want to delete this page?')) {
        return;
    }

    // Assuming the standard RESTful destroy route: /admin/pages/{id}
    const url = appUrl(`/admin/pages/${page.id}`);

    router.delete(url, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout>
        <!-- Heading + Primary Action (moved above filters) -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Pages</h2>
            <Link :href="appUrl('/admin/pages/create')" class="btn btn-svtp">+ New Page</Link>
        </div>

        <!-- Toolbar (filters) -->
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <!-- Search -->
            <div class="flex-grow-1" style="min-width: 200px;">
                <input
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Search title, slug, meta title..."
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

            <!-- Template filter -->
            <select
                class="form-select form-select-sm"
                v-model="localFilters.template"
                @change="applyFilters"
                style="width: 150px;"
            >
                <option value="">All templates</option>
                <option v-for="tpl in templates" :key="tpl" :value="tpl">
                    {{ tpl }}
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
                        <th class="cursor-pointer" @click="sort('title')">
                            Title
                            <span v-if="localFilters.sort === 'title'">
                                {{ localFilters.direction === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th class="cursor-pointer" @click="sort('template')">
                            Template
                            <span v-if="localFilters.sort === 'template'">
                                {{ localFilters.direction === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th class="cursor-pointer" @click="sort('sort_order')">
                            Order
                            <span v-if="localFilters.sort === 'sort_order'">
                                {{ localFilters.direction === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th class="cursor-pointer" @click="sort('is_active')">
                            Status
                            <span v-if="localFilters.sort === 'is_active'">
                                {{ localFilters.direction === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th class="cursor-pointer" @click="sort('created_at')">
                            Created
                            <span v-if="localFilters.sort === 'created_at'">
                                {{ localFilters.direction === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(page, index) in pages.data" :key="page.id">
                        <td>
                            <strong>{{ page.title }}</strong><br />
                            <small class="text-muted">{{ page.slug }}</small>
                        </td>
                        <td>{{ page.template }}</td>
                        <td>{{ page.sort_order }}</td>
                        <td>
                            <span class="badge" :class="page.is_active ? 'bg-success' : 'bg-secondary'">
                                {{ page.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>{{ formatCreated(page.created_at) }}</td>
                        <td class="text-end">
                            <!-- View public page -->
                            <Link
                                :href="appUrl(`/${page.slug}`)"
                                class="btn btn-sm btn-outline-primary me-2"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <i class="bi bi-eye"></i>
                            </Link>

                            <!-- Edit page -->
                            <Link
                                :href="`${appUrl('/admin/pages')}/${page.id}/edit`"
                                class="btn btn-sm btn-outline-secondary me-2"
                            >
                                <i class="bi bi-pencil"></i>
                            </Link>

                            <!-- Delete page -->
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removePage(page)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!pages.data.length">
                        <td colspan="6" class="text-center text-muted py-4">No pages created yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between">
            <Pagination :links="pages.links" />
        </div>
    </AdminLayout>
</template>
