<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    bookings: Object,
    filters: { type: Object, default: () => ({}) },
    vendors: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    tripTypes: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/taxi/bookings');

const form = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    trip_type: props.filters.trip_type ?? '',
    vendor_id: props.filters.vendor_id ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
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
        draft: 'bg-light text-dark',
        quoted: 'bg-light text-dark',
    };
    return map[status] ?? 'bg-secondary';
}

function pickup(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Taxi Bookings</h2>
                <p class="text-muted mb-0">One-way rides and airport transfers managed by the desk.</p>
            </div>
            <Link :href="`${endpoint}/create`" class="btn btn-svtp"><i class="bi bi-plus-lg me-2"></i>New Taxi Booking</Link>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="filter-search" class="form-label small">Search</label>
                    <input id="filter-search" v-model="form.search" class="form-control form-control-sm" placeholder="Reference, customer or address" />
                </div>
                <div class="col-md-2">
                    <label for="filter-status" class="form-label small">Status</label>
                    <select id="filter-status" v-model="form.status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter-trip" class="form-label small">Trip type</label>
                    <select id="filter-trip" v-model="form.trip_type" class="form-select form-select-sm">
                        <option value="">All trip types</option>
                        <option v-for="option in tripTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filter-vendor" class="form-label small">Vendor</label>
                    <select id="filter-vendor" v-model="form.vendor_id" class="form-select form-select-sm">
                        <option value="">Platform + all vendors</option>
                        <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <div class="flex-fill">
                            <label for="filter-from" class="form-label small">Pickup from</label>
                            <input id="filter-from" v-model="form.date_from" type="date" class="form-control form-control-sm" />
                        </div>
                        <div class="flex-fill">
                            <label for="filter-to" class="form-label small">Pickup to</label>
                            <input id="filter-to" v-model="form.date_to" type="date" class="form-control form-control-sm" />
                        </div>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-svtp" :disabled="form.processing">Apply filters</button>
                    <button v-if="hasFilters" type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                    <span class="ms-auto text-muted small align-self-center">{{ bookings.total }} booking(s)</span>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Trip</th>
                            <th>Vendor</th>
                            <th>Customer</th>
                            <th>Pickup</th>
                            <th>Status</th>
                            <th>Vehicle Type</th>
                            <th>Driver</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="booking in bookings.data" :key="booking.id">
                            <td><Link :href="`${endpoint}/${booking.id}`" class="fw-semibold text-decoration-none">{{ booking.reference }}</Link></td>
                            <td class="text-capitalize">{{ booking.trip_type.replace('_', ' ') }}</td>
                            <td>
                                <span v-if="booking.vendor_profile" class="small">{{ booking.vendor_profile.business_name }}</span>
                                <span v-else class="badge bg-light text-dark border">Platform</span>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ booking.customer_name || 'Guest' }}</div>
                                <div class="small text-muted">{{ booking.customer_phone }}</div>
                            </td>
                            <td>
                                <div>{{ pickup(booking.pickup_at) }}</div>
                                <div class="small text-muted">{{ booking.pickup_address }}</div>
                            </td>
                            <td><span class="badge" :class="statusBadge(booking.status)">{{ booking.status }}</span></td>
                            <td>{{ booking.vehicle_type?.name ?? '—' }}</td>
                            <td>
                                <div v-if="booking.assigned_driver">{{ booking.assigned_driver.first_name }} {{ booking.assigned_driver.last_name }}</div>
                                <span v-else class="small text-muted">Unassigned</span>
                            </td>
                            <td>{{ booking.currency ?? 'INR' }} {{ Number(booking.total_amount ?? 0).toFixed(2) }}</td>
                            <td class="text-end"><Link :href="`${endpoint}/${booking.id}`" class="btn btn-sm btn-outline-primary">Details</Link></td>
                        </tr>
                        <tr v-if="!bookings.data.length"><td colspan="10" class="text-center text-muted py-4">No taxi bookings match these filters.</td></tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="bookings.links" class="px-3 py-2" />
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); background: #101827; color: #e2e8f0; }
.table { --bs-table-bg: transparent; color: inherit; }
.btn.btn-outline-secondary { color: #e2e8f0; border-color: rgba(148, 163, 184, .4); }
</style>
