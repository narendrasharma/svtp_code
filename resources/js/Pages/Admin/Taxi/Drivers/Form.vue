<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    driver: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
    availabilityStatuses: { type: Array, default: () => [] },
    employmentStatuses: { type: Array, default: () => [] },
});

const form = useForm({
    vendor_profile_id: props.driver?.vendor_profile_id ?? (props.vendors[0]?.id ?? ''),
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
        form.put(appUrl(`/admin/taxi/drivers/${props.driver.id}`), { preserveScroll: true });
    } else {
        form.post(appUrl('/admin/taxi/drivers'), { preserveScroll: true });
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">{{ isEdit ? 'Edit driver' : 'Add driver' }}</h2>
                <p class="text-muted mb-0">{{ isEdit ? 'Update driver details and employment status.' : 'Register a new driver for taxi bookings.' }}</p>
            </div>
            <Link :href="appUrl('/admin/taxi/drivers')" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Back to drivers</Link>
        </div>

        <form class="card" @submit.prevent="submit">
            <div class="card-body">
                <h5 class="card-title mb-3">Driver information</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small">Vendor</label>
                        <select v-model="form.vendor_profile_id" class="form-select" required>
                            <option value="">Select vendor</option>
                            <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">First name</label>
                        <input v-model="form.first_name" type="text" class="form-control" maxlength="80" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Last name</label>
                        <input v-model="form.last_name" type="text" class="form-control" maxlength="80" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Phone</label>
                        <input v-model="form.phone" type="text" class="form-control" maxlength="30" required />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Email</label>
                        <input v-model="form.email" type="email" class="form-control" maxlength="150" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Date of birth</label>
                        <input v-model="form.date_of_birth" type="date" class="form-control" />
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Address</label>
                        <textarea v-model="form.address" class="form-control" rows="2" maxlength="1000"></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Emergency contact name</label>
                        <input v-model="form.emergency_contact_name" type="text" class="form-control" maxlength="120" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Emergency contact phone</label>
                        <input v-model="form.emergency_contact_phone" type="text" class="form-control" maxlength="30" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Joining date</label>
                        <input v-model="form.joining_date" type="date" class="form-control" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Driver type</label>
                        <input v-model="form.driver_type" type="text" class="form-control" maxlength="30" placeholder="Full-time, contract, etc." />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Availability</label>
                        <select v-model="form.availability_status" class="form-select">
                            <option v-for="status in availabilityStatuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Employment status</label>
                        <select v-model="form.employment_status" class="form-select">
                            <option v-for="status in employmentStatuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2 align-items-center">
                        <input id="driver-active" v-model="form.is_active" type="checkbox" class="form-check-input" />
                        <label for="driver-active" class="form-check-label small">Driver is active</label>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Notes</label>
                        <textarea v-model="form.notes" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <button class="btn btn-svtp" :disabled="form.processing">{{ isEdit ? 'Update driver' : 'Create driver' }}</button>
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
