<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DriverLayout from '../../../../Layouts/DriverLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    pending: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const endpoint = appUrl('/driver/taxi/offers');
const rejectReason = ref('');
const rejectTarget = ref(null);

const reasons = [
    { value: '', label: 'No reason' },
    { value: 'unavailable', label: 'Unavailable' },
    { value: 'too_far', label: 'Too far' },
    { value: 'vehicle_issue', label: 'Vehicle issue' },
    { value: 'schedule_conflict', label: 'Schedule conflict' },
    { value: 'other', label: 'Other' },
];

function pickup(value) {
    return value ? new Date(value).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) : '—';
}

function accept(offer) {
    router.post(`${endpoint}/${offer.id}/accept`, {}, { preserveScroll: true });
}

function reject(offer) {
    router.post(`${endpoint}/${offer.id}/reject`, rejectTarget.value === offer.id && rejectReason.value
        ? { reason: rejectReason.value }
        : {}, {
        preserveScroll: true,
        onSuccess: () => { rejectReason.value = ''; rejectTarget.value = null; },
    });
}
</script>

<template>
    <DriverLayout>
        <h2 class="mt-2 mb-1">Job Offers</h2>
        <p class="text-muted">Respond to trips offered to you. Accepting assigns the trip after final checks.</p>

        <div v-if="!pending.length" class="card p-4 text-center text-muted">No pending offers right now.</div>

        <div v-for="offer in pending" :key="offer.id" class="card p-3 mb-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-semibold">{{ offer.booking?.reference }}</span>
                <span class="badge bg-primary">Offer expires {{ pickup(offer.expires_at) }}</span>
            </div>
            <div class="small">{{ pickup(offer.booking?.pickup_at) }}</div>
            <div class="small text-muted">{{ offer.booking?.pickup_address }} → {{ offer.booking?.drop_address }}</div>
            <div v-if="offer.vehicle" class="small text-muted">{{ offer.vehicle.name }} · {{ offer.vehicle.registration_number }}</div>
            <div class="d-grid gap-2 mt-3">
                <button class="btn btn-lg btn-svtp" @click="accept(offer)">Accept trip</button>
                <div class="d-flex gap-2">
                    <select v-model="rejectReason" class="form-select" @focus="rejectTarget = offer.id">
                        <option v-for="option in reasons" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <button class="btn btn-outline-light" @click="reject(offer)">Reject</button>
                </div>
            </div>
        </div>

        <div v-if="history.length" class="card p-3 mt-3">
            <strong>Recent offers</strong>
            <div v-for="offer in history" :key="offer.id" class="small text-muted border-top pt-2 mt-2">
                {{ offer.booking?.reference ?? '—' }} · {{ offer.status }} · {{ pickup(offer.responded_at) }}
            </div>
        </div>

        <Link :href="appUrl('/notifications')" class="btn btn-outline-light w-100 mt-3">View notifications</Link>
    </DriverLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); }
.btn-lg { min-height: 52px; font-size: 1.05rem; }
</style>
