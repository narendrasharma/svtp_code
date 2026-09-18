<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    bookings: Object,
    filters: { type: Object, default: () => ({}) },
    packages: { type: Array, default: () => [] },
    vendors: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    paymentStatuses: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/bookings');
const filters = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    payment_status: props.filters.payment_status ?? '',
    package_id: props.filters.package_id ?? '',
    vendor: props.filters.vendor ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const hasActiveFilters = computed(() => Object.values(filters.data()).some(value => value !== '' && value !== null));

function applyFilters() {
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}
function clearFilters() {
    router.get(endpoint);
}
function cancelBooking(booking) {
    if (!window.confirm(`Cancel booking ${booking.booking_reference_id}? The record is kept for your reports.`)) return;
    router.patch(`${endpoint}/${booking.id}/status`, { booking_status: 'cancelled', note: 'Cancelled by admin' }, { preserveScroll: true });
}
function statusBadge(status) {
    return {
        pending: 'bg-warning text-dark',
        confirmed: 'bg-info text-dark',
        completed: 'bg-success',
        cancelled: 'bg-secondary',
    }[status] ?? 'bg-secondary';
}
function paymentBadge(status) {
    return {
        unpaid: 'bg-warning text-dark',
        partially_paid: 'bg-info text-dark',
        paid: 'bg-success',
        refunded: 'bg-secondary',
    }[status] ?? 'bg-secondary';
}
function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN') : '—';
}
function guests(booking) {
    return Number(booking.total_adults || 0) + Number(booking.total_children || 0);
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Tour Bookings</h2>
                <p class="text-muted mb-0">Phone, WhatsApp and website bookings for tour packages.</p>
            </div>
            <Link :href="`${endpoint}/create`" class="btn btn-svtp">New Manual Booking</Link>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><label for="filter-search" class="form-label small">Reference / customer</label><input id="filter-search" v-model="filters.search" class="form-control form-control-sm" placeholder="BK-…, name, email, phone" /></div>
                <div class="col-md-2"><label for="filter-status" class="form-label small">Status</label><select id="filter-status" v-model="filters.status" class="form-select form-select-sm"><option value="">All statuses</option><option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                <div class="col-md-2"><label for="filter-payment" class="form-label small">Payment</label><select id="filter-payment" v-model="filters.payment_status" class="form-select form-select-sm"><option value="">All payments</option><option v-for="option in paymentStatuses" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                <div class="col-md-2"><label for="filter-package" class="form-label small">Tour package</label><select id="filter-package" v-model="filters.package_id" class="form-select form-select-sm"><option value="">All packages</option><option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.title }}</option></select></div>
                <div class="col-md-2"><label for="filter-vendor" class="form-label small">Vendor</label><select id="filter-vendor" v-model="filters.vendor" class="form-select form-select-sm"><option value="">All vendors</option><option value="admin">Admin-owned</option><option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option></select></div>
                <div class="col-md-3"><div class="d-flex gap-2">
                    <div class="flex-fill"><label for="filter-from" class="form-label small">Travel from</label><input id="filter-from" v-model="filters.date_from" type="date" class="form-control form-control-sm" /></div>
                    <div class="flex-fill"><label for="filter-to" class="form-label small">Travel to</label><input id="filter-to" v-model="filters.date_to" type="date" class="form-control form-control-sm" /></div>
                </div></div>
                <div class="col-12 d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-svtp" :disabled="filters.processing">Apply Filters</button>
                    <button v-if="hasActiveFilters" type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                    <span class="text-muted small ms-auto align-self-center">{{ bookings.total }} booking(s)</span>
                </div>
            </div>
        </form>

        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Reference</th><th>Tour</th><th>Vendor</th><th>Customer</th><th>Travel Date</th><th>Guests</th><th>Total</th><th>Vendor Earning</th><th>Status</th><th>Payment</th><th>Source</th><th>Booked</th><th></th></tr></thead>
            <tbody>
                <tr v-for="booking in bookings.data" :key="booking.id">
                    <td><Link :href="`${endpoint}/${booking.id}`" class="text-decoration-none fw-semibold">{{ booking.booking_reference_id }}</Link></td>
                    <td>{{ booking.package?.title ?? '—' }}</td>
                    <td><span v-if="booking.vendor_profile" class="small">{{ booking.vendor_profile.business_name }}</span><span v-else class="badge bg-light text-dark border">Admin-owned</span></td>
                    <td><strong>{{ booking.customer_name || booking.user?.name || 'Guest' }}</strong><div class="small text-muted">{{ booking.customer_phone }}</div></td>
                    <td class="text-nowrap">{{ formatDate(booking.travel_date) }}</td>
                    <td>{{ guests(booking) }}</td>
                    <td class="text-nowrap">{{ booking.currency }} {{ Number(booking.total_amount).toFixed(2) }}</td>
                    <td class="text-nowrap small">{{ booking.vendor_profile ? `${booking.currency} ${Number(booking.vendor_earning_amount ?? 0).toFixed(2)}` : '—' }}</td>
                    <td><span class="badge" :class="statusBadge(booking.booking_status)">{{ booking.booking_status }}</span></td>
                    <td><span class="badge" :class="paymentBadge(booking.payment_status)">{{ booking.payment_status }}</span></td>
                    <td><span class="text-muted small">{{ booking.source }}</span></td>
                    <td class="text-nowrap small text-muted">{{ formatDate(booking.created_at) }}</td>
                    <td class="text-nowrap">
                        <Link :href="`${endpoint}/${booking.id}`" class="btn btn-sm btn-outline-primary me-2">View</Link>
                        <button v-if="booking.booking_status !== 'cancelled' && booking.booking_status !== 'completed'" type="button" class="btn btn-sm btn-outline-danger" @click="cancelBooking(booking)">Cancel</button>
                    </td>
                </tr>
                <tr v-if="!bookings.data.length"><td colspan="13" class="text-center text-muted py-4">No bookings match these filters.</td></tr>
            </tbody>
        </table></div>
        <Pagination :links="bookings.links" />
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
</style>
