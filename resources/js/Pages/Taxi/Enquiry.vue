<script setup>
import { usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { computed } from 'vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    vehicleTypes: { type: Array, default: () => [] },
});

const page = usePage();
const securityQuestion = computed(() => page.props.securityQuestion ?? null);

const form = useForm({
    trip_type: 'one_way',
    pickup_address: '',
    drop_address: '',
    pickup_at: '',
    passenger_count: 1,
    vehicle_type_id: '',
    name: '',
    phone: '',
    email: '',
    notes: '',
});

function submit() {
    form.post(appUrl('/taxi/enquiry'), {
        preserveScroll: true,
        onSuccess: () => form.reset('pickup_address', 'drop_address', 'pickup_at', 'passenger_count', 'vehicle_type_id', 'notes'),
    });
}
</script>

<template>
    <AppLayout>
        <section class="enquiry-hero py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center">
                        <h1 class="display-5 fw-semibold text-white mb-3">Plan your taxi ride</h1>
                        <p class="lead text-white-50 mb-0">Tell us about your pickup and destination and our team will confirm the ride with you.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-5">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card shadow-sm">
                            <div class="card-body p-4 p-md-5">
                                <h2 class="h4 mb-4">Taxi enquiry</h2>
                                <form @submit.prevent="submit" class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Trip type</label>
                                        <select v-model="form.trip_type" class="form-select">
                                            <option value="one_way">One way</option>
                                            <option value="airport_transfer">Airport transfer</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Preferred pickup time</label>
                                        <input v-model="form.pickup_at" type="datetime-local" class="form-control" required />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Pickup address</label>
                                        <input v-model="form.pickup_address" type="text" class="form-control" maxlength="500" required />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Drop address</label>
                                        <input v-model="form.drop_address" type="text" class="form-control" maxlength="500" required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Passengers</label>
                                        <input v-model.number="form.passenger_count" type="number" min="1" max="60" class="form-control" />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Vehicle preference</label>
                                        <select v-model="form.vehicle_type_id" class="form-select">
                                            <option value="">Any vehicle</option>
                                            <option v-for="type in vehicleTypes" :key="type.id" :value="type.id">{{ type.name }} · {{ type.passenger_capacity }} pax</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Your name</label>
                                        <input v-model="form.name" type="text" class="form-control" maxlength="150" required />
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input v-model="form.phone" type="text" class="form-control" maxlength="30" required />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Email (optional)</label>
                                        <input v-model="form.email" type="email" class="form-control" maxlength="150" />
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Notes</label>
                                        <textarea v-model="form.notes" class="form-control" rows="4" maxlength="1000" placeholder="Flight details, luggage information, hotel pickup, etc."></textarea>
                                    </div>
                                    <div v-if="securityQuestion" class="col-12">
                                        <label class="form-label">Security question</label>
                                        <input :value="securityQuestion" class="form-control-plaintext" readonly />
                                        <div class="form-text">Provide your answer during the confirmation call.</div>
                                    </div>
                                    <div class="col-12">
                                        <button class="btn btn-primary px-4" :disabled="form.processing">Send enquiry</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.enquiry-hero { background: radial-gradient(circle at top left, #2563eb, #0f172a 60%); }
.card { border: none; border-radius: 1.5rem; }
.form-label { font-weight: 500; }
</style>
