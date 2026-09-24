<script setup>
import { Link } from '@inertiajs/vue3';
import AccountLayout from '../../Layouts/AccountLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import { appUrl } from '../../appUrl';

defineProps({
    stats: Object,
    recentBookings: { type: Array, default: () => [] },
});

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN') : '—';
}
</script>

<template>
    <AccountLayout>
        <SeoHead title="My Account" noindex />
        <div class="container py-5">
            <h1 class="section-title mb-4">Dashboard</h1>


            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3"><div class="glass-card p-3 text-center h-100"><h3 class="text-svtp mb-0">{{ stats.total }}</h3><small class="text-muted">Tour bookings</small></div></div>
                <div class="col-6 col-md-3"><div class="glass-card p-3 text-center h-100"><h3 class="text-svtp mb-0">{{ stats.upcoming }}</h3><small class="text-muted">Upcoming trips</small></div></div>
                <div class="col-6 col-md-3"><div class="glass-card p-3 text-center h-100"><h3 class="text-svtp mb-0">{{ stats.pending }}</h3><small class="text-muted">Pending confirmation</small></div></div>
                <div class="col-6 col-md-3"><div class="glass-card p-3 text-center h-100"><h3 class="text-svtp mb-0">{{ stats.completed }}</h3><small class="text-muted">Completed trips</small></div></div>
            </div>

            <div class="glass-card p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="text-svtp mb-0">Recent Tour Bookings</h5>
                    <Link :href="appUrl('/account/bookings')" class="btn btn-sm btn-outline-svtp">View Tour Bookings</Link>
                </div>
                <div v-if="recentBookings.length" class="table-responsive"><table class="table align-middle mb-0">
                    <thead><tr><th>Reference</th><th>Tour</th><th>Travel Date</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="booking in recentBookings" :key="booking.id">
                            <td class="fw-semibold">{{ booking.booking_reference_id }}</td>
                            <td>{{ booking.package?.title ?? '—' }}</td>
                            <td class="text-nowrap">{{ formatDate(booking.travel_date) }}</td>
                            <td><span class="badge bg-secondary">{{ booking.booking_status }}</span></td>
                            <td><Link :href="appUrl(`/account/bookings/${booking.id}`)" class="btn btn-sm btn-outline-svtp">View</Link></td>
                        </tr>
                    </tbody>
                </table></div>
                <div v-else class="text-center py-4">
                    <p class="text-muted mb-3">No bookings yet — your upcoming adventures will appear here.</p>
                    <a :href="appUrl('/packages')" class="btn btn-svtp">Browse Tours</a>
                </div>
            </div>
        </div>
    </AccountLayout>
</template>
