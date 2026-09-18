<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Pagination from '../../../../Components/Pagination.vue';
import { computed } from 'vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    vehicles: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

const endpoint = appUrl('/vendor/taxi/vehicles');

const form = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const hasFilters = computed(() => Object.values(form.data()).some(value => value !== '' && value !== null));

function applyFilters() {
    form.get(endpoint, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    router.get(endpoint, {}, { preserveScroll: true });
}

function statusBadge(status) {
    const map = {
        available: 'bg-success',
        assigned: 'bg-primary',
        on_trip: 'bg-info text-dark',
        maintenance: 'bg-warning text-dark',
        out_of_service: 'bg-secondary',
    };
    return map[status] ?? 'bg-secondary';
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">My taxi vehicles</h2>
                <p class="text-muted mb-0">Manage the vehicles you dispatch for rides.</p>
            </div>
            <Link :href="appUrl('/vendor/taxi/vehicles/create')" class="btn btn-svtp"><i class="bi bi-plus-lg me-2"></i>Add vehicle</Link>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small">Search</label>
                    <input v-model="form.search" type="text" class="form-control" placeholder="Reference, name or registration" />
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Status</label>
                    <select v-model="form.status" class="form-select">
                        <option value="">All statuses</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-svtp" :disabled="form.processing">Apply</button>
                    <button v-if="hasFilters" type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Reference</th><th>Name</th><th>Status</th><th>Capacity</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="vehicle in vehicles.data" :key="vehicle.id">
                            <td><Link :href="appUrl(`/vendor/taxi/vehicles/${vehicle.id}`)" class="fw-semibold text-decoration-none">{{ vehicle.reference }}</Link></td>
                            <td>
                                <div class="fw-semibold">{{ vehicle.name }}</div>
                                <div class="small text-muted">{{ vehicle.registration_number }}</div>
                                <div class="small text-muted">{{ vehicle.vehicle_type?.name ?? 'Uncategorised' }}</div>
                            </td>
                            <td><span class="badge text-uppercase" :class="statusBadge(vehicle.status)">{{ vehicle.status }}</span></td>
                            <td>{{ vehicle.passenger_capacity }} pax · {{ vehicle.luggage_capacity ?? 0 }} bags</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <Link :href="appUrl(`/vendor/taxi/vehicles/${vehicle.id}`)" class="btn btn-outline-secondary">View</Link>
                                    <Link :href="appUrl(`/vendor/taxi/vehicles/${vehicle.id}/edit`)" class="btn btn-outline-secondary">Edit</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!vehicles.data.length"><td colspan="5" class="text-center text-muted py-4">No vehicles found.</td></tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="vehicles.links" class="p-3" />
        </div>
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 1.25rem; border: 1px solid #e2e8f0; }
</style>
