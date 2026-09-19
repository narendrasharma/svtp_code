<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    vehicleTypes: { type: Array, default: () => [] },
    vendors: { type: Array, default: () => [] },
    tripTypes: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    rentalPackages: { type: Array, default: () => [] },
});

const defaultTrip = props.tripTypes.find(type => type && type.value) ?? { value: 'one_way' };
const defaultSource = props.sources?.[0] ?? 'admin';
const defaultVendor = props.vendors?.[0]?.id ?? '';

const form = useForm({
    vendor_profile_id: defaultVendor,
    customer_user_id: '',
    lead_id: '',
    trip_type: defaultTrip.value,
    pickup_at: '',
    return_at: '',
    pickup_address: '',
    pickup_lat: '',
    pickup_lng: '',
    drop_address: '',
    drop_lat: '',
    drop_lng: '',
    airport_direction: '',
    flight_number: '',
    airline: '',
    terminal: '',
    passenger_count: 1,
    luggage_count: 0,
    vehicle_type_id: '',
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    special_instructions: '',
    source: defaultSource,
    quoted_distance_km: '',
    quoted_duration_minutes: '',
    waiting_minutes: 0,
    toll_amount: 0,
    parking_amount: 0,
    rental_package_id: '',
});

const vendors = computed(() => props.vendors ?? []);
const vehicleTypes = computed(() => props.vehicleTypes ?? []);
const tripTypes = computed(() => props.tripTypes ?? []);
const sources = computed(() => props.sources ?? []);

const convertId = ref('');

const endpoint = appUrl('/admin/taxi/bookings');

const isAirport = computed(() => form.trip_type === 'airport_transfer');
const needsReturn = computed(() => ['round_trip', 'outstation'].includes(form.trip_type));
const isHourly = computed(() => form.trip_type === 'hourly');
const quote = ref(null);
const quoteError = ref('');
const quoteLoading = ref(false);

async function previewPrice() {
    quoteLoading.value = true;
    quoteError.value = '';
    try {
        const response = await axios.post(appUrl('/admin/taxi/bookings/quote'), form.data());
        quote.value = response.data;
    } catch (error) {
        quote.value = null;
        quoteError.value = Object.values(error.response?.data?.errors ?? {})[0]?.[0] ?? 'Unable to calculate pricing.';
    } finally {
        quoteLoading.value = false;
    }
}

function submitCreate() {
    form.transform(data => data).post(endpoint, {
        preserveScroll: true,
        onFinish: () => form.transform(data => data),
    });
}

function submitConvert() {
    if (!convertId.value) {
        alert('Enter a quotation reference to convert.');
        return;
    }

    form.transform(data => ({
        ...data,
        quotation_id: convertId.value,
    })).post(appUrl('/admin/taxi/bookings/convert'), {
        preserveScroll: true,
        onFinish: () => form.transform(data => data),
    });
}

