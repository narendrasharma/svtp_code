<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    booking: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    fleet: { type: Object, default: () => ({ drivers: [], vehicles: [] }) },
    statuses: { type: Array, default: () => [] },
    allowedTransitions: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    canAssign: { type: Boolean, default: false },
    canStatus: { type: Boolean, default: false },
    autoDispatch: { type: Object, default: () => ({ status: 'idle', attempts: 0, max_attempts: 3, pending: null, history: [] }) },
    trackingLink: { type: Object, default: () => ({ active: false, expires_at: null, last_accessed_at: null }) },
});

const booking = computed(() => props.booking);
const summary = computed(() => props.summary ?? { total: 0, paid: 0, due: 0, currency: 'INR' });
const drivers = computed(() => props.fleet?.drivers ?? []);
const vehicles = computed(() => props.fleet?.vehicles ?? []);
const statusHistory = computed(() => props.booking?.status_histories ?? []);
const assignments = computed(() => props.booking?.assignments ?? []);
const payments = computed(() => props.booking?.payments ?? []);
const autoDispatch = computed(() => props.autoDispatch ?? { status: 'idle', attempts: 0, max_attempts: 3, pending: null, history: [] });
const trackingLink = computed(() => props.trackingLink ?? { active: false, expires_at: null, last_accessed_at: null });

const assignForm = useForm({
    driver_id: '',
    vehicle_id: '',
    note: '',
});

const statusForm = useForm({
    status: '',
    note: '',
});

const paymentForm = useForm({
    amount: '',
    payment_method: props.paymentMethods?.[0]?.value ?? 'cash',
    note: '',
    external_reference: '',
});

const unassignForm = useForm({ note: '' });

const routeBase = computed(() => appUrl(`/admin/taxi/bookings/${booking.value.id}`));

function currentStatus() {
    return booking.value.status ?? 'draft';
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
        quoted: 'bg-light text-dark',
        draft: 'bg-light text-dark',
    };
    return map[status] ?? 'bg-secondary';
}

function assign() {
    assignForm.post(`${routeBase.value}/assign`, {
        preserveScroll: true,
        onSuccess: () => assignForm.reset('note'),
    });
}

function unassign() {
    if (!window.confirm('Remove the current driver and vehicle assignment?')) return;
    unassignForm.post(`${routeBase.value}/unassign`, {
        preserveScroll: true,
        onSuccess: () => unassignForm.reset('note'),
    });
}

function startAutoDispatch() {
    router.post(`${routeBase.value}/auto-dispatch/start`, {}, { preserveScroll: true });
}

function stopAutoDispatch() {
    router.post(`${routeBase.value}/auto-dispatch/stop`, {}, { preserveScroll: true });
}

function generateTrackingLink() {
    router.post(`${routeBase.value}/tracking`, {}, { preserveScroll: true });
}

function revokeTrackingLink() {
    router.delete(`${routeBase.value}/tracking`, { preserveScroll: true });
}

function updateStatus() {
    statusForm.patch(`${routeBase.value}/status`, {
        preserveScroll: true,
        onSuccess: () => statusForm.reset('note'),
    });
}

function recordPayment() {
    paymentForm.post(`${routeBase.value}/payments`, {
        preserveScroll: true,
        onSuccess: () => paymentForm.reset('amount', 'note', 'external_reference'),
    });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}

function scrollToTop() {
    router.reload({ only: ['booking', 'summary', 'fleet'] });
}
</script>

