<script setup>
import { Link, router } from '@inertiajs/vue3';
import DriverLayout from '../../../../Layouts/DriverLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    trips: { type: Object, required: true },
    view: { type: String, default: 'upcoming' },
    activeTrip: { type: Object, default: null },
});

const endpoint = appUrl('/driver/taxi/trips');
const views = [
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'active', label: 'Active' },
    { value: 'history', label: 'History' },
];

function setView(view) {
    router.get(endpoint, { view }, { preserveScroll: true });
}

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
        <h2 class="mt-2 mb-1">My Trips</h2>
        <p class="text-muted">Only trips assigned to you appear here.</p>

        <div class="d-flex gap-2 mb-3">
            <button
                v-for="option in views"
                :key="option.value"
                type="button"
                class="btn flex-fill"
                :class="view === option.value ? 'btn-svtp' : 'btn-outline-secondary'"
                @click="setView(option.value)"
            >
                {{ option.label }}
            </button>
        </div>

        <div v-if="activeTrip" class="card p-3 mb-3 border-warning">
            <div class="small text-muted text-uppercase">Active now</div>
            <div class="fw-semibold">{{ activeTrip.reference }} · {{ pickup(activeTrip.pickup_at) }}</div>
            <Link :href="`${endpoint}/${activeTrip.id}`" class="btn btn-svtp w-100 mt-2">Open active trip</Link>
        </div>

        <div v-if="!trips.data.length" class="card p-4 text-center text-muted">No trips in this view.</div>

        <div v-for="trip in trips.data" :key="trip.id" class="card p-3 mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">{{ trip.reference }}</span>
                <span class="badge" :class="statusBadge(trip.status)">{{ trip.status }}</span>
            </div>
            <div class="small">{{ pickup(trip.pickup_at) }}</div>
            <div class="small text-muted">{{ trip.pickup_address }} → {{ trip.drop_address }}</div>
            <div v-if="trip.assigned_vehicle" class="small text-muted">{{ trip.assigned_vehicle.name }} · {{ trip.assigned_vehicle.registration_number }}</div>
            <div v-if="trip.acknowledged === false" class="badge bg-warning text-dark mt-1">Needs acknowledgement</div>
            <Link :href="`${endpoint}/${trip.id}`" class="btn btn-outline-light w-100 mt-2">Open trip</Link>
        </div>

        <Pagination :links="trips.links" class="mt-2" />
    </DriverLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); }
</style>
