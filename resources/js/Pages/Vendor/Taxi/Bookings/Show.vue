<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    booking: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    fleet: { type: Object, default: () => ({ drivers: [], vehicles: [] }) },
    allowedTransitions: { type: Array, default: () => [] },
});

const booking = computed(() => props.booking);
const summary = computed(() => props.summary ?? { total: 0, paid: 0, due: 0, currency: 'INR' });
const drivers = computed(() => props.fleet?.drivers ?? []);
const vehicles = computed(() => props.fleet?.vehicles ?? []);

const assignForm = useForm({
    driver_id: booking.value.assigned_driver_id ?? '',
    vehicle_id: booking.value.assigned_vehicle_id ?? '',
    note: '',
});

const statusForm = useForm({
    status: '',
    note: '',
});

function formatDate(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}

function badge(status) {
    const map = {
        confirmed: 'bg-info text-dark',
        driver_assigned: 'bg-primary',
        en_route: 'bg-warning text-dark',
        arrived: 'bg-warning text-dark',
        passenger_on_board: 'bg-success',
        completed: 'bg-success',
        cancelled: 'bg-secondary',
        no_show: 'bg-secondary',
        draft: 'bg-light text-dark',
    };
    return map[status] ?? 'bg-secondary';
}

function assign() {
    assignForm.post(appUrl(`/vendor/taxi/bookings/${booking.value.id}/assign`), { preserveScroll: true });
}

function unassign() {
    assignForm.post(appUrl(`/vendor/taxi/bookings/${booking.value.id}/unassign`), { preserveScroll: true });
}

function updateStatus() {
    statusForm.patch(appUrl(`/vendor/taxi/bookings/${booking.value.id}/status`), { preserveScroll: true });
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">{{ booking.reference }}</h2>
                <p class="text-muted mb-0">Pickup {{ formatDate(booking.pickup_at) }}</p>
            </div>
            <Link :href="appUrl('/vendor/taxi/bookings')" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back to bookings</Link>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                            <div>
                                <span class="badge" :class="badge(booking.status)">{{ booking.status }}</span>
                                <div class="mt-2"><strong>Pickup:</strong> {{ booking.pickup_address }}</div>
                                <div class="small text-muted">{{ formatDate(booking.pickup_at) }}</div>
                                <div class="mt-2"><strong>Drop:</strong> {{ booking.drop_address }}</div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-row"><span>Total</span><strong>{{ summary.currency }} {{ Number(summary.total ?? 0).toFixed(2) }}</strong></div>
                                <div class="summary-row"><span>Paid</span><strong>{{ summary.currency }} {{ Number(summary.paid ?? 0).toFixed(2) }}</strong></div>
                                <div class="summary-row"><span>Due</span><strong>{{ summary.currency }} {{ Number(summary.due ?? 0).toFixed(2) }}</strong></div>
                            </div>
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <h6 class="text-uppercase small text-muted">Customer</h6>
                                <div>{{ booking.customer_name || 'Guest' }}</div>
                                <div class="small text-muted">{{ booking.customer_phone }}</div>
                                <div class="small text-muted">{{ booking.customer_email }}</div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-uppercase small text-muted">Assigned driver</h6>
                                <div v-if="booking.assigned_driver">{{ booking.assigned_driver.first_name }} {{ booking.assigned_driver.last_name }}</div>
                                <div v-else class="text-muted">Awaiting assignment</div>
                                <div class="mt-2">
                                    <h6 class="text-uppercase small text-muted">Vehicle</h6>
                                    <div v-if="booking.assigned_vehicle">{{ booking.assigned_vehicle.name }} · {{ booking.assigned_vehicle.registration_number }}</div>
                                    <div v-else class="text-muted">Unassigned</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card mb-3">
                    <div class="card-header"><strong>Assign driver & vehicle</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small">Driver</label>
                            <select v-model="assignForm.driver_id" class="form-select">
                                <option value="">No driver</option>
                                <option v-for="driver in drivers" :key="driver.id" :value="driver.id">{{ driver.first_name }} {{ driver.last_name }} · {{ driver.availability_status }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Vehicle</label>
                            <select v-model="assignForm.vehicle_id" class="form-select">
                                <option value="">No vehicle</option>
                                <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.name }} · {{ vehicle.registration_number }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Note</label>
                            <textarea v-model="assignForm.note" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary flex-fill" :disabled="assignForm.processing" @click.prevent="assign">Save assignment</button>
                            <button v-if="booking.assigned_driver" type="button" class="btn btn-outline-secondary" :disabled="assignForm.processing" @click="unassign">Clear</button>
                        </div>
                    </div>
                </div>

                <div v-if="allowedTransitions.length" class="card">
                    <div class="card-header"><strong>Update status</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small">Next status</label>
                            <select v-model="statusForm.status" class="form-select">
                                <option value="">Select</option>
                                <option v-for="option in allowedTransitions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Note</label>
                            <textarea v-model="statusForm.note" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <button class="btn btn-outline-secondary w-100" :disabled="statusForm.processing" @click.prevent="updateStatus">Update status</button>
                    </div>
                </div>
            </div>
        </div>
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 1rem; }
.summary-card { min-width: 220px; border-radius: 1rem; background: #0f172a; color: #f8fafc; padding: 1rem; }
.summary-row { display: flex; justify-content: space-between; margin-bottom: .4rem; }
.summary-row:last-child { margin-bottom: 0; }
</style>
