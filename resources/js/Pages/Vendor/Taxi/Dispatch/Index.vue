<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    board: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({}) },
    bucketCounts: { type: Object, default: () => ({}) },
    buckets: { type: Array, default: () => [] },
    timeViews: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    drivers: { type: Array, default: () => [] },
    vehicles: { type: Array, default: () => [] },
});

const endpoint = appUrl('/vendor/taxi/dispatch');
const bookingsEndpoint = appUrl('/vendor/taxi/bookings');

const form = useForm({
    search: props.filters.search ?? '',
    bucket: props.filters.bucket ?? '',
    status: props.filters.status ?? '',
    time_view: props.filters.time_view ?? '',
    driver_id: props.filters.driver_id ?? '',
    vehicle_id: props.filters.vehicle_id ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

const eligibleCache = ref({});
const recoCache = ref({});
const noteForms = ref({});

function applyFilters() {
    form.get(endpoint, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    router.get(endpoint, {}, { preserveScroll: true });
}

function setBucket(bucket) {
    form.bucket = form.bucket === bucket ? '' : bucket;
    applyFilters();
}

function setTimeView(view) {
    form.time_view = form.time_view === view ? '' : view;
    applyFilters();
}

function statusBadge(status) {
    const map = {
        confirmed: 'bg-info text-dark',
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

function pickup(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}

function isOverdue(booking) {
    if (!booking.pickup_at) return false;
    const active = ['confirmed', 'driver_assigned', 'en_route', 'arrived', 'passenger_on_board'];
    return active.includes(booking.status) && new Date(booking.pickup_at) < new Date();
}

async function loadEligible(booking) {
    if (eligibleCache.value[booking.id]) return;
    try {
        const res = await fetch(appUrl(`/vendor/taxi/dispatch/${booking.id}/eligible`), { headers: { Accept: 'application/json' } });
        if (res.ok) eligibleCache.value[booking.id] = await res.json();
    } catch (e) {
        eligibleCache.value[booking.id] = { drivers: [], vehicles: [] };
    }
}

function noteForm(booking) {
    if (!noteForms.value[booking.id]) noteForms.value[booking.id] = { body: '' };
    return noteForms.value[booking.id];
}

function saveNote(booking) {
    const payload = noteForm(booking);
    if (!payload.body.trim()) return;
    router.post(appUrl(`/vendor/taxi/dispatch/${booking.id}/notes`), { body: payload.body }, {
        preserveScroll: true,
        onSuccess: () => { payload.body = ''; },
    });
}

async function loadRecommendations(booking) {
    if (recoCache.value[booking.id]) return;
    recoCache.value[booking.id] = { loading: true, recommendations: [], excluded_counts: {} };
    try {
        const res = await fetch(appUrl(`/vendor/taxi/dispatch/${booking.id}/recommendations`), { headers: { Accept: 'application/json' } });
        recoCache.value[booking.id] = res.ok
            ? await res.json()
            : { recommendations: [], excluded_counts: {}, error: true };
    } catch (e) {
        recoCache.value[booking.id] = { recommendations: [], excluded_counts: {}, error: true };
    }
}

function assignRecommendation(booking, rec) {
    router.post(`${bookingsEndpoint}/${booking.id}/assign`, {
        driver_id: rec.driver_id,
        vehicle_id: rec.vehicle_id,
    }, { preserveScroll: true });
}

function quickStatus(booking, status) {
    router.patch(`${bookingsEndpoint}/${booking.id}/status`, { status }, { preserveScroll: true });
}

const metricCards = computed(() => [
    { label: 'Unassigned today', value: props.metrics.unassigned_today ?? 0 },
    { label: 'Assigned today', value: props.metrics.assigned_today ?? 0 },
    { label: 'Active trips', value: props.metrics.active_trips ?? 0 },
    { label: 'Overdue pickups', value: props.metrics.overdue_pickups ?? 0 },
    { label: 'Completed today', value: props.metrics.completed_today ?? 0 },
    { label: 'No-shows today', value: props.metrics.no_shows_today ?? 0 },
]);
</script>

<template>
    <VendorLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div>
                <h2 class="mt-2 mb-1">Taxi Dispatch</h2>
                <p class="text-muted mb-0">Your trips — assign your drivers and update ride status.</p>
            </div>
            <Link :href="appUrl('/vendor/taxi/bookings/create')" class="btn btn-svtp"><i class="bi bi-plus-lg me-2"></i>New booking</Link>
        </div>

        <div class="row g-2 mb-3">
            <div v-for="card in metricCards" :key="card.label" class="col-6 col-md-4 col-xl-2">
                <div class="card p-3 h-100">
                    <div class="display-6 fw-bold">{{ card.value }}</div>
                    <div class="small text-muted">{{ card.label }}</div>
                </div>
            </div>
        </div>

        <div class="card p-3 mb-3">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <button
                    v-for="bucket in buckets"
                    :key="bucket.value"
                    type="button"
                    class="btn btn-sm"
                    :class="form.bucket === bucket.value ? 'btn-svtp' : 'btn-outline-secondary'"
                    @click="setBucket(bucket.value)"
                >
                    {{ bucket.label }} ({{ bucketCounts[bucket.value] ?? 0 }})
                </button>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button
                    v-for="view in timeViews"
                    :key="view.value"
                    type="button"
                    class="btn btn-sm"
                    :class="form.time_view === view.value ? 'btn-svtp' : 'btn-outline-secondary'"
                    @click="setTimeView(view.value)"
                >
                    {{ view.label }}
                </button>
            </div>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Search</label>
                    <input v-model="form.search" class="form-control form-control-sm" placeholder="Reference, customer or address" />
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select v-model="form.status" class="form-select form-select-sm">
                        <option value="">All statuses</option>
                        <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Driver</label>
                    <select v-model="form.driver_id" class="form-select form-select-sm">
                        <option value="">Any driver</option>
                        <option v-for="driver in drivers" :key="driver.id" :value="driver.id">{{ driver.first_name }} {{ driver.last_name }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <div class="flex-fill">
                            <label class="form-label small">From</label>
                            <input v-model="form.date_from" type="date" class="form-control form-control-sm" />
                        </div>
                        <div class="flex-fill">
                            <label class="form-label small">To</label>
                            <input v-model="form.date_to" type="date" class="form-control form-control-sm" />
                        </div>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-svtp" :disabled="form.processing">Apply filters</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                    <span class="ms-auto text-muted small align-self-center">{{ board.total }} trip(s)</span>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Pickup</th>
                            <th>Route</th>
                            <th>Customer</th>
                            <th>Fleet</th>
                            <th>Status</th>
                            <th class="text-end">Ops</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="booking in board.data" :key="booking.id">
                            <tr :class="{ 'table-warning': isOverdue(booking) }">
                                <td>
                                    <Link :href="`${bookingsEndpoint}/${booking.id}`" class="fw-semibold text-decoration-none">{{ booking.reference }}</Link>
                                </td>
                                <td>
                                    <div>{{ pickup(booking.pickup_at) }}</div>
                                    <div v-if="isOverdue(booking)" class="badge bg-danger mt-1">Overdue</div>
                                </td>
                                <td>
                                    <div class="small">{{ booking.pickup_address }}</div>
                                    <div class="small text-muted">→ {{ booking.drop_address }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold small">{{ booking.customer_name || 'Guest' }}</div>
                                    <div class="small text-muted">{{ booking.customer_phone }}</div>
                                </td>
                                <td>
                                    <div v-if="booking.assigned_driver" class="small">{{ booking.assigned_driver.first_name }} {{ booking.assigned_driver.last_name }}</div>
                                    <span v-else class="small text-muted">Unassigned</span>
                                    <div v-if="booking.assigned_vehicle" class="small text-muted">{{ booking.assigned_vehicle.name }}</div>
                                </td>
                                <td><span class="badge" :class="statusBadge(booking.status)">{{ booking.status }}</span></td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <Link :href="`${bookingsEndpoint}/${booking.id}`" class="btn btn-outline-primary">Open</Link>
                                        <button class="btn btn-outline-secondary" @click="loadEligible(booking)">Fleet</button>
                                        <button v-if="booking.status === 'confirmed'" class="btn btn-outline-secondary" @click="loadRecommendations(booking)">Smart</button>
                                    </div>
                                    <div v-if="(booking.allowed_transitions || []).length" class="d-flex flex-wrap gap-1 justify-content-end mt-2">
                                        <button
                                            v-for="next in booking.allowed_transitions"
                                            :key="next.value"
                                            class="btn btn-sm btn-outline-success"
                                            @click="quickStatus(booking, next.value)"
                                        >
                                            {{ next.label }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="recoCache[booking.id]">
                                <td colspan="7" class="small">
                                    <strong>Smart suggestions:</strong>
                                    <div v-if="recoCache[booking.id].loading" class="text-muted">Finding drivers…</div>
                                    <ul v-else-if="(recoCache[booking.id].recommendations || []).length" class="mb-0 ps-3">
                                        <li v-for="rec in recoCache[booking.id].recommendations.slice(0, 5)" :key="rec.driver_id" class="mb-1">
                                            <strong>#{{ rec.rank }} {{ rec.driver_name }}</strong> · {{ rec.vehicle_name }}
                                            <span class="text-muted">· {{ rec.recommendation_reason }} · {{ rec.location_state }}</span>
                                            <button class="btn btn-sm btn-svtp ms-2" @click="assignRecommendation(booking, rec)">Assign</button>
                                        </li>
                                    </ul>
                                    <div v-else class="text-muted">No eligible drivers currently available.</div>
                                </td>
                            </tr>
                            <tr v-if="eligibleCache[booking.id]">
                                <td colspan="7" class="small">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <strong>Drivers:</strong>
                                            <ul class="mb-0 ps-3">
                                                <li v-for="d in eligibleCache[booking.id].drivers.slice(0, 6)" :key="d.id">
                                                    {{ d.name }} — <span :class="d.eligible ? 'text-success' : 'text-danger'">{{ d.eligible ? 'eligible' : d.reason }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Vehicles:</strong>
                                            <ul class="mb-0 ps-3">
                                                <li v-for="v in eligibleCache[booking.id].vehicles.slice(0, 6)" :key="v.id">
                                                    {{ v.name }} ({{ v.registration_number }}) — <span :class="v.eligible ? 'text-success' : 'text-danger'">{{ v.eligible ? 'eligible' : v.reason }}</span>
                                                </li>
                                            </ul>
                                            <div class="mt-2 d-flex gap-2">
                                                <input v-model="noteForm(booking).body" class="form-control form-control-sm" placeholder="Ops note: customer contacted, delay…" maxlength="2000" />
                                                <button class="btn btn-sm btn-svtp" @click="saveNote(booking)">Note</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!board.data.length"><td colspan="7" class="text-center text-muted py-4">No trips match this dispatch view.</td></tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="board.links" class="px-3 py-2" />
        </div>
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); }
</style>
