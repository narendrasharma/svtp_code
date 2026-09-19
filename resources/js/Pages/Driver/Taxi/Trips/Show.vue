<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import DriverLayout from '../../../../Layouts/DriverLayout.vue';
import TaxiMap from '../../../../Components/Taxi/TaxiMap.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    booking: { type: Object, required: true },
    assignment: { type: Object, required: true },
    vehicle: { type: Object, default: null },
    notes: { type: Array, default: () => [] },
    allowedTransitions: { type: Array, default: () => [] },
    tracking: { type: Object, default: null },
    tripMap: { type: Object, default: null },
});

const endpoint = appUrl(`/driver/taxi/trips/${props.booking.id}`);
const routeEndpoint = appUrl(`/driver/taxi/trips/${props.booking.id}/route`);
const locationEndpoint = appUrl('/driver/taxi/location');
const locationStatusEndpoint = appUrl('/driver/taxi/location/status');
const noteForm = useForm({ body: '' });
const statusForm = useForm({ status: '', note: '' });
const loadedNotes = ref(null);

// Web-based active-session tracking only (12A.5): conservative 30s
// interval while sharing is on, never background/mobile tracking.
const TRACKING_INTERVAL_SECONDS = 30;
const sharing = ref(false);
const sharingState = ref(props.tracking?.freshness ?? 'offline');
const lastSharedAt = ref(props.tracking?.last_captured_at ?? null);
const sharingError = ref('');
const tripMapState = ref(props.tripMap);
const routeRefreshing = ref(false);
const routeError = ref('');
const lastRouteRefresh = ref(0);
let watchId = null;
let intervalId = null;

const TRACKABLE = ['driver_assigned', 'en_route', 'arrived', 'passenger_on_board'];

async function sendPosition(position) {
    sharingError.value = '';
    try {
        const res = await axios.post(locationEndpoint, {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy_meters: position.coords.accuracy != null ? Math.round(position.coords.accuracy) : null,
            heading: position.coords.heading,
            speed_kmh: position.coords.speed != null ? position.coords.speed * 3.6 : null,
            captured_at: new Date(position.timestamp).toISOString(),
        });
        lastSharedAt.value = res.data.captured_at;
        sharingState.value = res.data.freshness;
    } catch (e) {
        sharingError.value = e.response?.data?.message ?? 'Location update failed.';
    }
}

function onPositionError(error) {
    if (error.code === 1) {
        sharingError.value = 'Location permission denied. Enable it in the browser to share.';
    } else if (error.code === 2) {
        sharingError.value = 'Position unavailable. Try again outdoors.';
    } else {
        sharingError.value = 'Location request timed out. Retrying…';
    }
}

function pollOnce() {
    if (!('geolocation' in navigator)) {
        sharingError.value = 'Geolocation is not supported on this device.';
        stopSharing();
        return;
    }
    navigator.geolocation.getCurrentPosition(sendPosition, onPositionError, {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 10000,
    });
}

function startSharing() {
    sharingError.value = '';
    if (!('geolocation' in navigator)) {
        sharingError.value = 'Geolocation is not supported on this device.';
        return;
    }
    sharing.value = true;
    pollOnce();
    intervalId = setInterval(pollOnce, TRACKING_INTERVAL_SECONDS * 1000);
}

function stopSharing() {
    sharing.value = false;
    if (intervalId) {
        clearInterval(intervalId);
        intervalId = null;
    }
}