<template>
    <AdminLayout>
        <div class="mb-3"><Link :href="appUrl(`/admin/taxi/changes/${booking.id}`)" class="btn btn-outline-secondary">Cancellation, refund and reschedule</Link></div>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">{{ booking.reference }}</h2>
                <p class="text-muted mb-0">Taxi booking details and status timeline.</p>
            </div>
            <div class="d-flex gap-2">
                <Link :href="appUrl('/admin/taxi/bookings')" class="btn btn-admin-outline"><i class="bi bi-arrow-left me-2"></i>Back to list</Link>
                <button type="button" class="btn btn-admin-outline" @click="scrollToTop"><i class="bi bi-arrow-repeat me-2"></i>Refresh data</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xxl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge" :class="badge(currentStatus())">{{ currentStatus() }}</span>
                                <div class="mt-2">
                                    <strong>Pickup:</strong> {{ formatDate(booking.pickup_at) }}<br>
                                    <span class="text-muted">{{ booking.pickup_address }}</span>
                                </div>
                                <div class="mt-2">
                                    <strong>Drop:</strong><br>
                                    <span class="text-muted">{{ booking.drop_address }}</span>
                                </div>
                            </div>
                            <div class="summary-card">
                                <div class="summary-row">
                                    <span>Total</span>
                                    <strong>{{ summary.currency }} {{ Number(summary.total ?? 0).toFixed(2) }}</strong>
                                </div>
                                <div class="summary-row">
                                    <span>Paid</span>
                                    <strong>{{ summary.currency }} {{ Number(summary.paid ?? 0).toFixed(2) }}</strong>
                                </div>
                                <div class="summary-row">
                                    <span>Due</span>
                                    <strong>{{ summary.currency }} {{ Number(summary.due ?? 0).toFixed(2) }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="text-uppercase small text-muted">Customer</h6>
                                    <div class="fw-semibold">{{ booking.customer_name || 'Guest' }}</div>
                                    <div class="small text-muted">{{ booking.customer_phone }}</div>
                                    <div class="small text-muted">{{ booking.customer_email }}</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="text-uppercase small text-muted">Assigned driver</h6>
                                    <div v-if="booking.assigned_driver">
                                        <div class="fw-semibold">{{ booking.assigned_driver.first_name }} {{ booking.assigned_driver.last_name }}</div>
                                        <div class="small text-muted">{{ booking.assigned_driver.phone }}</div>
                                    </div>
                                    <div v-else class="text-muted">No driver assigned.</div>
                                    <div class="mt-3">
                                        <h6 class="text-uppercase small text-muted">Vehicle</h6>
                                        <div v-if="booking.assigned_vehicle">
                                            <div class="fw-semibold">{{ booking.assigned_vehicle.name }}</div>
                                            <div class="small text-muted">{{ booking.assigned_vehicle.registration_number }}</div>
                                        </div>
                                        <div v-else class="text-muted">No vehicle assigned.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="booking.pricing_snapshot?.breakdown" class="border rounded p-3 mt-3">
                            <h6 class="text-uppercase small text-muted">Historical pricing snapshot</h6>
                            <div v-for="(amount, key) in booking.pricing_snapshot.breakdown" :key="key" class="d-flex justify-content-between small"><span class="text-capitalize">{{ String(key).replaceAll('_', ' ') }}</span><strong>{{ booking.currency }} {{ amount }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Status history</strong>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div v-for="history in statusHistory" :key="history.id" class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="badge me-2" :class="badge(history.to_status)">{{ history.to_status }}</span>
                                        <span class="small text-muted">{{ formatDate(history.created_at) }}</span>
                                    </div>
                                    <div class="small text-muted">{{ history.changer?.name ?? 'System' }}</div>
                                </div>
                                <div v-if="history.note" class="small mt-1">{{ history.note }}</div>
                            </div>
                            <div v-if="!statusHistory.length" class="p-4 text-center text-muted">No status transitions captured.</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Payments</strong></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Reference</th><th>Amount</th><th>Method</th><th>Recorded at</th><th>Receiver</th></tr></thead>
                                <tbody>
                                    <tr v-for="payment in payments" :key="payment.id">
                                        <td>{{ payment.reference }}</td>
                                        <td>{{ payment.currency }} {{ Number(payment.amount).toFixed(2) }}</td>
                                        <td class="text-capitalize">{{ payment.payment_method }}</td>
                                        <td>{{ formatDate(payment.created_at) }}</td>
                                        <td>{{ payment.receiver?.name ?? '—' }}</td>
                                    </tr>
                                    <tr v-if="!payments.length"><td colspan="5" class="text-center text-muted py-3">No payments recorded yet.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xxl-4">
                <div v-if="canAssign" class="card mb-3">
                    <div class="card-header"><strong>Assign driver & vehicle</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small">Driver</label>
                            <select v-model="assignForm.driver_id" class="form-select">
                                <option value="">Select driver</option>
                                <option v-for="driver in drivers" :key="driver.id" :value="driver.id">{{ driver.first_name }} {{ driver.last_name }} · {{ driver.availability_status }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Vehicle</label>
                            <select v-model="assignForm.vehicle_id" class="form-select">
                                <option value="">Select vehicle</option>
                                <option v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">{{ vehicle.name }} ({{ vehicle.registration_number }})</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Note (optional)</label>
                            <textarea v-model="assignForm.note" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-svtp flex-fill" :disabled="assignForm.processing" @click.prevent="assign">Assign</button>
                            <button v-if="booking.assigned_driver" type="button" class="btn btn-admin-outline" :disabled="unassignForm.processing" @click="unassign">Unassign</button>
                        </div>
                    </div>
                </div>

                <div v-if="canStatus && allowedTransitions.length" class="card mb-3">
                    <div class="card-header"><strong>Update status</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small">Next status</label>
                            <select v-model="statusForm.status" class="form-select">
                                <option value="">Select status</option>
                                <option v-for="option in allowedTransitions" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Note</label>
                            <textarea v-model="statusForm.note" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <button class="btn btn-admin-outline w-100" :disabled="statusForm.processing" @click.prevent="updateStatus">Update status</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Record payment</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small">Amount</label>
                            <input v-model="paymentForm.amount" type="number" step="0.01" min="0" class="form-control" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Method</label>
                            <select v-model="paymentForm.payment_method" class="form-select">
                                <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Note</label>
                            <textarea v-model="paymentForm.note" class="form-control" rows="2" maxlength="255"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">External reference</label>
                            <input v-model="paymentForm.external_reference" type="text" class="form-control" maxlength="100" />
                        </div>
                        <button class="btn btn-admin-outline w-100" :disabled="paymentForm.processing" @click.prevent="recordPayment">Save payment</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="assignments.length" class="card mt-3">
            <div class="card-header"><strong>Assignment history</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Assigned at</th><th>Driver</th><th>Vehicle</th><th>Assigned by</th><th>Note</th><th>Unassigned at</th></tr></thead>
                        <tbody>
                            <tr v-for="assignment in assignments" :key="assignment.id">
                                <td>{{ formatDate(assignment.assigned_at) }}</td>
                                <td>{{ assignment.driver?.first_name }} {{ assignment.driver?.last_name }}</td>
                                <td>{{ assignment.vehicle?.name }} ({{ assignment.vehicle?.registration_number }})</td>
                                <td>{{ assignment.assigner?.name ?? 'System' }}</td>
                                <td>{{ assignment.note ?? '—' }}</td>
                                <td>{{ formatDate(assignment.unassigned_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Auto-dispatch</strong>
                <span class="badge bg-secondary text-uppercase">{{ autoDispatch.status }}</span>
            </div>
            <div class="card-body small">
                <div v-if="autoDispatch.pending" class="mb-2">
                    Offered to <strong>{{ autoDispatch.pending.driver_name }}</strong>
                    ({{ autoDispatch.pending.vehicle_name }}, rank #{{ autoDispatch.pending.rank }})
                    · expires {{ formatDate(autoDispatch.pending.expires_at) }}
                </div>
                <div v-else-if="autoDispatch.status === 'exhausted'" class="text-muted mb-2">
                    Candidate attempts exhausted — assign manually.
                </div>
                <div v-else class="text-muted mb-2">No active offer. Attempts: {{ autoDispatch.attempts }} / {{ autoDispatch.max_attempts }}.</div>
                <div class="d-flex gap-2">
                    <button v-if="canAssign && booking.status === 'confirmed'" class="btn btn-sm btn-svtp" @click="startAutoDispatch">Start auto-dispatch</button>
                    <button v-if="canAssign && autoDispatch.pending" class="btn btn-sm btn-admin-outline" @click="stopAutoDispatch">Stop</button>
                </div>
                <div v-if="(autoDispatch.history || []).length" class="mt-3">
                    <div class="text-uppercase text-muted mb-1" style="font-size:.72rem">Offer history</div>
                    <ul class="mb-0 ps-3">
                        <li v-for="offer in autoDispatch.history" :key="offer.id">
                            {{ offer.driver?.first_name }} {{ offer.driver?.last_name }} · {{ offer.status }} · rank #{{ offer.rank }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Customer tracking link</strong>
                <span v-if="trackingLink.active" class="badge bg-success">Active</span>
                <span v-else class="badge bg-secondary">None</span>
            </div>
            <div class="card-body small">
                <div v-if="$page.props.flash?.tracking_url" class="alert alert-success">
                    Copy now — the link cannot be recovered later, only regenerated.
                    <div class="input-group mt-2">
                        <input :value="$page.props.flash.tracking_url" readonly class="form-control form-control-sm" />
                    </div>
                </div>
                <div v-if="trackingLink.active" class="text-muted mb-2">
                    <span v-if="trackingLink.expires_at">Expires {{ formatDate(trackingLink.expires_at) }} · </span>
                    <span>Last opened {{ trackingLink.last_accessed_at ? formatDate(trackingLink.last_accessed_at) : 'never' }}</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-svtp" @click="generateTrackingLink">{{ trackingLink.active ? 'Regenerate link' : 'Generate link' }}</button>
                    <button v-if="trackingLink.active" class="btn btn-sm btn-admin-outline" @click="revokeTrackingLink">Revoke</button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); background: #101827; color: #e2e8f0; }
.card-header { border-bottom-color: rgba(148, 163, 184, .12); }
.summary-card { min-width: 220px; border-radius: 1rem; background: rgba(15, 23, 42, .6); border: 1px solid rgba(148, 163, 184, .15); padding: 1rem; }
.summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: .4rem; font-size: .95rem; }
.summary-row:last-child { margin-bottom: 0; }
.list-group-item { background: transparent; color: inherit; border-color: rgba(148, 163, 184, .1); }
.table { --bs-table-bg: transparent; color: inherit; }
.form-control, .form-select, textarea { background: rgba(15, 23, 42, .65); border-color: rgba(148, 163, 184, .25); color: #f8fafc; }
.form-control:focus, .form-select:focus, textarea:focus { border-color: #f59e0b; box-shadow: 0 0 0 .2rem rgba(245, 158, 11, .18); }
</style>
