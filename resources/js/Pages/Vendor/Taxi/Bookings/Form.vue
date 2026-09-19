<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    vehicleTypes: { type: Array, default: () => [] },
    fixedVendor: { type: Boolean, default: true },
    tripTypes: { type: Array, default: () => [] },
    rentalPackages: { type: Array, default: () => [] },
});

const form = useForm({
    trip_type: 'one_way',
    pickup_at: '',
    return_at: '',
    pickup_address: '',
    drop_address: '',
    passenger_count: 1,
    luggage_count: 0,
    vehicle_type_id: '',
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    special_instructions: '',
    airport_direction: '',
    quoted_distance_km: '',
    quoted_duration_minutes: '',
    waiting_minutes: 0,
    toll_amount: 0,
    parking_amount: 0,
    rental_package_id: '',
});

const needsReturn = computed(() => ['round_trip', 'outstation'].includes(form.trip_type));
const isHourly = computed(() => form.trip_type === 'hourly');
const isAirport = computed(() => form.trip_type === 'airport_transfer');
const quote = ref(null);
const quoteError = ref('');
const quoteLoading = ref(false);

async function previewPrice() {
    quoteLoading.value = true;
    quoteError.value = '';
    try {
        const response = await axios.post(appUrl('/vendor/taxi/bookings/quote'), form.data());
        quote.value = response.data;
    } catch (error) {
        quote.value = null;
        quoteError.value = Object.values(error.response?.data?.errors ?? {})[0]?.[0] ?? 'Unable to calculate pricing.';
    } finally {
        quoteLoading.value = false;
    }
}

function submit() {
    form.post(appUrl('/vendor/taxi/bookings'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Create taxi booking</h2>
                <p class="text-muted mb-0">Confirm a ride for your customer.</p>
            </div>
            <Link :href="appUrl('/vendor/taxi/bookings')" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back to bookings</Link>
        </div>

        <form class="card" @submit.prevent="submit">
            <div class="card-body p-4 p-md-5">
                <h5 class="mb-3">Trip details</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Trip type</label>
                        <select v-model="form.trip_type" class="form-select">
                            <option v-for="type in tripTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Pickup time</label>
                        <input v-model="form.pickup_at" type="datetime-local" class="form-control" required />
                    </div>
                    <div v-if="needsReturn" class="col-md-4">
                        <label class="form-label">Return time</label>
                        <input v-model="form.return_at" type="datetime-local" class="form-control" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Vehicle preference</label>
                        <select v-model="form.vehicle_type_id" class="form-select">
                            <option value="">Any</option>
                            <option v-for="type in vehicleTypes" :key="type.id" :value="type.id">{{ type.name }} · {{ type.passenger_capacity }} pax</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Pickup address</label>
                        <input v-model="form.pickup_address" type="text" class="form-control" maxlength="500" required />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Drop address</label>
                        <input v-model="form.drop_address" type="text" class="form-control" maxlength="500" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Passengers</label>
                        <input v-model.number="form.passenger_count" type="number" min="1" max="60" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Luggage</label>
                        <input v-model.number="form.luggage_count" type="number" min="0" max="60" class="form-control" />
                    </div>
                    <div class="col-md-4"><label class="form-label">Distance (km)</label><input v-model="form.quoted_distance_km" type="number" min="0" step="0.1" class="form-control" /></div>
                    <div class="col-md-4"><label class="form-label">Duration (minutes)</label><input v-model="form.quoted_duration_minutes" type="number" min="0" class="form-control" /></div>
                    <div v-if="isAirport" class="col-md-4"><label class="form-label">Airport direction</label><select v-model="form.airport_direction" class="form-select" required><option value="">Select</option><option value="airport_pickup">Airport pickup</option><option value="airport_drop">Airport drop</option></select></div>
                    <div v-if="isHourly" class="col-md-6"><label class="form-label">Rental package</label><select v-model="form.rental_package_id" class="form-select" required><option value="">Select package</option><option v-for="item in rentalPackages" :key="item.id" :value="item.id">{{ item.name }} · {{ item.rate_card?.name }}</option></select></div>
                </div>

                <hr class="my-4" />

                <h5 class="mb-3">Customer</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input v-model="form.customer_name" type="text" class="form-control" maxlength="150" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input v-model="form.customer_phone" type="text" class="form-control" maxlength="30" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input v-model="form.customer_email" type="email" class="form-control" maxlength="150" />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.special_instructions" class="form-control" rows="3" maxlength="2000" placeholder="Flight details, pickup instructions, etc."></textarea>
                    </div>
                </div>

                <hr class="my-4" />

                <h5 class="mb-3">Pricing</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Waiting minutes</label>
                        <input v-model="form.waiting_minutes" type="number" min="0" class="form-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Actual toll</label>
                        <input v-model="form.toll_amount" type="number" step="0.01" min="0" class="form-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Actual parking</label>
                        <input v-model="form.parking_amount" type="number" step="0.01" min="0" class="form-control" />
                    </div>
                    <div class="col-md-3 d-flex align-items-end"><button type="button" class="btn btn-outline-primary w-100" :disabled="quoteLoading" @click="previewPrice">{{ quoteLoading ? 'Calculating…' : 'Preview price' }}</button></div>
                </div>
                <div v-if="quoteError" class="alert alert-danger mt-3 mb-0">{{ quoteError }}</div>
                <div v-if="quote" class="border rounded p-3 mt-3"><div class="small text-muted mb-2">{{ quote.rate_card.name }} · {{ quote.currency }}</div><div v-for="(amount, key) in quote.breakdown" :key="key" class="d-flex justify-content-between"><span class="text-capitalize">{{ String(key).replaceAll('_', ' ') }}</span><strong>{{ quote.currency }} {{ amount }}</strong></div><p class="small text-muted mt-2 mb-0">Preview only. Submission is recalculated server-side.</p></div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary" :disabled="form.processing">Confirm booking</button>
            </div>
        </form>
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 1.5rem; border: 1px solid #e2e8f0; }
.form-label { font-weight: 500; }
</style>
