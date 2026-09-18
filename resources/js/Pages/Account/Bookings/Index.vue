<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';
import AccountNav from '../../../Components/AccountNav.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    bookings: Object,
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

const endpoint = appUrl('/account/bookings');
const filters = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    scope: props.filters.scope ?? '',
});

function applyFilters() {
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}
function clearFilters() {
    filters.reset();
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}
function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN') : '—';
}
</script>

<template>
    <AppLayout>
        <SeoHead title="My Bookings" noindex />
        <div class="container py-5">
            <p class="section-eyebrow">My account</p>
            <h1 class="section-title mb-4">My Bookings</h1>
            <AccountNav active="bookings" />

            <form class="glass-card p-3 mb-3" @submit.prevent="applyFilters">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4"><label for="booking-search" class="form-label small">Booking reference</label><input id="booking-search" v-model="filters.search" class="form-control form-control-sm" placeholder="BK-…" /></div>
                    <div class="col-md-3"><label for="booking-status" class="form-label small">Status</label><select id="booking-status" v-model="filters.status" class="form-select form-select-sm"><option value="">All statuses</option><option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select></div>
                    <div class="col-md-3"><label for="booking-scope" class="form-label small">When</label><select id="booking-scope" v-model="filters.scope" class="form-select form-select-sm"><option value="">All trips</option><option value="upcoming">Upcoming</option><option value="past">Past</option></select></div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-sm btn-svtp flex-fill" :disabled="filters.processing">Filter</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                    </div>
                </div>
            </form>

            <div v-if="bookings.data.length" class="glass-card p-3 p-md-4">
                <div class="table-responsive"><table class="table align-middle mb-0">
                    <thead><tr><th>Reference</th><th>Tour</th><th>Travel Date</th><th>Travellers</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="booking in bookings.data" :key="booking.id">
                            <td class="fw-semibold">{{ booking.booking_reference_id }}</td>
                            <td>{{ booking.package?.title ?? '—' }}</td>
                            <td class="text-nowrap">{{ formatDate(booking.travel_date) }}</td>
                            <td>{{ Number(booking.total_adults || 0) + Number(booking.total_children || 0) }}</td>
                            <td class="text-nowrap">₹{{ Number(booking.total_amount).toLocaleString('en-IN') }}</td>
                            <td><span class="badge bg-secondary">{{ booking.booking_status }}</span></td>
                            <td><Link :href="`${endpoint}/${booking.id}`" class="btn btn-sm btn-outline-svtp">View</Link></td>
                        </tr>
                    </tbody>
                </table></div>
                <Pagination :links="bookings.links" />
            </div>
            <div v-else class="glass-card p-5 text-center">
                <p class="text-muted mb-3">No bookings found. Your adventures start here.</p>
                <a :href="appUrl('/packages')" class="btn btn-svtp">Browse Tours</a>
            </div>
        </div>
    </AppLayout>
</template>
