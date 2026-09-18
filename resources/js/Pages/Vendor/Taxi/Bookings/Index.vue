<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Pagination from '../../../../Components/Pagination.vue';
import { computed } from 'vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    bookings: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

const endpoint = appUrl('/vendor/taxi/bookings');

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
        confirmed: 'bg-info text-dark',
        driver_assigned: 'bg-primary',
        en_route: 'bg-warning text-dark',
        arrived: 'bg-warning text-dark',
        passenger_on_board: 'bg-success',
        completed: 'bg-success',
        cancelled: 'bg-secondary',
        no_show: 'bg-secondary',
    };
    return map[status] ?? 'bg-secondary';
}

function pickup(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Taxi bookings</h2>
                <p class="text-muted mb-0">Manage your upcoming rides.</p>
            </div>
            <Link :href="appUrl('/vendor/taxi/bookings/create')" class="btn btn-svtp"><i class="bi bi-plus-lg me-2"></i>New booking</Link>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small">Search</label>
                    <input v-model="form.search" type="text" class="form-control form-control-sm" placeholder="Reference, customer, pickup" />
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Status</label>
                    <select v-model="form.status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-svtp" :disabled="form.processing">Apply filters</button>
                    <button v-if="hasFilters" type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Reference</th><th>Pickup</th><th>Customer</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="booking in bookings.data" :key="booking.id">
                            <td><Link :href="appUrl(`/vendor/taxi/bookings/${booking.id}`)" class="fw-semibold text-decoration-none">{{ booking.reference }}</Link></td>
                            <td>
                                <div>{{ pickup(booking.pickup_at) }}</div>
                                <div class="small text-muted">{{ booking.pickup_address }}</div>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ booking.customer_name || 'Guest' }}</div>
                                <div class="small text-muted">{{ booking.customer_phone }}</div>
                            </td>
                            <td><span class="badge" :class="statusBadge(booking.status)">{{ booking.status }}</span></td>
                            <td class="text-end">
                                <Link :href="appUrl(`/vendor/taxi/bookings/${booking.id}`)" class="btn btn-sm btn-outline-secondary">Details</Link>
                            </td>
                        </tr>
                        <tr v-if="!bookings.data.length"><td colspan="5" class="text-center text-muted py-4">No bookings found.</td></tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="bookings.links" class="p-3" />
        </div>
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid #e2e8f0; }
.btn.btn-outline-secondary { border-color: #cbd5e1; }
</style>
