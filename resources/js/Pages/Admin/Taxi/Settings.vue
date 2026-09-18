<script setup>
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
});

function boolSetting(key, fallback = true) {
    const value = props.settings?.[key];
    if (value === undefined || value === null || value === '') {
        return fallback;
    }
    return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
}

const form = useForm({
    taxi_booking_enabled: boolSetting('taxi.booking_enabled', true),
    taxi_one_way_enabled: boolSetting('taxi.one_way_enabled', true),
    taxi_airport_transfer_enabled: boolSetting('taxi.airport_transfer_enabled', true),
    taxi_default_currency: props.settings['taxi.default_currency'] ?? 'INR',
    taxi_min_advance_minutes: Number(props.settings['taxi.min_advance_minutes'] ?? 60),
    taxi_max_advance_days: props.settings['taxi.max_advance_days'] ? Number(props.settings['taxi.max_advance_days']) : '',
    taxi_allow_guest_booking: boolSetting('taxi.allow_guest_booking', true),
    taxi_default_assignment_mode: props.settings['taxi.default_assignment_mode'] ?? 'manual',
});

function submit() {
    form.post(appUrl('/admin/taxi/settings'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Taxi module settings</h2>
                <p class="text-muted mb-0">Control taxi booking availability and booking guardrails.</p>
            </div>
            <Link :href="appUrl('/admin/taxi/bookings')" class="btn btn-outline-light">View taxi bookings</Link>
        </div>

        <form class="card" @submit.prevent="submit">
            <div class="card-body">
                <h5 class="card-title mb-4">Booking availability</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="booking-enabled" v-model="form.taxi_booking_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="booking-enabled">Taxi booking enabled</label>
                        </div>
                        <div class="form-text">Toggle the entire taxi module on/off for staff and vendors.</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="one-way" v-model="form.taxi_one_way_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="one-way">One-way rides enabled</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch">
                            <input id="airport" v-model="form.taxi_airport_transfer_enabled" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="airport">Airport transfers enabled</label>
                        </div>
                    </div>
                </div>

                <hr class="my-4" />

                <h5 class="card-title mb-3">Booking window</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Minimum advance (minutes)</label>
                        <input v-model.number="form.taxi_min_advance_minutes" type="number" min="0" max="10080" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Maximum advance (days)</label>
                        <input v-model="form.taxi_max_advance_days" type="number" min="1" max="365" class="form-control" placeholder="Leave blank for no limit" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Default currency</label>
                        <select v-model="form.taxi_default_currency" class="form-select">
                            <option value="INR">INR</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="AED">AED</option>
                        </select>
                    </div>
                </div>

                <hr class="my-4" />

                <h5 class="card-title mb-3">Booking controls</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input id="guest" v-model="form.taxi_allow_guest_booking" type="checkbox" class="form-check-input" />
                            <label class="form-check-label" for="guest">Allow guest bookings (no customer account)</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Assignment mode</label>
                        <select v-model="form.taxi_default_assignment_mode" class="form-select">
                            <option value="manual">Manual assignment</option>
                        </select>
                        <div class="form-text">Automatic dispatch is planned for a future phase.</div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <button class="btn btn-svtp" :disabled="form.processing">Save taxi settings</button>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); background: #101827; color: #e2e8f0; }
.form-control, .form-select { background: rgba(15, 23, 42, .65); border-color: rgba(148, 163, 184, .25); color: #f8fafc; }
.form-control:focus, .form-select:focus { border-color: #f59e0b; box-shadow: 0 0 0 .2rem rgba(245, 158, 11, .18); }
.form-check-label { color: #e2e8f0; }
.form-text { color: #94a3b8; }
.btn.btn-outline-light { color: #f8fafc; border-color: rgba(148, 163, 184, .35); }
</style>
