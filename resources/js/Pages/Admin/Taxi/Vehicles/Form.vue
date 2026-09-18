<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    vehicle: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
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
    vendor_profile_id: props.vehicle?.vendor_profile_id ?? (props.vendors[0]?.id ?? ''),
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
        form.put(appUrl(`/admin/taxi/vehicles/${props.vehicle.id}`), { preserveScroll: true });
    } else {
        form.post(appUrl('/admin/taxi/vehicles'), { preserveScroll: true });
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">{{ isEdit ? 'Edit vehicle' : 'Add vehicle' }}</h2>
                <p class="text-muted mb-0">{{ isEdit ? 'Update vehicle details, status and capacity.' : 'Register a new vehicle for taxi operations.' }}</p>
            </div>
            <Link :href="appUrl('/admin/taxi/vehicles')" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Back to vehicles</Link>
        </div>

        <form class="card" @submit.prevent="submit">
            <div class="card-body">
                <h5 class="card-title mb-3">Ownership & classification</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label small">Vendor</label>
                        <select v-model="form.vendor_profile_id" class="form-select" required>
                            <option value="">Platform managed</option>
                            <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Vehicle type</label>
                        <select v-model="form.vehicle_type_id" class="form-select">
                            <option value="">Unclassified</option>
                            <option v-for="type in vehicleTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Status</label>
                        <select v-model="form.status" class="form-select">
                            <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </div>
                </div>

                <h5 class="card-title mb-3">Vehicle details</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Name</label>
                        <input v-model="form.name" type="text" class="form-control" maxlength="120" required />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Registration number</label>
                        <input v-model="form.registration_number" type="text" class="form-control" maxlength="30" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Make</label>
                        <input v-model="form.make" type="text" class="form-control" maxlength="60" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Model</label>
                        <input v-model="form.model" type="text" class="form-control" maxlength="60" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Year</label>
                        <input v-model="form.year" type="number" min="1990" max="2100" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Color</label>
                        <input v-model="form.color" type="text" class="form-control" maxlength="40" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Passenger capacity</label>
                        <input v-model.number="form.passenger_capacity" type="number" min="1" max="60" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Luggage capacity</label>
                        <input v-model.number="form.luggage_capacity" type="number" min="0" max="60" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Fuel type</label>
                        <select v-model="form.fuel_type" class="form-select">
                            <option value="">Select fuel</option>
                            <option value="petrol">Petrol</option>
                            <option value="diesel">Diesel</option>
                            <option value="cng">CNG</option>
                            <option value="electric">Electric</option>
                            <option value="hybrid">Hybrid</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Transmission</label>
                        <select v-model="form.transmission" class="form-select">
                            <option value="">Select transmission</option>
                            <option value="manual">Manual</option>
                            <option value="automatic">Automatic</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-center gap-2">
                        <input id="aircon" v-model="form.is_air_conditioned" type="checkbox" class="form-check-input" />
                        <label for="aircon" class="form-check-label small">Air conditioned</label>
                    </div>
                    <div class="col-md-4 d-flex align-items-center gap-2">
                        <input id="active" v-model="form.is_active" type="checkbox" class="form-check-input" />
                        <label for="active" class="form-check-label small">Vehicle active</label>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Internal notes</label>
                        <textarea v-model="form.notes" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2">
                <button class="btn btn-svtp" :disabled="form.processing">{{ isEdit ? 'Update vehicle' : 'Create vehicle' }}</button>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); background: #101827; color: #e2e8f0; }
.form-control, .form-select, textarea { background: rgba(15, 23, 42, .65); border-color: rgba(148, 163, 184, .25); color: #f8fafc; }
.form-control:focus, .form-select:focus, textarea:focus { border-color: #f59e0b; box-shadow: 0 0 0 .2rem rgba(245, 158, 11, .18); }
.btn.btn-outline-light { color: #f8fafc; border-color: rgba(148, 163, 184, .35); }
</style>
