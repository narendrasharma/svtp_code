<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';

const props = defineProps({
    securityQuestion: { type: String, required: true },
    tourPackageId: { type: Number, default: null },
    idPrefix: { type: String, default: 'tour-enquiry' },
});

const submitted = ref(false);
const today = new Date().toISOString().slice(0, 10);
const form = useForm({
    enquiry_type: 'tour_plan',
    tour_package_id: props.tourPackageId,
    full_name: '',
    phone: '',
    email: '',
    pickup_drop: '',
    hotel_category: '',
    adults: 2,
    children: 0,
    arrival_date: '',
    departure_date: '',
    message: '',
    security_answer: '',
});

function fieldId(field) {
    return `${props.idPrefix}-${field}`;
}

function submit() {
    submitted.value = false;
    form.post(appUrl('/enquiries'), {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
            form.reset();
        },
    });
}
</script>

<template>
    <div>
        <div v-if="submitted" class="alert alert-success">Thank you. Your enquiry has been received.</div>

        <form @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label :for="fieldId('name')" class="form-label">Full Name*</label>
                    <input :id="fieldId('name')" v-model="form.full_name" class="form-control" required />
                    <div v-if="form.errors.full_name" class="text-danger small mt-1">{{ form.errors.full_name }}</div>
                </div>
                <div class="col-md-6">
                    <label :for="fieldId('phone')" class="form-label">Phone Number*</label>
                    <input :id="fieldId('phone')" v-model="form.phone" type="tel" class="form-control" required />
                    <div v-if="form.errors.phone" class="text-danger small mt-1">{{ form.errors.phone }}</div>
                </div>
                <div class="col-md-6">
                    <label :for="fieldId('email')" class="form-label">Email (optional)</label>
                    <input :id="fieldId('email')" v-model="form.email" type="email" class="form-control" />
                    <div v-if="form.errors.email" class="text-danger small mt-1">{{ form.errors.email }}</div>
                </div>
                <div class="col-md-6">
                    <label :for="fieldId('route')" class="form-label">Pickup &amp; Drop*</label>
                    <input :id="fieldId('route')" v-model="form.pickup_drop" class="form-control" placeholder="e.g. Delhi to Mathura" required />
                    <div v-if="form.errors.pickup_drop" class="text-danger small mt-1">{{ form.errors.pickup_drop }}</div>
                </div>
                <div class="col-md-6">
                    <label :for="fieldId('hotel')" class="form-label">Hotel Category*</label>
                    <select :id="fieldId('hotel')" v-model="form.hotel_category" class="form-select" required>
                        <option value="" disabled>Select a category</option>
                        <option value="budget">Budget</option>
                        <option value="standard">Standard</option>
                        <option value="deluxe">Deluxe</option>
                        <option value="premium">Premium</option>
                    </select>
                    <div v-if="form.errors.hotel_category" class="text-danger small mt-1">{{ form.errors.hotel_category }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <label :for="fieldId('adults')" class="form-label">Adults*</label>
                    <input :id="fieldId('adults')" v-model.number="form.adults" type="number" min="1" class="form-control" required />
                    <div v-if="form.errors.adults" class="text-danger small mt-1">{{ form.errors.adults }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <label :for="fieldId('children')" class="form-label">Children</label>
                    <input :id="fieldId('children')" v-model.number="form.children" type="number" min="0" class="form-control" />
                    <div v-if="form.errors.children" class="text-danger small mt-1">{{ form.errors.children }}</div>
                </div>
                <div class="col-md-6">
                    <label :for="fieldId('arrival')" class="form-label">Arrival Date*</label>
                    <input :id="fieldId('arrival')" v-model="form.arrival_date" type="date" :min="today" class="form-control" required />
                    <div v-if="form.errors.arrival_date" class="text-danger small mt-1">{{ form.errors.arrival_date }}</div>
                </div>
                <div class="col-md-6">
                    <label :for="fieldId('departure')" class="form-label">Departure Date*</label>
                    <input :id="fieldId('departure')" v-model="form.departure_date" type="date" :min="form.arrival_date || today" class="form-control" required />
                    <div v-if="form.errors.departure_date" class="text-danger small mt-1">{{ form.errors.departure_date }}</div>
                </div>
                <div class="col-12">
                    <label :for="fieldId('message')" class="form-label">Message / Requirements</label>
                    <textarea :id="fieldId('message')" v-model="form.message" class="form-control" rows="4"></textarea>
                    <div v-if="form.errors.message" class="text-danger small mt-1">{{ form.errors.message }}</div>
                </div>
                <div class="col-md-6">
                    <label :for="fieldId('security')" class="form-label">Math security check*: {{ securityQuestion }}</label>
                    <input :id="fieldId('security')" v-model="form.security_answer" type="number" class="form-control" required />
                    <div v-if="form.errors.security_answer" class="text-danger small mt-1">{{ form.errors.security_answer }}</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-svtp" :disabled="form.processing">
                        {{ form.processing ? 'Submitting...' : 'Submit Tour Enquiry' }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>