async function refreshTrackingState() {
    try {
        const res = await fetch(locationStatusEndpoint, { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const state = await res.json();
            sharingState.value = state.freshness;
            lastSharedAt.value = state.last_captured_at;
        }
    } catch (e) {
    }
}

async function refreshRoute() {
    const minInterval = Math.max(30, Number(tripMapState.value?.map?.refresh_seconds ?? 60)) * 1000;
    if (Date.now() - lastRouteRefresh.value < minInterval || routeRefreshing.value) {
        return;
    }
    routeRefreshing.value = true;
    routeError.value = '';
    try {
        const res = await fetch(routeEndpoint, { headers: { Accept: 'application/json' } });
        if (res.ok) {
            tripMapState.value = await res.json();
            lastRouteRefresh.value = Date.now();
        } else {
            routeError.value = 'Route unavailable.';
        }
    } catch (e) {
        routeError.value = 'Route unavailable.';
    } finally {
        routeRefreshing.value = false;
    }
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

function acknowledge() {
    router.post(`${endpoint}/acknowledge`, {}, { preserveScroll: true });
}

function changeStatus(status) {
    statusForm.status = status;
    statusForm.patch(`${endpoint}/status`, { preserveScroll: true });
}

function saveNote() {
    if (!noteForm.body.trim()) return;
    noteForm.post(`${endpoint}/notes`, { preserveScroll: true, onSuccess: () => noteForm.reset() });
}

async function refreshNotes() {
    try {
        const res = await fetch(`${endpoint}/notes`, { headers: { Accept: 'application/json' } });
        if (res.ok) loadedNotes.value = (await res.json()).notes;
    } catch (e) {
        loadedNotes.value = [];
    }
}
</script>

<template>
    <DriverLayout>
        <Link :href="appUrl('/driver/taxi/trips')" class="small text-decoration-none">← All trips</Link>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-1 mb-3">
            <h2 class="mb-0">{{ booking.reference }}</h2>
            <span class="badge fs-6" :class="statusBadge(booking.status)">{{ booking.status }}</span>
        </div>

        <div v-if="!assignment.acknowledged_at" class="card p-3 mb-3 border-warning">
            <strong>Please acknowledge this trip</strong>
            <p class="small text-muted mb-2">Confirm you have received this job.</p>
            <button class="btn btn-svtp w-100" @click="acknowledge">Acknowledge trip</button>
        </div>

        <div class="card p-3 mb-3">
            <div class="row g-2 small">
                <div class="col-6 text-muted">Pickup</div>
                <div class="col-6 text-end fw-semibold">{{ pickup(booking.pickup_at) }}</div>
                <div class="col-12">{{ booking.pickup_address }}</div>
                <div class="col-6 text-muted">Drop</div>
                <div class="col-6 text-end">{{ booking.trip_type }}</div>
                <div class="col-12">{{ booking.drop_address }}</div>
                <div class="col-6 text-muted">Customer</div>
                <div class="col-6 text-end fw-semibold">{{ booking.customer_name }}</div>
                <div class="col-6 text-muted">Phone</div>
                <div class="col-6 text-end"><a v-if="booking.customer_phone" :href="`tel:${booking.customer_phone}`">{{ booking.customer_phone }}</a><span v-else>—</span></div>
                <div v-if="vehicle" class="col-6 text-muted">Vehicle</div>
                <div v-if="vehicle" class="col-6 text-end">{{ vehicle.name }} · {{ vehicle.registration_number }}</div>
                <div v-if="booking.special_instructions" class="col-12 mt-2">
                    <div class="text-muted">Pickup instructions</div>
                    <div>{{ booking.special_instructions }}</div>
                </div>
            </div>
        </div>

        <div v-if="tripMapState" class="card p-3 mb-3">
            <strong>Trip map</strong>
            <TaxiMap :map="tripMapState.map" :markers="tripMapState.markers" :path="tripMapState.route?.path ?? []" />
            <div v-if="tripMapState.route?.available" class="small mt-2">
                Distance {{ (tripMapState.route.distance_meters / 1000).toFixed(1) }} km · ETA {{ tripMapState.route.eta }}
                <span v-if="tripMapState.route.cached" class="text-muted">(cached)</span>
            </div>
            <div v-else class="small text-muted mt-2">{{ tripMapState.route?.reason ?? 'Route unavailable.' }}</div>
            <a v-if="booking.drop_lat && booking.drop_lng" :href="`https://www.google.com/maps/dir/?api=1&destination=${booking.drop_lat},${booking.drop_lng}`" target="_blank" rel="noopener" class="btn btn-outline-light w-100 mt-2">Open in Maps</a>
            <button class="btn btn-sm btn-link mt-1" :disabled="routeRefreshing" @click="refreshRoute">{{ routeRefreshing ? 'Refreshing…' : 'Refresh route' }}</button>
            <div v-if="routeError" class="small text-danger">{{ routeError }}</div>
        </div>

        <div v-if="allowedTransitions.length" class="card p-3 mb-3">
            <strong>Update trip status</strong>
            <div class="d-grid gap-2 mt-2">
                <button
                    v-for="next in allowedTransitions"
                    :key="next.value"
                    class="btn btn-lg btn-svtp"
                    :disabled="statusForm.processing"
                    @click="changeStatus(next.value)"
                >
                    {{ next.label }}
                </button>
            </div>
        </div>

        <div class="card p-3 mb-3">
            <strong>Location sharing</strong>            <p class="small text-muted mb-2">Shares your position every {{ TRACKING_INTERVAL_SECONDS }}s while on. Only your vendor dispatch sees it.</p>
            <div class="small mb-2">Status: <span class="badge" :class="sharingState === 'live' ? 'bg-success' : 'bg-secondary'">{{ sharing ? sharingState : 'off' }}</span>
                <span v-if="lastSharedAt" class="text-muted"> · last {{ pickup(lastSharedAt) }}</span>
            </div>
            <div v-if="sharingError" class="alert alert-warning py-2 small">{{ sharingError }}</div>
            <div class="d-grid gap-2">
                <button v-if="!sharing" class="btn btn-svtp" @click="startSharing">Start sharing location</button>
                <button v-else class="btn btn-outline-light" @click="stopSharing">Stop sharing</button>
                <button class="btn btn-sm btn-link" @click="refreshTrackingState">Refresh status</button>
            </div>
        </div>

        <div class="card p-3 mb-3">
            <strong>Operational notes</strong>
            <div v-for="note in (loadedNotes ?? notes)" :key="note.id" class="border rounded p-2 my-2 small">
                {{ note.body }}
                <div class="text-muted">{{ note.created_at ? new Date(note.created_at).toLocaleString('en-IN') : '' }}</div>
            </div>
            <div class="d-flex gap-2 mt-2">
                <input v-model="noteForm.body" class="form-control" placeholder="e.g. delayed due to traffic…" maxlength="2000" />
                <button class="btn btn-svtp" :disabled="noteForm.processing" @click="saveNote">Add</button>
            </div>
            <button class="btn btn-sm btn-link mt-1" @click="refreshNotes">Refresh notes</button>
        </div>
    </DriverLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); }
.btn-lg { min-height: 52px; font-size: 1.05rem; }
</style>
