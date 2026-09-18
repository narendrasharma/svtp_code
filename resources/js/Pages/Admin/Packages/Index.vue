<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import Pagination from '../../../Components/Pagination.vue';

// Props from the controller
const props = defineProps({
    packages: { type: Object, required: true },
    categories: { type: Array, required: true },
    cities: { type: Array, required: true },
    filters: { type: Object, required: true },
    moderationStatuses: { type: Array, default: () => [] },
});

// Reactive copy of incoming filters for UI binding
const localFilters = ref({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    moderation_status: props.filters.moderation_status ?? '',
    owner: props.filters.owner ?? '',
    category: props.filters.category ?? '',
    city: props.filters.city ?? '',
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
// Apply filters – Inertia GET preserving state
// ---------------------------------------------------------------------
function applyFilters() {
    const query = {};

    if (localFilters.value.search) query.search = localFilters.value.search;
    if (localFilters.value.status && localFilters.value.status !== 'all')
        query.status = localFilters.value.status;
    if (localFilters.value.moderation_status) query.moderation_status = localFilters.value.moderation_status;
    if (localFilters.value.owner) query.owner = localFilters.value.owner;
    if (localFilters.value.category) query.category = localFilters.value.category;
    if (localFilters.value.city) query.city = localFilters.value.city;
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
        moderation_status: '',
        owner: '',
        category: '',
        city: '',
        per_page: 10,
        sort: '',
        direction: '',
    };
    applyFilters();
}

// ---------------------------------------------------------------------
// Delete a package (already used elsewhere)
// ---------------------------------------------------------------------
function removePackage(p) {
    if (window.confirm(`Remove ${p.title}?`)) {
        router.delete(`${appUrl('/admin/packages')}/${p.id}`);
    }
}

// ---------------------------------------------------------------------
// Human‑readable date (if you decide to show a Created column later)
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
        <!-- Heading + New Package button -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Packages</h2>
            <Link :href="appUrl('/admin/packages/create')" class="btn btn-svtp">+ New Package</Link>
        </div>

        <!-- Filter toolbar -->
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <!-- Search -->
            <div class="flex-grow-1" style="min-width: 200px;">
                <input
                    type="text"
                    class="form-control form-control-sm"
                    placeholder="Search title, slug, code..."
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

            <!-- Moderation filter -->
            <select
                class="form-select form-select-sm"
                v-model="localFilters.moderation_status"
                @change="applyFilters"
                style="width: 150px;"
            >
                <option value="">All moderation</option>
                <option v-for="s in moderationStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>

            <!-- Owner filter -->
            <select
                class="form-select form-select-sm"
                v-model="localFilters.owner"
                @change="applyFilters"
                style="width: 130px;"
            >
                <option value="">All owners</option>
                <option value="admin">Admin</option>
                <option value="vendor">Vendor</option>
            </select>

            <!-- Category filter -->
            <select
                class="form-select form-select-sm"
                v-model="localFilters.category"
                @change="applyFilters"
                style="width: 150px;"
            >
                <option value="">All categories</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
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
                <option v-for="c in cities" :key="c.id" :value="c.id">
                    {{ c.name }}
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
                        <th>#</th>
                        <th class="cursor-pointer" @click="sort('title')">
                            Name
                            <span v-if="localFilters.sort === 'title'">
                                {{ localFilters.direction === 'asc' ? '↑' : '↓' }}
                            </span>
                        </th>
                        <th>Vendor</th>
                        <th>City</th>
                        <th>Price</th>
                        <th>Moderation</th>
                        <th>Public</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, index) in packages.data" :key="p.id">
                        <td>{{ packages.from + index }}</td>
                        <td>
                            <div class="fw-semibold">{{ p.title }}</div>
                            <div class="small text-muted">{{ p.slug }}</div>
                        </td>
                        <td class="small">
                            <span v-if="p.vendor_profile">{{ p.vendor_profile.business_name }}</span>
                            <span v-else class="text-muted">Admin</span>
                        </td>
                        <td>{{ p.city?.name }}</td>
                        <td>₹{{ p.discounted_price || p.price }}</td>
                        <td>
                            <span class="badge" :class="{
                                'bg-secondary': p.moderation_status === 'draft',
                                'bg-warning text-dark': p.moderation_status === 'pending_review',
                                'bg-success': p.moderation_status === 'approved',
                                'bg-info text-dark': p.moderation_status === 'changes_requested',
                                'bg-danger': p.moderation_status === 'rejected'
                            }">{{ p.moderation_status }}</span>
                        </td>
                        <td>
                            <span class="badge" :class="p.is_active && p.moderation_status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                {{ p.is_active && p.moderation_status === 'approved' ? 'Visible' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <Link
                                class="btn btn-sm btn-outline-primary me-1"
                                :href="`${appUrl('/admin/packages')}/${p.id}`"
                            >
                                <i class="bi bi-eye"></i>
                            </Link>
                            <Link
                                class="btn btn-sm btn-outline-secondary me-1"
                                :href="`${appUrl('/admin/packages')}/${p.id}/edit`"
                            >
                                <i class="bi bi-pencil"></i>
                            </Link>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-danger"
                                @click="removePackage(p)"
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!packages.data.length">
                        <td colspan="8" class="text-center text-muted py-4">No packages added yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-between">
            <Pagination :links="packages.links" />
        </div>
    </AdminLayout>
</template>
