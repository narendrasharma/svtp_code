<script setup>
import { Head } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import TaxiMap from '../../../Components/Taxi/TaxiMap.vue';

const props = defineProps({
    reference: { type: String, required: true },
    status: { type: String, required: true },
    status_label: { type: String, default: '' },
    trip_type: { type: String, default: '' },
    pickup_at: { type: String, default: null },
    pickup_address: { type: String, default: '' },
    drop_address: { type: String, default: '' },
    driver: { type: Object, default: null },
    tracking: { type: Object, default: () => ({ state: 'hidden' }) },
    route: { type: Object, default: null },
    map: { type: Object, default: () => ({ provider: 'none', enabled: false, public_key: null }) },
    refresh_seconds: { type: Number, default: 30 },
});

const statusUrl = window.location.pathname.replace(/\/$/, '') + '/status';
const live = ref({ tracking: props.tracking, route: props.route, status: props.status, status_label: props.status_label, driver: props.driver });
const loadError = ref('');
let timerId = null;

const TERMINAL = ['completed', 'cancelled', 'no_show'];

function when(value) {
    return value ? new Date(value).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) : '—';
}

function stateBadge(state) {
    return state === 'live' ? 'bg-success'
        : state === 'stale' ? 'bg-warning text-dark'
        : state === 'ended' ? 'bg-secondary'
        : 'bg-light text-dark';
}

function stateLabel(state) {
    return { live: 'Live', stale: 'Stale', offline: 'Waiting for signal', hidden: 'Not shared yet', ended: 'Trip ended' }[state] ?? state;
}

async function refresh() {
    if (document.hidden || TERMINAL.includes(live.value.status)) {
        stopPolling();
        return;
    }
    try {
        const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            live.value = {
                tracking: data.tracking,
                route: data.route,
                status: data.status,
                status_label: data.status_label,
                driver: data.driver,
            };
            if (TERMINAL.includes(data.status)) {
                stopPolling();
            }
        }
    } catch (e) {
        loadError.value = 'Could not refresh. Showing last known status.';
    }
}

function stopPolling() {
    if (timerId) {
        clearInterval(timerId);
        timerId = null;
    }
}

const interval = Math.min(60, Math.max(15, Number(props.refresh_seconds) || 30)) * 1000;
timerId = setInterval(refresh, interval);
onBeforeUnmount(stopPolling);

const markers = () => {
    const points = [];
    if (live.value.tracking?.location) {
        points.push({ kind: 'driver', lat: Number(live.value.tracking.location.latitude), lng: Number(live.value.tracking.location.longitude), label: 'Driver' });
    }
    return points;
};
</script>

<template>
    <div class="track-shell">
        <Head>
            <title>Ride {{ reference }} — live status</title>
            <meta name="robots" content="noindex, nofollow" />
        </Head>

        <header class="track-header">
            <div class="small text-muted">LIVE TRIP STATUS</div>
            <h1 class="h4 mb-1">{{ reference }}</h1>
            <span class="badge fs-6" :class="live.status === 'completed' ? 'bg-success' : 'bg-primary'">{{ live.status_label || live.status }}</span>
        </header>

        <main class="track-main">
            <section class="track-card">
                <div class="row g-2 small">
                    <div class="col-5 text-muted">Pickup</div>
                    <div class="col-7 text-end fw-semibold">{{ when(pickup_at) }}</div>
                    <div class="col-12">{{ pickup_address }}</div>
                    <div class="col-5 text-muted">Destination</div>
                    <div class="col-7 text-end">{{ trip_type }}</div>
                    <div class="col-12">{{ drop_address }}</div>
                </div>
            </section>

            <section v-if="live.driver" class="track-card">
                <div class="fw-semibold mb-1">Your driver</div>
                <div class="fs-5">{{ live.driver.display_name }}</div>
                <div v-if="live.driver.phone" class="mt-1"><a :href="`tel:${live.driver.phone}`" class="btn btn-svtp w-100">Call driver</a></div>
                <div v-if="live.driver.vehicle" class="small text-muted mt-2">
                    {{ live.driver.vehicle.type ?? 'Vehicle' }}
                    <span v-if="live.driver.vehicle.make_model"> · {{ live.driver.vehicle.make_model }}</span>
                    <span v-if="live.driver.vehicle.registration"> · {{ live.driver.vehicle.registration }}</span>
                </div>
            </section>
            <section v-else class="track-card text-muted small">
                Waiting for driver assignment — this page updates automatically.
            </section>

            <section class="track-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Live location</strong>
                    <span class="badge" :class="stateBadge(live.tracking?.state)">{{ stateLabel(live.tracking?.state) }}</span>
                </div>
                <TaxiMap :map="map" :markers="markers()" :path="live.route?.path ?? []" height="240px" />
                <div v-if="live.route?.available" class="small mt-2">
                    ETA {{ live.route.eta }} · {{ (live.route.distance_meters / 1000).toFixed(1) }} km away
                </div>
                <div v-else-if="live.tracking?.state === 'live'" class="small text-muted mt-2">ETA unavailable right now.</div>
                <div v-if="live.tracking?.updated_at" class="small text-muted">Last update {{ when(live.tracking.updated_at) }}</div>
                <div v-if="loadError" class="small text-danger mt-1">{{ loadError }}</div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.track-shell { min-height: 100vh; background: #090f1d; color: #e5e7eb; }
.track-header { padding: 1.25rem 1rem .75rem; text-align: center; border-bottom: 1px solid #1e293b; }
.track-main { max-width: 560px; margin: 0 auto; padding: 1rem 1rem 2rem; display: grid; gap: .75rem; }
.track-card { background: #111c2d; border: 1px solid #334155; border-radius: 1rem; padding: 1rem; }
</style>
