<script setup>
import { Link } from '@inertiajs/vue3';
import DriverLayout from '../../../Layouts/DriverLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    driver: { type: Object, required: true },
    metrics: { type: Object, default: () => ({}) },
    nextPickup: { type: Object, default: null },
    activeTrip: { type: Object, default: null },
    todayTrips: { type: Array, default: () => [] },
    upcomingTrips: { type: Array, default: () => [] },
    leave: { type: Array, default: () => [] },
    notifications: { type: Array, default: () => [] },
    unreadNotifications: { type: Number, default: 0 },
});

const tripsEndpoint = appUrl('/driver/taxi/trips');

function pickup(value) {
    return value ? new Date(value).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) : '—';
}

function statusBadge(status) {
    const map = {
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
</script>

<template>
    <DriverLayout>
        <div class="mb-3">
            <h2 class="mt-2 mb-1">Namaste, {{ driver.first_name }}</h2>
            <p class="text-muted mb-0">{{ driver.availability_status === 'available' ? 'You are marked available.' : 'You are currently off duty.' }}</p>
        </div>

        <div v-if="activeTrip" class="card p-3 mb-3 border-warning">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Active trip</strong>
                <span class="badge" :class="statusBadge(activeTrip.status)">{{ activeTrip.status }}</span>
            </div>
            <div class="fs-5 fw-semibold">{{ activeTrip.reference }}</div>
            <div class="small text-muted">{{ activeTrip.pickup_address }} → {{ activeTrip.drop_address }}</div>
            <Link :href="`${tripsEndpoint}/${activeTrip.id}`" class="btn btn-svtp w-100 mt-3">Open active trip</Link>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <div class="card p-3 h-100 text-center">
                    <div class="display-6 fw-bold">{{ metrics.trips_today ?? 0 }}</div>
                    <div class="small text-muted">Today</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-3 h-100 text-center">
                    <div class="display-6 fw-bold">{{ metrics.upcoming ?? 0 }}</div>
                    <div class="small text-muted">Upcoming</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-3 h-100 text-center">
                    <div class="display-6 fw-bold">{{ metrics.active ?? 0 }}</div>
                    <div class="small text-muted">Active</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card p-3 h-100 text-center">
                    <div class="display-6 fw-bold">{{ metrics.completed_today ?? 0 }}</div>
                    <div class="small text-muted">Done today</div>
                </div>
            </div>
        </div>

        <div v-if="(metrics.attention ?? 0) > 0" class="alert alert-warning">You have {{ metrics.attention }} trip(s) needing attention.</div>

        <div v-if="nextPickup" class="card p-3 mb-3">
            <div class="small text-muted text-uppercase">Next pickup</div>
            <div class="fw-semibold">{{ nextPickup.reference }} · {{ pickup(nextPickup.pickup_at) }}</div>
            <div class="small text-muted">{{ nextPickup.pickup_address }}</div>
            <Link :href="`${tripsEndpoint}/${nextPickup.id}`" class="btn btn-outline-light w-100 mt-2">View trip</Link>
        </div>

        <div class="card p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Today's trips</strong>
                <Link :href="tripsEndpoint" class="small">All trips</Link>
            </div>
            <div v-if="!todayTrips.length" class="text-muted small">No trips scheduled for today.</div>
            <div v-for="trip in todayTrips" :key="trip.id" class="border rounded p-2 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <Link :href="`${tripsEndpoint}/${trip.id}`" class="fw-semibold text-decoration-none">{{ trip.reference }}</Link>
                    <span class="badge" :class="statusBadge(trip.status)">{{ trip.status }}</span>
                </div>
                <div class="small text-muted">{{ pickup(trip.pickup_at) }} · {{ trip.pickup_address }}</div>
            </div>
        </div>

        <div v-if="leave.length" class="card p-3 mb-3">
            <strong>Upcoming time off</strong>
            <div v-for="window in leave" :key="window.id" class="small text-muted">{{ window.status }}: {{ pickup(window.from_at) }} → {{ pickup(window.to_at) }}</div>
        </div>

        <div v-if="unreadNotifications > 0" class="card p-3 mb-3">
            <strong>You have {{ unreadNotifications }} unread notification(s)</strong>
            <div v-for="note in notifications.slice(0, 3)" :key="note.id" class="small text-muted">· {{ note.data?.title ?? 'Update' }}</div>
            <Link :href="appUrl('/notifications')" class="btn btn-outline-light w-100 mt-2">View notifications</Link>
        </div>
    </DriverLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); }
</style>
