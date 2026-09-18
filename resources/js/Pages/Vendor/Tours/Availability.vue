<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ tour: Object, blackouts: { type: Array, default: () => [] } });
const base = appUrl(`/vendor/tours/${props.tour.id}`);

const settings = useForm({
    booking_enabled: !!props.tour.booking_enabled,
    available_weekdays: props.tour.available_weekdays ?? [],
    min_advance_days: props.tour.min_advance_days ?? 0,
    max_advance_days: props.tour.max_advance_days ?? '',
});

const blackout = useForm({ date: '', reason: '' });
const weekdays = [
    { value: 0, label: 'Sun' }, { value: 1, label: 'Mon' }, { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' }, { value: 4, label: 'Thu' }, { value: 5, label: 'Fri' }, { value: 6, label: 'Sat' },
];

function save() {
    settings.put(`${base}/availability`, { preserveScroll: true });
}
function block() {
    blackout.post(`${base}/blackouts`, { preserveScroll: true, onSuccess: () => blackout.reset() });
}
function unblock(row) {
    router.delete(`${base}/blackouts/${row.id}`, { preserveScroll: true });
}
</script>

<template>
    <VendorLayout>
        <div class="mb-4">
            <Link :href="appUrl('/vendor/tours')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>All Tours</Link>
            <h2 class="mt-2 mb-1">Availability — {{ tour.title }}</h2>
            <p class="text-muted mb-0 small">Control which dates guests can book.</p>
        </div>
        <div class="row g-3">
            <div class="col-lg-6">
                <form class="card p-3 p-md-4" @submit.prevent="save">
                    <h5 class="mb-3">Booking Rules</h5>
                    <div class="form-check mb-3"><input id="enabled" v-model="settings.booking_enabled" type="checkbox" class="form-check-input" /><label for="enabled" class="form-check-label">Online booking enabled</label></div>
                    <label class="form-label small">Available weekdays (none = all)</label>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <div v-for="day in weekdays" :key="day.value" class="form-check"><input :id="`wd-${day.value}`" v-model="settings.available_weekdays" :value="day.value" type="checkbox" class="form-check-input" /><label :for="`wd-${day.value}`" class="form-check-label small">{{ day.label }}</label></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><label for="min-adv" class="form-label small">Min advance (days)</label><input id="min-adv" v-model="settings.min_advance_days" type="number" min="0" max="365" class="form-control form-control-sm" /></div>
                        <div class="col-6"><label for="max-adv" class="form-label small">Max advance (days)</label><input id="max-adv" v-model="settings.max_advance_days" type="number" min="1" max="730" class="form-control form-control-sm" placeholder="No limit" /></div>
                    </div>
                    <button class="btn btn-svtp btn-sm mt-3" :disabled="settings.processing">Save Rules</button>
                </form>
            </div>
            <div class="col-lg-6">
                <form class="card p-3 p-md-4 mb-3" @submit.prevent="block">
                    <h5 class="mb-3">Block a Date</h5>
                    <div class="row g-2">
                        <div class="col-6"><label for="block-date" class="form-label small">Date*</label><input id="block-date" v-model="blackout.date" type="date" class="form-control form-control-sm" required /></div>
                        <div class="col-6"><label for="block-reason" class="form-label small">Reason</label><input id="block-reason" v-model="blackout.reason" class="form-control form-control-sm" maxlength="255" /></div>
                        <div class="col-12"><button class="btn btn-outline-danger btn-sm" :disabled="blackout.processing">Block Date</button></div>
                    </div>
                </form>
                <div class="card p-3 p-md-4">
                    <h5 class="mb-3">Blocked Dates</h5>
                    <div v-for="row in blackouts" :key="row.id" class="d-flex gap-2 align-items-center border rounded p-2 mb-2 small">
                        <strong>{{ String(row.date).slice(0, 10) }}</strong><span class="text-muted">{{ row.reason || '—' }}</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" @click="unblock(row)">Remove</button>
                    </div>
                    <p v-if="!blackouts.length" class="text-muted small mb-0">No blocked dates.</p>
                </div>
            </div>
        </div>
    </VendorLayout>
</template>
