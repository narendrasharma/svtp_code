<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    vehicleTypes: { type: Array, default: () => [] },
    fixedVendor: { type: Boolean, default: true },
});

const form = useForm({
    trip_type: 'one_way',
    pickup_at: '',
    pickup_address: '',
    drop_address: '',
    passenger_count: 1,
    luggage_count: 0,
    vehicle_type_id: '',
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    special_instructions: '',
    base_amount: '',
    extra_amount: '',
    discount_amount: '',
    tax_amount: '',
});

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
                            <option value="one_way">One-way</option>
                            <option value="airport_transfer">Airport transfer</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Pickup time</label>
                        <input v-model="form.pickup_at" type="datetime-local" class="form-control" required />
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

                <h5 class="mb-3">Pricing snapshot</h5>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Base amount</label>
                        <input v-model="form.base_amount" type="number" step="0.01" min="0" class="form-control" required />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Extra amount</label>
                        <input v-model="form.extra_amount" type="number" step="0.01" min="0" class="form-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Discount</label>
                        <input v-model="form.discount_amount" type="number" step="0.01" min="0" class="form-control" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tax</label>
                        <input v-model="form.tax_amount" type="number" step="0.01" min="0" class="form-control" />
                    </div>
                </div>
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
