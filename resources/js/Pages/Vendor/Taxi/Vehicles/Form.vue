<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    vehicle: { type: Object, default: null },
    vehicleTypes: { type: Array, default: () => [] },
});

const statuses = [
    { value: 'available', label: 'Available' },
    { value: 'assigned', label: 'Assigned' },
    { value: 'on_trip', label: 'On trip' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'out_of_service', label: 'Out of service' },
];

const form = useForm({
    vehicle_type_id: props.vehicle?.vehicle_type_id ?? '',
    name: props.vehicle?.name ?? '',
    registration_number: props.vehicle?.registration_number ?? '',
    make: props.vehicle?.make ?? '',
    model: props.vehicle?.model ?? '',
    year: props.vehicle?.year ?? '',
    color: props.vehicle?.color ?? '',
    passenger_capacity: props.vehicle?.passenger_capacity ?? 4,
    luggage_capacity: props.vehicle?.luggage_capacity ?? 2,
    fuel_type: props.vehicle?.fuel_type ?? '',
    transmission: props.vehicle?.transmission ?? '',
    is_air_conditioned: props.vehicle?.is_air_conditioned ?? true,
    status: props.vehicle?.status ?? 'available',
    is_active: props.vehicle?.is_active ?? true,
    notes: props.vehicle?.notes ?? '',
});

const isEdit = computed(() => Boolean(props.vehicle));

function submit() {
    if (isEdit.value) {
        form.put(appUrl(`/vendor/taxi/vehicles/${props.vehicle.id}`));
    } else {
        form.post(appUrl('/vendor/taxi/vehicles'));
    }
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">{{ isEdit ? 'Edit vehicle' : 'Add vehicle' }}</h2>
                <p class="text-muted mb-0">{{ isEdit ? 'Update details for this vehicle.' : 'Register a car you will dispatch for rides.' }}</p>
            </div>
            <Link :href="appUrl('/vendor/taxi/vehicles')" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</Link>
        </div>

        <form class="card" @submit.prevent="submit">
            <div class="card-body p-4 p-md-5">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Vehicle type</label>
                        <select v-model="form.vehicle_type_id" class="form-select">
                            <option value="">Select type</option>
                            <option v-for="type in vehicleTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select v-model="form.status" class="form-select">
                            <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-center gap-2">
                        <input id="active" v-model="form.is_active" type="checkbox" class="form-check-input" />
                        <label class="form-check-label" for="active">Vehicle active</label>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input v-model="form.name" type="text" class="form-control" maxlength="120" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Registration number</label>
                        <input v-model="form.registration_number" type="text" class="form-control" maxlength="30" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Make</label>
                        <input v-model="form.make" type="text" class="form-control" maxlength="60" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Model</label>
                        <input v-model="form.model" type="text" class="form-control" maxlength="60" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Year</label>
                        <input v-model="form.year" type="number" min="1990" max="2100" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Color</label>
                        <input v-model="form.color" type="text" class="form-control" maxlength="40" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Passenger capacity</label>
                        <input v-model.number="form.passenger_capacity" type="number" min="1" max="60" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Luggage capacity</label>
                        <input v-model.number="form.luggage_capacity" type="number" min="0" max="60" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fuel type</label>
                        <select v-model="form.fuel_type" class="form-select">
                            <option value="">Select</option>
                            <option value="petrol">Petrol</option>
                            <option value="diesel">Diesel</option>
                            <option value="cng">CNG</option>
                            <option value="electric">Electric</option>
                            <option value="hybrid">Hybrid</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Transmission</label>
                        <select v-model="form.transmission" class="form-select">
                            <option value="">Select</option>
                            <option value="manual">Manual</option>
                            <option value="automatic">Automatic</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-center gap-2">
                        <input id="airconditioned" v-model="form.is_air_conditioned" type="checkbox" class="form-check-input" />
                        <label for="airconditioned" class="form-check-label">Air conditioned</label>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.notes" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <button class="btn btn-primary" :disabled="form.processing">{{ isEdit ? 'Update vehicle' : 'Save vehicle' }}</button>
            </div>
        </form>
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 1.5rem; border: 1px solid #e2e8f0; }
.form-label { font-weight: 500; }
</style>
