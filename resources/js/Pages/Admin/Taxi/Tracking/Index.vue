<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import TaxiMap from '../../../../Components/Taxi/TaxiMap.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    board: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    vendors: { type: Array, default: () => [] },
    staleSeconds: { type: Number, default: 120 },
    map: { type: Object, default: () => ({ provider: 'none', enabled: false, public_key: null }) },
});

const endpoint = appUrl('/admin/taxi/tracking');
const routeEndpoint = appUrl('/admin/taxi/tracking/route');

const selected = ref(null);
const selectedLoading = ref(false);
const selectedError = ref('');

const markers = computed(() => (props.board.data || [])
    .filter(row => row.location)
    .map(row => ({
        kind: 'driver',
        lat: Number(row.location.latitude),
        lng: Number(row.location.longitude),
        label: row.driver_name,
    })));

async function loadTripRoute(row) {
    if (!row.trip) {
        return;
    }
    selectedLoading.value = true;
    selectedError.value = '';
    try {
        const res = await fetch(`${routeEndpoint}?taxi_booking=${row.trip.booking_id}`, { headers: { Accept: 'application/json' } });
        if (res.ok) {
            selected.value = await res.json();
        } else {
            selectedError.value = 'Route unavailable.';
        }
    } catch (e) {
        selectedError.value = 'Route unavailable.';
    } finally {
        selectedLoading.value = false;
    }
}

const form = useForm({
    search: props.filters.search ?? '',
    vendor_id: props.filters.vendor_id ?? '',
});

function applyFilters() {
    form.get(endpoint, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    form.reset();
    applyFilters();
}

function freshnessBadge(row) {
    return row.freshness === 'live'
        ? 'bg-success'
        : row.freshness === 'stale' ? 'bg-warning text-dark' : 'bg-secondary';
}

function when(value) {
    return value ? new Date(value).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit', second: '2-digit' }) : '—';
}
</script>

<template>
    <AdminLayout>
        <div class="mb-3">
            <h2 class="mt-2 mb-1">Taxi Tracking</h2>
            <p class="text-muted mb-0">Live driver positions. Live = updated within the last {{ staleSeconds }}s. No map yet — coordinates only.</p>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Search driver</label>
                    <input v-model="form.search" class="form-control form-control-sm" placeholder="Name or phone" />
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Vendor</label>
                    <select v-model="form.vendor_id" class="form-select form-select-sm">
                        <option value="">All vendors</option>
                        <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-sm btn-svtp" :disabled="form.processing">Apply</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                    <span class="ms-auto text-muted small align-self-center">{{ board.total }} driver(s)</span>
                </div>
            </div>
        </form>

        <div class="card p-3 mb-3">
            <strong>Live map</strong>
            <p class="small text-muted mb-2">Driver positions on this page. Select a trip below for route and ETA.</p>
            <TaxiMap :map="map" :markers="markers" :path="selected?.route?.path ?? []" />
            <div v-if="selected" class="border rounded p-2 mt-2 small">
                <div class="fw-semibold">Selected trip route</div>
                <div v-if="selected.route?.available">
                    Distance {{ (selected.route.distance_meters / 1000).toFixed(1) }} km · ETA {{ selected.route.eta }}
                    <span v-if="selected.route.cached" class="text-muted">(cached)</span>
                </div>
                <div v-else class="text-muted">{{ selected.route?.reason ?? 'Route unavailable.' }}</div>
            </div>
            <div v-if="selectedLoading" class="small text-muted mt-1">Loading route…</div>
            <div v-if="selectedError" class="small text-danger mt-1">{{ selectedError }}</div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Trip</th>
                            <th>Last location</th>
                            <th>Freshness</th>
                            <th class="text-end">Map</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in board.data" :key="row.driver_id">
                            <td>
                                <div class="fw-semibold">{{ row.driver_name }}</div>
                                <div class="small text-muted">{{ row.vendor ?? '—' }} · {{ row.phone ?? '' }}</div>
                            </td>
                            <td>
                                <div v-if="row.trip" class="small">{{ row.trip.reference }}</div>
                                <div v-if="row.trip" class="small text-muted">{{ row.trip.status }} · {{ row.trip.vehicle ?? '' }}</div>
                                <span v-else class="small text-muted">No active trip</span>
                            </td>
                            <td class="small">
                                <div v-if="row.location">{{ row.location.latitude }}, {{ row.location.longitude }}</div>
                                <div v-if="row.location" class="text-muted">{{ when(row.location.captured_at) }}</div>
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td><span class="badge" :class="freshnessBadge(row)">{{ row.freshness }}</span></td>
                            <td>
                                <button v-if="row.trip" class="btn btn-sm btn-outline-light" :disabled="selectedLoading" @click="loadTripRoute(row)">Route</button>
                            </td>
                        </tr>
                        <tr v-if="!board.data.length"><td colspan="5" class="text-center text-muted py-4">No drivers match this view.</td></tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="board.links" class="px-3 py-2" />
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); }
</style>
