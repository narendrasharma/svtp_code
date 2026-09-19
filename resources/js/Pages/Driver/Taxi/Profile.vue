<script setup>
import { useForm } from '@inertiajs/vue3';
import DriverLayout from '../../../Layouts/DriverLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    driver: { type: Object, required: true },
    availabilityOptions: { type: Array, default: () => [] },
    leave: { type: Array, default: () => [] },
});

const endpoint = appUrl('/driver/taxi/profile');

const form = useForm({
    phone: props.driver.phone ?? '',
    address: props.driver.address ?? '',
    emergency_contact_name: props.driver.emergency_contact_name ?? '',
    emergency_contact_phone: props.driver.emergency_contact_phone ?? '',
});

const availabilityForm = useForm({
    availability_status: props.driver.availability_status ?? 'offline',
});

function saveProfile() {
    form.put(endpoint, { preserveScroll: true });
}

function saveAvailability() {
    availabilityForm.patch(`${endpoint}/availability`, { preserveScroll: true });
}

function pickup(value) {
    return value ? new Date(value).toLocaleString('en-IN', { day: 'numeric', month: 'short' }) : '—';
}
</script>

<template>
    <DriverLayout>
        <h2 class="mt-2 mb-1">Profile</h2>
        <p class="text-muted">{{ driver.first_name }} {{ driver.last_name }} · {{ driver.vendor_profile?.business_name ?? '' }}</p>

        <div class="card p-3 mb-3">
            <strong>Availability</strong>
            <p class="small text-muted mb-2">Only Available / Off Duty can be set here. Leave is managed by your vendor.</p>
            <div class="d-flex gap-2">
                <select v-model="availabilityForm.availability_status" class="form-select">
                    <option v-for="option in availabilityOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <button class="btn btn-svtp" :disabled="availabilityForm.processing" @click="saveAvailability">Save</button>
            </div>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="saveProfile">
            <strong>Contact details</strong>
            <div class="mt-2">
                <label class="form-label small">Phone</label>
                <input v-model="form.phone" class="form-control" maxlength="30" required />
            </div>
            <div class="mt-2">
                <label class="form-label small">Address</label>
                <input v-model="form.address" class="form-control" maxlength="500" />
            </div>
            <div class="mt-2">
                <label class="form-label small">Emergency contact name</label>
                <input v-model="form.emergency_contact_name" class="form-control" maxlength="120" />
            </div>
            <div class="mt-2">
                <label class="form-label small">Emergency contact phone</label>
                <input v-model="form.emergency_contact_phone" class="form-control" maxlength="30" />
            </div>
            <button class="btn btn-svtp w-100 mt-3" :disabled="form.processing">Save profile</button>
        </form>

        <div class="card p-3 mb-3 small">
            <div class="row g-2">
                <div class="col-6 text-muted">Employment</div>
                <div class="col-6 text-end">{{ driver.employment_status }}</div>
                <div class="col-6 text-muted">Driver type</div>
                <div class="col-6 text-end">{{ driver.driver_type ?? '—' }}</div>
                <div class="col-6 text-muted">Joined</div>
                <div class="col-6 text-end">{{ driver.joining_date ?? '—' }}</div>
            </div>
        </div>

        <div v-if="driver.documents?.length" class="card p-3 mb-3">
            <strong>Documents</strong>
            <div v-for="doc in driver.documents" :key="doc.id" class="small text-muted">{{ doc.document_type }} · {{ doc.status }}<span v-if="doc.expiry_date"> · expires {{ doc.expiry_date }}</span></div>
        </div>

        <div v-if="leave.length" class="card p-3 mb-3">
            <strong>Time off</strong>
            <div v-for="window in leave" :key="window.id" class="small text-muted">{{ window.status }}: {{ pickup(window.from_at) }} → {{ pickup(window.to_at) }}</div>
        </div>
    </DriverLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); }
</style>
