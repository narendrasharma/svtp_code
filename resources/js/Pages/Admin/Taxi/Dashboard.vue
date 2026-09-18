<script setup>
import { computed } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    cards: { type: Object, default: () => ({}) },
    todayRides: { type: Array, default: () => [] },
    unassignedRides: { type: Array, default: () => [] },
    expiringDriverDocs: { type: Number, default: 0 },
    expiringVehicleDocs: { type: Number, default: 0 },
});

const cardList = computed(() => ([
    { key: 'today', label: "Today's Pickups", icon: 'bi-sunrise-fill', route: appUrl('/admin/taxi/bookings?date_from=today'), value: props.cards.today ?? 0 },
    { key: 'upcoming', label: 'Upcoming Trips', icon: 'bi-calendar-event', route: appUrl('/admin/taxi/bookings?date_from=' + new Date().toISOString().slice(0, 10)), value: props.cards.upcoming ?? 0 },
    { key: 'unassigned', label: 'Unassigned Rides', icon: 'bi-exclamation-triangle', route: appUrl('/admin/taxi/bookings?status=confirmed'), value: props.cards.unassigned ?? 0 },
    { key: 'active', label: 'Active Trips', icon: 'bi-geo-alt', route: appUrl('/admin/taxi/bookings?status=driver_assigned'), value: props.cards.active_trips ?? 0 },
    { key: 'completed', label: 'Completed Today', icon: 'bi-flag-checkered', route: appUrl('/admin/taxi/bookings?status=completed'), value: props.cards.completed_today ?? 0 },
    { key: 'drivers', label: 'Available Drivers', icon: 'bi-person-badge', route: appUrl('/admin/taxi/drivers?availability_status=available'), value: props.cards.drivers_available ?? 0 },
    { key: 'vehicles', label: 'Available Vehicles', icon: 'bi-truck-front', route: appUrl('/admin/taxi/vehicles?status=available'), value: props.cards.vehicles_available ?? 0 },
]));

const todayRides = computed(() => props.todayRides ?? []);
const unassignedRides = computed(() => props.unassignedRides ?? []);

function pickupDate(ride) {
    return ride.pickup_at ? new Date(ride.pickup_at).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Taxi Operations</h2>
                <p class="text-muted mb-0">Monitor taxi rides, assignments and fleet readiness.</p>
            </div>
            <div class="d-flex gap-2">
                <Link :href="appUrl('/admin/taxi/bookings/create')" class="btn btn-svtp"><i class="bi bi-plus-lg me-2"></i>New Taxi Booking</Link>
                <Link :href="appUrl('/admin/taxi/bookings')" class="btn btn-outline-light">All Taxi Bookings</Link>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div v-for="card in cardList" :key="card.key" class="col-12 col-sm-6 col-lg-3">
                <Link :href="card.route" class="dashboard-card text-decoration-none">
                    <div class="dashboard-card-icon"><i class="bi" :class="card.icon"></i></div>
                    <div class="dashboard-card-body">
                        <span class="dashboard-card-label">{{ card.label }}</span>
                        <span class="dashboard-card-value">{{ card.value }}</span>
                    </div>
                </Link>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-7">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <strong>Today's Pickups</strong>
                        <Link :href="appUrl('/admin/taxi/bookings?date_from=' + new Date().toISOString().slice(0, 10))" class="small">View all</Link>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Reference</th><th>Customer</th><th>Pickup</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    <tr v-for="ride in todayRides" :key="ride.id">
                                        <td><Link :href="appUrl(`/admin/taxi/bookings/${ride.id}`)" class="fw-semibold text-decoration-none">{{ ride.reference }}</Link></td>
                                        <td>
                                            <div class="fw-semibold">{{ ride.customer_name || 'Guest' }}</div>
                                            <div class="small text-muted">{{ ride.customer_phone }}</div>
                                        </td>
                                        <td>
                                            <div>{{ pickupDate(ride) }}</div>
                                            <div class="small text-muted">{{ ride.pickup_address }}</div>
                                        </td>
                                        <td><span class="badge text-uppercase bg-secondary">{{ ride.status }}</span></td>
                                        <td class="text-end"><Link :href="appUrl(`/admin/taxi/bookings/${ride.id}`)" class="btn btn-sm btn-outline-primary">Details</Link></td>
                                    </tr>
                                    <tr v-if="!todayRides.length"><td colspan="5" class="text-center text-muted py-4">No pickups scheduled for today.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <strong>Awaiting Assignment</strong>
                        <Link :href="appUrl('/admin/taxi/bookings?status=confirmed')" class="small">Assign drivers</Link>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div v-for="ride in unassignedRides" :key="ride.id" class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold">{{ ride.reference }}</div>
                                    <div class="small text-muted">{{ ride.customer_name || 'Guest' }} · {{ ride.pickup_address }}</div>
                                </div>
                                <Link :href="appUrl(`/admin/taxi/bookings/${ride.id}`)" class="btn btn-sm btn-outline-primary">Assign</Link>
                            </div>
                            <div v-if="!unassignedRides.length" class="p-4 text-center text-muted">All confirmed rides are assigned.</div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <div class="fw-semibold">Driver documents expiring soon</div>
                                <div class="small text-muted">Within the next 30 days</div>
                            </div>
                            <span class="badge bg-warning text-dark fs-6">{{ props.expiringDriverDocs }}</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div>
                                <div class="fw-semibold">Vehicle documents expiring soon</div>
                                <div class="small text-muted">Within the next 30 days</div>
                            </div>
                            <span class="badge bg-warning text-dark fs-6">{{ props.expiringVehicleDocs }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.dashboard-card { display: flex; align-items: center; gap: 1rem; padding: 1.25rem; border-radius: 1rem; background: var(--svtp-card-bg, #101827); border: 1px solid rgba(148, 163, 184, 0.15); color: #e2e8f0; transition: transform .15s ease, border-color .15s ease; }
.dashboard-card:hover { transform: translateY(-2px); border-color: #f59e0b; color: #fff; }
.dashboard-card-icon { width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #fbbf24; }
.dashboard-card-body { display: flex; flex-direction: column; gap: .25rem; }
.dashboard-card-label { font-size: .85rem; text-transform: uppercase; letter-spacing: .05em; color: #cbd5f5; }
.dashboard-card-value { font-size: 1.5rem; font-weight: 700; }
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, 0.1); background: #101827; color: #e2e8f0; }
.table { --bs-table-bg: transparent; color: inherit; }
.list-group-item { background: transparent; color: inherit; border-color: rgba(148, 163, 184, 0.1); }
</style>
