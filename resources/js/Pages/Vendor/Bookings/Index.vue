<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import { appUrl } from '../../../appUrl';
import Pagination from '../../../Components/Pagination.vue';

const props = defineProps({
    bookings: { type: Object, required: true },
    filters: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    paymentStatuses: { type: Array, default: () => [] },
    tours: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
});

const endpoint = appUrl('/vendor/bookings');

const localFilters = ref({
    search: props.filters.search ?? '',
    booking_status: props.filters.booking_status ?? '',
    payment_status: props.filters.payment_status ?? '',
    package_id: props.filters.package_id ?? '',
    sort: props.filters.sort ?? 'newest',
    per_page: props.filters.per_page ?? 10,
});

function applyFilters() {
    const query = {};
    if (localFilters.value.search) query.search = localFilters.value.search;
    if (localFilters.value.booking_status) query.booking_status = localFilters.value.booking_status;
    if (localFilters.value.payment_status) query.payment_status = localFilters.value.payment_status;
    if (localFilters.value.package_id) query.package_id = localFilters.value.package_id;
    if (localFilters.value.sort && localFilters.value.sort !== 'newest') query.sort = localFilters.value.sort;
    if (localFilters.value.per_page) query.per_page = localFilters.value.per_page;
    router.get(endpoint, query, { preserveState: true, replace: true });
}
function resetFilters() {
    localFilters.value = { search: '', booking_status: '', payment_status: '', package_id: '', sort: 'newest', per_page: 10 };
    applyFilters();
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
function money(value) {
    return `₹${Number(value ?? 0).toFixed(2)}`;
}
</script>

<template>
    <VendorLayout>
        <div class="mb-3 d-flex flex-wrap justify-content-between align-items-end gap-2">
            <div>
            <h2 class="mb-1">Customer Bookings</h2>
            <p class="text-muted small mb-0">Bookings assigned to your business. Read-only — status, payment and payout actions stay with the platform.</p>
            </div>
            <Link :href="appUrl('/vendor/bookings/create')" class="btn btn-svtp btn-sm"><i class="bi bi-plus-lg me-1"></i>Sell Offline</Link>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100"><div class="text-muted small">Assigned bookings</div><div class="fs-4 fw-bold">{{ summary.assigned_bookings }}</div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100"><div class="text-muted small">Paid booking gross</div><div class="fs-4 fw-bold">{{ money(summary.paid_gross) }}</div><div class="text-muted small">Paid, non-cancelled only</div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100"><div class="text-muted small">Platform commission</div><div class="fs-4 fw-bold">{{ money(summary.platform_commission) }}</div><div class="text-muted small">Retained by platform</div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100"><div class="text-muted small">Recorded vendor earnings</div><div class="fs-4 fw-bold">{{ money(summary.recorded_vendor_earnings) }}</div><div class="text-muted small">Paid, non-cancelled only</div></div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <input type="text" class="form-control form-control-sm" placeholder="Search reference, name, email..." v-model="localFilters.search" @keyup.enter="applyFilters" style="max-width: 260px;" />
            <select class="form-select form-select-sm" v-model="localFilters.booking_status" @change="applyFilters" style="width: 150px;">
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select class="form-select form-select-sm" v-model="localFilters.payment_status" @change="applyFilters" style="width: 150px;">
                <option value="">All payments</option>
                <option v-for="s in paymentStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select class="form-select form-select-sm" v-model="localFilters.package_id" @change="applyFilters" style="width: 170px;">
                <option value="">All tours</option>
                <option v-for="tour in tours" :key="tour.id" :value="tour.id">{{ tour.title }}</option>
            </select>
            <select class="form-select form-select-sm" v-model="localFilters.sort" @change="applyFilters" style="width: 120px;">
                <option value="newest">Newest</option>
                <option value="oldest">Oldest</option>
            </select>
            <select class="form-select form-select-sm" v-model.number="localFilters.per_page" @change="applyFilters" style="width: 90px;">
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" @click="applyFilters">Filter</button>
            <button class="btn btn-sm btn-outline-secondary" @click="resetFilters">Reset</button>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Tour</th>
                        <th>Customer</th>
                        <th>Travel Date</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">My Earning</th>
                        <th>Booked</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="booking in bookings.data" :key="booking.id">
                        <td class="fw-semibold text-nowrap">{{ booking.booking_reference_id }}</td>
                        <td class="small">{{ booking.package?.title ?? '—' }}</td>
                        <td class="small">{{ booking.customer_name ?? 'Guest' }}</td>
                        <td class="small text-nowrap">{{ formatDate(booking.travel_date) }}</td>
                        <td><span class="badge" :class="statusBadge(booking.booking_status)">{{ booking.booking_status }}</span></td>
                        <td><span class="badge" :class="paymentBadge(booking.payment_status)">{{ booking.payment_status }}</span></td>
                        <td class="small text-end text-nowrap">{{ money(booking.gross_amount ?? booking.total_amount) }}</td>
                        <td class="small text-end text-nowrap">{{ money(booking.vendor_earning_amount) }}</td>
                        <td class="small text-muted text-nowrap">{{ formatDate(booking.created_at) }}</td>
                        <td class="text-end"><Link :href="`${endpoint}/${booking.id}`" class="btn btn-sm btn-outline-primary">View</Link></td>
                    </tr>
                    <tr v-if="!bookings.data.length">
                        <td colspan="10" class="text-center text-muted py-4">No bookings assigned to you yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination :links="bookings.links" />
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
</style>
