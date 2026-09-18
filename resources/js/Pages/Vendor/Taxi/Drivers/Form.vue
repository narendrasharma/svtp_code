<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    driver: { type: Object, default: null },
    availabilityStatuses: { type: Array, default: () => [] },
    employmentStatuses: { type: Array, default: () => [] },
});

const form = useForm({
    first_name: props.driver?.first_name ?? '',
    last_name: props.driver?.last_name ?? '',
    phone: props.driver?.phone ?? '',
    email: props.driver?.email ?? '',
    date_of_birth: props.driver?.date_of_birth ?? '',
    address: props.driver?.address ?? '',
    emergency_contact_name: props.driver?.emergency_contact_name ?? '',
    emergency_contact_phone: props.driver?.emergency_contact_phone ?? '',
    joining_date: props.driver?.joining_date ?? '',
    driver_type: props.driver?.driver_type ?? '',
    availability_status: props.driver?.availability_status ?? (props.availabilityStatuses[0]?.value ?? 'offline'),
    employment_status: props.driver?.employment_status ?? (props.employmentStatuses[0]?.value ?? 'active'),
    is_active: props.driver?.is_active ?? true,
    notes: props.driver?.notes ?? '',
});

const isEdit = computed(() => Boolean(props.driver));

function submit() {
    if (isEdit.value) {
        form.put(appUrl(`/vendor/taxi/drivers/${props.driver.id}`));
    } else {
        form.post(appUrl('/vendor/taxi/drivers'));
    }
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">{{ isEdit ? 'Edit driver' : 'Add driver' }}</h2>
                <p class="text-muted mb-0">{{ isEdit ? 'Update driver contact and availability information.' : 'Register a driver for taxi operations.' }}</p>
            </div>
            <Link :href="appUrl('/vendor/taxi/drivers')" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</Link>
        </div>

        <form class="card" @submit.prevent="submit">
            <div class="card-body p-4 p-md-5">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">First name</label>
                        <input v-model="form.first_name" type="text" class="form-control" maxlength="80" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last name</label>
                        <input v-model="form.last_name" type="text" class="form-control" maxlength="80" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input v-model="form.phone" type="text" class="form-control" maxlength="30" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input v-model="form.email" type="email" class="form-control" maxlength="150" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date of birth</label>
                        <input v-model="form.date_of_birth" type="date" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Driver type</label>
                        <input v-model="form.driver_type" type="text" class="form-control" maxlength="30" placeholder="Full-time, contract, etc." />
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea v-model="form.address" class="form-control" rows="2" maxlength="1000"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Emergency contact name</label>
                        <input v-model="form.emergency_contact_name" type="text" class="form-control" maxlength="120" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Emergency contact phone</label>
                        <input v-model="form.emergency_contact_phone" type="text" class="form-control" maxlength="30" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Joining date</label>
                        <input v-model="form.joining_date" type="date" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Availability</label>
                        <select v-model="form.availability_status" class="form-select">
                            <option v-for="status in availabilityStatuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Employment status</label>
                        <select v-model="form.employment_status" class="form-select">
                            <option v-for="status in employmentStatuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2 align-items-center">
                        <input id="driver-active" v-model="form.is_active" type="checkbox" class="form-check-input" />
                        <label for="driver-active" class="form-check-label">Driver active</label>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.notes" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary" :disabled="form.processing">{{ isEdit ? 'Update driver' : 'Save driver' }}</button>
            </div>
        </form>
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 1.5rem; border: 1px solid #e2e8f0; }
.form-label { font-weight: 500; }
</style>
