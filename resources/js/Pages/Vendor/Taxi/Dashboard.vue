<script setup>
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    cards: { type: Object, default: () => ({}) },
    todayRides: { type: Array, default: () => [] },
    upcomingRides: { type: Array, default: () => [] },
    unassignedRides: { type: Array, default: () => [] },
});

const cardList = computed(() => ([
    { key: 'today', label: "Today's Pickups", icon: 'bi-sunrise-fill', value: props.cards.today ?? 0, route: appUrl('/vendor/taxi/bookings') },
    { key: 'upcoming', label: 'Upcoming Trips', icon: 'bi-calendar-event', value: props.cards.upcoming ?? 0, route: appUrl('/vendor/taxi/bookings') },
    { key: 'unassigned', label: 'Unassigned', icon: 'bi-person-x', value: props.cards.unassigned ?? 0, route: appUrl('/vendor/taxi/bookings?status=confirmed') },
    { key: 'active', label: 'Active Trips', icon: 'bi-geo-alt', value: props.cards.active_trips ?? 0, route: appUrl('/vendor/taxi/bookings?status=driver_assigned') },
    { key: 'completed', label: 'Completed Today', icon: 'bi-flag', value: props.cards.completed_today ?? 0, route: appUrl('/vendor/taxi/bookings?status=completed') },
    { key: 'drivers', label: 'Available Drivers', icon: 'bi-person-badge', value: props.cards.drivers_available ?? 0, route: appUrl('/vendor/taxi/drivers?availability_status=available') },
    { key: 'vehicles', label: 'Available Vehicles', icon: 'bi-truck-front', value: props.cards.vehicles_available ?? 0, route: appUrl('/vendor/taxi/vehicles?status=available') },
]));

const todayRides = computed(() => props.todayRides ?? []);
const upcomingRides = computed(() => props.upcomingRides ?? []);
const unassignedRides = computed(() => props.unassignedRides ?? []);

function pickupDate(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Taxi Dashboard</h2>
                <p class="text-muted mb-0">Keep track of your taxi rides, drivers and vehicles.</p>
            </div>
            <div class="d-flex gap-2">
                <Link :href="appUrl('/vendor/taxi/bookings/create')" class="btn btn-svtp">New Taxi Booking</Link>
                <Link :href="appUrl('/vendor/taxi/bookings')" class="btn btn-outline-secondary">Manage Bookings</Link>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div v-for="card in cardList" :key="card.key" class="col-12 col-sm-6 col-lg-3">
                <Link :href="card.route" class="dashboard-card text-decoration-none">
                    <div class="dashboard-card-icon"><i class="bi" :class="card.icon"></i></div>
                    <div>
                        <div class="dashboard-card-label">{{ card.label }}</div>
                        <div class="dashboard-card-value">{{ card.value }}</div>
                    </div>
                </Link>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Today's Pickups</strong>
                        <Link :href="appUrl('/vendor/taxi/bookings')" class="small">All bookings</Link>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Reference</th><th>Customer</th><th>Pickup</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    <tr v-for="ride in todayRides" :key="ride.id">
                                        <td><Link :href="appUrl(`/vendor/taxi/bookings/${ride.id}`)" class="fw-semibold text-decoration-none">{{ ride.reference }}</Link></td>
                                        <td><div class="fw-semibold">{{ ride.customer_name || 'Guest' }}</div><div class="small text-muted">{{ ride.customer_phone }}</div></td>
                                        <td><div>{{ pickupDate(ride.pickup_at) }}</div><div class="small text-muted">{{ ride.pickup_address }}</div></td>
                                        <td><span class="badge bg-secondary text-uppercase">{{ ride.status }}</span></td>
                                        <td class="text-end"><Link :href="appUrl(`/vendor/taxi/bookings/${ride.id}`)" class="btn btn-sm btn-outline-primary">Details</Link></td>
                                    </tr>
                                    <tr v-if="!todayRides.length"><td colspan="5" class="text-center text-muted py-4">No pickups scheduled for today.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-3">
                <div class="card h-100">
                    <div class="card-header"><strong>Upcoming Trips</strong></div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div v-for="ride in upcomingRides" :key="ride.id" class="list-group-item">
                                <div class="fw-semibold">{{ ride.reference }}</div>
                                <div class="small text-muted">{{ pickupDate(ride.pickup_at) }}</div>
                                <div class="small text-muted">{{ ride.pickup_address }}</div>
                            </div>
                            <div v-if="!upcomingRides.length" class="p-4 text-center text-muted">You have no upcoming taxi bookings.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-3">
                <div class="card h-100">
                    <div class="card-header"><strong>Awaiting Assignment</strong></div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div v-for="ride in unassignedRides" :key="ride.id" class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">{{ ride.reference }}</div>
                                    <div class="small text-muted">{{ pickupDate(ride.pickup_at) }}</div>
                                </div>
                                <Link :href="appUrl(`/vendor/taxi/bookings/${ride.id}`)" class="btn btn-sm btn-outline-primary">Assign</Link>
                            </div>
                            <div v-if="!unassignedRides.length" class="p-4 text-center text-muted">All confirmed rides are assigned.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </VendorLayout>
</template>

<style scoped>
.dashboard-card { display: flex; align-items: center; gap: .9rem; padding: 1.1rem; border-radius: 1rem; background: #ffffff; border: 1px solid #e2e8f0; color: #0f172a; transition: transform .15s ease, box-shadow .15s ease; }
.dashboard-card:hover { transform: translateY(-2px); box-shadow: 0 18px 34px rgba(15, 23, 42, 0.08); }
.dashboard-card-icon { width: 42px; height: 42px; border-radius: 12px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #2563eb; }
.dashboard-card-label { font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
.dashboard-card-value { font-size: 1.4rem; font-weight: 700; }
.card { border-radius: 1rem; border: 1px solid #e2e8f0; }
.table { --bs-table-bg: transparent; }
.list-group-item { border-color: #e2e8f0; }
</style>