function setTripType(value) {
    form.trip_type = value;
    if (value !== 'airport_transfer') {
        form.airport_direction = '';
        form.flight_number = '';
        form.airline = '';
        form.terminal = '';
    }
    quote.value = null;
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Manual Taxi Booking</h2>
                <p class="text-muted mb-0">Confirm a guest ride or convert an accepted quotation.</p>
            </div>
            <Link :href="appUrl('/admin/taxi/bookings')" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Back to bookings</Link>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xxl-8">
                <form class="card" @submit.prevent="submitCreate">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Trip details</h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">Vendor (optional)</label>
                                <select v-model="form.vendor_profile_id" class="form-select">
                                    <option value="">Platform managed</option>
                                    <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Trip type</label>
                                <select v-model="form.trip_type" class="form-select" @change="setTripType($event.target.value)">
                                    <option v-for="type in tripTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Source</label>
                                <select v-model="form.source" class="form-select">
                                    <option v-for="source in sources" :key="source" :value="source">{{ source }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Pickup at</label>
                                <input v-model="form.pickup_at" type="datetime-local" class="form-control" required />
                            </div>
                            <div v-if="needsReturn" class="col-md-6">
                                <label class="form-label small">Return at</label>
                                <input v-model="form.return_at" type="datetime-local" class="form-control" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Pickup address</label>
                                <input v-model="form.pickup_address" type="text" class="form-control" maxlength="500" required />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Drop address</label>
                                <input v-model="form.drop_address" type="text" class="form-control" maxlength="500" required />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Passengers</label>
                                <input v-model.number="form.passenger_count" type="number" min="1" class="form-control" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Luggage</label>
                                <input v-model.number="form.luggage_count" type="number" min="0" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Vehicle type</label>
                                <select v-model="form.vehicle_type_id" class="form-select">
                                    <option value="">Any</option>
                                    <option v-for="type in vehicleTypes" :key="type.id" :value="type.id">{{ type.name }} ({{ type.passenger_capacity }} pax)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Quoted distance (km)</label>
                                <input v-model="form.quoted_distance_km" type="number" min="0" step="0.1" class="form-control" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Quoted duration (mins)</label>
                                <input v-model="form.quoted_duration_minutes" type="number" min="0" class="form-control" />
                            </div>
                            <div v-if="isHourly" class="col-md-4">
                                <label class="form-label small">Rental package</label>
                                <select v-model="form.rental_package_id" class="form-select" required><option value="">Select package</option><option v-for="item in rentalPackages" :key="item.id" :value="item.id">{{ item.name }} · {{ item.rate_card?.name }}</option></select>
                            </div>
                        </div>

                        <div v-if="isAirport" class="airport-fields border-top mt-4 pt-3">
                            <h6 class="mb-3">Airport transfer details</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small">Direction</label>
                                    <select v-model="form.airport_direction" class="form-select">
                                        <option value="">Select...</option>
                                        <option value="airport_pickup">Airport pickup</option>
                                        <option value="airport_drop">Airport drop</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Flight number</label>
                                    <input v-model="form.flight_number" type="text" class="form-control" maxlength="20" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Airline</label>
                                    <input v-model="form.airline" type="text" class="form-control" maxlength="80" />
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Terminal</label>
                                    <input v-model="form.terminal" type="text" class="form-control" maxlength="40" />
                                </div>
                            </div>
                        </div>

                        <h5 class="card-title mt-4 mb-3">Customer information</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">Link customer (optional)</label>
                                <input v-model="form.customer_user_id" type="number" min="1" class="form-control" placeholder="Customer user ID" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Link lead (optional)</label>
                                <input v-model="form.lead_id" type="number" min="1" class="form-control" placeholder="Lead ID" />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Customer name</label>
                                <input v-model="form.customer_name" type="text" class="form-control" maxlength="150" required />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Customer phone</label>
                                <input v-model="form.customer_phone" type="text" class="form-control" maxlength="30" required />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">Customer email</label>
                                <input v-model="form.customer_email" type="email" class="form-control" maxlength="150" />
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Notes / special instructions</label>
                                <textarea v-model="form.special_instructions" class="form-control" rows="3" maxlength="2000"></textarea>
                            </div>
                        </div>

                        <h5 class="card-title mt-4 mb-3">Pricing</h5>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small">Waiting minutes</label>
                                <input v-model="form.waiting_minutes" type="number" min="0" class="form-control" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Actual toll</label>
                                <input v-model="form.toll_amount" type="number" step="0.01" min="0" class="form-control" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">Actual parking</label>
                                <input v-model="form.parking_amount" type="number" step="0.01" min="0" class="form-control" />
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-light w-100" :disabled="quoteLoading" @click="previewPrice">{{ quoteLoading ? 'Calculating…' : 'Preview price' }}</button>
                            </div>
                        </div>
                        <div v-if="quoteError" class="alert alert-danger mt-3 mb-0">{{ quoteError }}</div>
                        <div v-if="quote" class="border rounded p-3 mt-3">
                            <div class="small text-muted mb-2">{{ quote.rate_card.name }} · {{ quote.currency }}</div>
                            <div v-for="(amount, key) in quote.breakdown" :key="key" class="d-flex justify-content-between"><span class="text-capitalize">{{ String(key).replaceAll('_', ' ') }}</span><strong>{{ quote.currency }} {{ amount }}</strong></div>
                            <p class="small text-muted mt-2 mb-0">Preview only. The server recalculates when the booking is submitted.</p>
                        </div>
                    </div>
                    <div class="card-footer d-flex gap-2 justify-content-end">
                        <button type="submit" class="btn btn-svtp" :disabled="form.processing">Confirm taxi booking</button>
                    </div>
                </form>
            </div>

            <div class="col-12 col-xxl-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Convert accepted quotation</h5>
                        <p class="text-muted small">Enter an accepted taxi quotation ID to convert it using the booking details above.</p>
                        <div class="mb-3">
                            <label class="form-label small">Quotation ID</label>
                            <input v-model="convertId" type="number" min="1" class="form-control" placeholder="e.g. 152" />
                        </div>
                        <button type="button" class="btn btn-outline-light w-100" :disabled="form.processing" @click="submitConvert">Convert quotation</button>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="fw-semibold">Tips</h6>
                        <ul class="small text-muted mb-0">
                            <li>Vendor, driver and vehicle assignments can be handled after creation.</li>
                            <li>Use the customer user ID to link an existing account for reminders and invoices.</li>
                            <li>Airport transfer fields are required only when the trip type is set to airport transfer.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); background: #101827; color: #e2e8f0; }
.card .form-label { color: #cbd5f5; }
.form-control, .form-select, textarea { background: rgba(15, 23, 42, .65); border-color: rgba(148, 163, 184, .25); color: #f8fafc; }
.form-control:focus, .form-select:focus, textarea:focus { border-color: #f59e0b; box-shadow: 0 0 0 .2rem rgba(245, 158, 11, .18); }
.airport-fields { border-color: rgba(148, 163, 184, .15) !important; }
.btn.btn-outline-light { color: #f8fafc; border-color: rgba(148, 163, 184, .35); }
</style>
