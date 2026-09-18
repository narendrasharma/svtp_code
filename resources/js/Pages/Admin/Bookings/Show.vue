<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import ShareMenu from '../../../Components/ShareMenu.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    booking: Object,
    ledgerState: { type: String, default: 'not_eligible' },
    refundSummary: { type: Object, default: () => ({}) },
    paymentSummary: { type: Object, default: () => ({ total: 0, paid: 0, refunded: 0, due: 0, currency: 'INR', payments_count: 0 }) },
    paymentMethods: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    paymentStatuses: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({ record_payment: false, reschedule: false }) },
});

const endpoint = appUrl('/admin/bookings');
const statusForm = useForm({
    booking_status: props.booking.booking_status,
    payment_status: props.booking.payment_status,
    note: '',
});
const refundForm = useForm({
    amount: '',
    reason: '',
    external_reference: '',
    reference: props.refundSummary?.refund_form_key ?? '',
});
const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    note: '',
    external_reference: '',
});
const rescheduleForm = useForm({
    new_travel_date: '',
    reason: '',
});
const noteForm = useForm({
    body: '',
    is_internal: true,
});
const dueDateForm = useForm({
    payment_due_date: props.booking.payment_due_date ? String(props.booking.payment_due_date).slice(0, 10) : '',
});

function recordRefund() {
    if (!window.confirm(`Record manual refund of ${refundForm.amount}? This is accounting only — no money moves electronically. Vendor reversal is prorated from the original snapshot.`)) return;
    refundForm.post(`${endpoint}/${props.booking.id}/refunds`, { preserveScroll: true });
}

function recordPayment() {
    if (!window.confirm(`Record payment of ${paymentForm.amount} via ${paymentForm.payment_method}? Collections are append-only and cannot be edited.`)) return;
    paymentForm.post(`${endpoint}/${props.booking.id}/payments`, {
        preserveScroll: true,
        onSuccess: () => paymentForm.reset('amount', 'note', 'external_reference'),
    });
}

function reschedule() {
    if (!rescheduleForm.new_travel_date || !window.confirm(`Move travel date to ${rescheduleForm.new_travel_date}? Availability is rechecked server-side and the old date stays in history.`)) return;
    rescheduleForm.post(`${endpoint}/${props.booking.id}/reschedule`, {
        preserveScroll: true,
        onSuccess: () => rescheduleForm.reset(),
    });
}

function addNote() {
    noteForm.post(`${endpoint}/${props.booking.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset('body'),
    });
}

function saveStatus() {
    statusForm.patch(`${endpoint}/${props.booking.id}/status`, { preserveScroll: true });
}
function saveDueDate() {
    dueDateForm.patch(`${endpoint}/${props.booking.id}/payment-due-date`, { preserveScroll: true });
}
function sendPaymentReminder() {
    if (!window.confirm('Queue a payment reminder for the customer now?')) return;
    router.post(`${endpoint}/${props.booking.id}/payment-reminder`, {}, { preserveScroll: true });
}
function reviewCancellation(cancellation, decision) {
    if (decision === 'approve' && !window.confirm(`Approve cancellation and mark booking ${props.booking.booking_reference_id} as cancelled? No refund is issued automatically.`)) return;
    router.patch(`${endpoint}/${props.booking.id}/cancellation-requests/${cancellation.id}/${decision}`, {}, { preserveScroll: true });
}
function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN') : '—';
}
function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
function money(value) {
    return `${props.booking.currency ?? ''} ${Number(value ?? 0).toFixed(2)}`.trim();
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>All Bookings</Link>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                <h2 class="mb-0">{{ booking.booking_reference_id }}</h2>
                <span class="badge bg-info text-dark">{{ booking.booking_status }}</span>
                <span class="badge bg-secondary">{{ booking.payment_status }}</span>
            </div>
            <p class="text-muted mb-0 mt-1">Booked {{ formatDateTime(booking.created_at) }} via {{ booking.source }}</p>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Tour Details</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Tour package</dt><dd class="col-sm-8">{{ booking.package?.title ?? '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Travel date</dt><dd class="col-sm-8">{{ formatDate(booking.travel_date) }}</dd>
                        <dt class="col-sm-4 text-muted">Guests</dt><dd class="col-sm-8">{{ booking.total_adults }} adult(s)<span v-if="booking.total_children">, {{ booking.total_children }} child(ren)</span></dd>
                        <dt class="col-sm-4 text-muted">Pickup address</dt><dd class="col-sm-8">{{ booking.pickup_address || '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Special requests</dt><dd class="col-sm-8">{{ booking.special_requests || '—' }}</dd>
                        <dt v-if="booking.quotation" class="col-sm-4 text-muted">Quotation</dt><dd v-if="booking.quotation" class="col-sm-8">{{ booking.quotation.reference }} (Rev {{ booking.quotation_revision_number }}) · quoted {{ money(booking.quoted_total_amount) }}</dd>
                    </dl>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Customer</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Name</dt><dd class="col-sm-8">{{ booking.customer_name || booking.user?.name || 'Guest' }}</dd>
                        <dt class="col-sm-4 text-muted">Phone</dt><dd class="col-sm-8">{{ booking.customer_phone || '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Email</dt><dd class="col-sm-8">{{ booking.customer_email || booking.user?.email || '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Country</dt><dd class="col-sm-8">{{ booking.country || '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Account</dt><dd class="col-sm-8">{{ booking.user ? booking.user.name : 'Guest booking (no account)' }}</dd>
                    </dl>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Pricing Snapshot</h5>
                    <p class="text-muted small">Historical booked price — changing the tour price later never alters this record.</p>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Base price (per adult)</dt><dd class="col-sm-8">{{ money(booking.base_price) }}</dd>
                        <dt v-if="Number(booking.addons_total)" class="col-sm-4 text-muted">Extras total</dt><dd v-if="Number(booking.addons_total)" class="col-sm-8">{{ money(booking.addons_total) }}</dd>
                        <dt class="col-sm-4 text-muted">Subtotal</dt><dd class="col-sm-8">{{ money(booking.subtotal) }}</dd>
                        <dt v-if="Number(booking.discount_amount)" class="col-sm-4 text-muted">Discount{{ booking.coupon_code ? ` (${booking.coupon_code})` : '' }}</dt><dd v-if="Number(booking.discount_amount)" class="col-sm-8">−{{ money(booking.discount_amount) }}</dd>
                        <dt v-if="Number(booking.tax_amount)" class="col-sm-4 text-muted">Tax</dt><dd v-if="Number(booking.tax_amount)" class="col-sm-8">{{ money(booking.tax_amount) }}</dd>
                        <dt class="col-sm-4 text-muted">Total charged</dt><dd class="col-sm-8 fw-bold">{{ money(booking.total_amount) }}</dd>
                        <dt class="col-sm-4 text-muted">Gateway</dt><dd class="col-sm-8">{{ booking.payment_gateway || '—' }}</dd>
                        <dt v-if="booking.payment_reference" class="col-sm-4 text-muted">Payment reference</dt><dd v-if="booking.payment_reference" class="col-sm-8">{{ booking.payment_reference }}</dd>
                        <dt v-if="booking.coupon_code" class="col-sm-4 text-muted">Promo code</dt><dd v-if="booking.coupon_code" class="col-sm-8">{{ booking.coupon_code }}</dd>
                    </dl>
                    <div v-if="booking.booking_addons?.length" class="table-responsive mt-3">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>Extra</th><th class="text-end">Qty</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                <tr v-for="line in booking.booking_addons" :key="line.id"><td>{{ line.name }}</td><td class="text-end">{{ line.quantity }}</td><td class="text-end">{{ money(line.total_amount) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <ShareMenu
                        :endpoint="`/admin/bookings/${booking.id}/share-invoice`"
                        :default-email="booking.customer_email || booking.user?.email || ''"
                        :default-phone="booking.customer_phone || booking.user?.phone || ''"
                        label="Share invoice"
                    />
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Marketplace Split</h5>
                    <p class="text-muted small">Historical vendor assignment and commission snapshot — later ownership or rate changes never alter this record.</p>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Vendor</dt><dd class="col-sm-8">{{ booking.vendor_profile?.business_name ?? 'Admin-owned (platform retains full value)' }}</dd>
                        <template v-if="booking.vendor_profile">
                            <dt class="col-sm-4 text-muted">Booking gross</dt><dd class="col-sm-8">{{ money(booking.gross_amount ?? booking.total_amount) }}</dd>
                            <dt class="col-sm-4 text-muted">Platform commission</dt><dd class="col-sm-8">{{ booking.platform_commission_percentage ?? '—' }}% · {{ money(booking.platform_commission_amount) }}</dd>
                            <dt class="col-sm-4 text-muted">Vendor earning</dt><dd class="col-sm-8 fw-bold">{{ money(booking.vendor_earning_amount) }}</dd>
                            <dt class="col-sm-4 text-muted">Ledger</dt><dd class="col-sm-8">{{ { credited: 'Credited', reversed: 'Reversed', pending_payment: 'Not yet eligible (unpaid)', not_eligible: 'Not eligible' }[ledgerState] ?? ledgerState }}</dd>
                        </template>
                    </dl>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Payments &amp; Outstanding</h5>
                    <p class="text-muted small">Append-only collections — recorded payments are never edited. Outstanding is always derived (total − paid + refunded).</p>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Total</dt><dd class="col-sm-8 fw-bold">{{ money(paymentSummary.total) }}</dd>
                        <dt class="col-sm-4 text-muted">Paid</dt><dd class="col-sm-8">{{ money(paymentSummary.paid) }}</dd>
                        <dt v-if="Number(paymentSummary.refunded)" class="col-sm-4 text-muted">Refunded</dt><dd v-if="Number(paymentSummary.refunded)" class="col-sm-8">{{ money(paymentSummary.refunded) }}</dd>
                        <dt class="col-sm-4 text-muted">Outstanding due</dt><dd class="col-sm-8 fw-bold" :class="Number(paymentSummary.due) > 0 ? 'text-warning' : ''">{{ money(paymentSummary.due) }}</dd>
                    </dl>
                    <div v-if="booking.payments?.length" class="table-responsive mt-3">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>Receipt</th><th class="text-end">Amount</th><th>Method</th><th>Received</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="payment in booking.payments" :key="payment.id">
                                    <td class="text-nowrap">{{ payment.reference }}</td>
                                    <td class="text-end text-nowrap">{{ money(payment.amount) }}</td>
                                    <td>{{ payment.payment_method }}</td>
                                    <td class="text-muted text-nowrap">{{ formatDateTime(payment.paid_at) }} · {{ payment.receiver?.name ?? payment.creator?.name ?? '—' }}</td>
                                    <td class="text-end"><a :href="appUrl(`/admin/bookings/${booking.id}/payments/${payment.id}/receipt`)" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Receipt</a></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <form v-if="permissions.record_payment && Number(paymentSummary.due) > 0" @submit.prevent="recordPayment" class="row g-2 align-items-end mt-3">
                        <div class="col-md-3">
                            <label for="payment-amount" class="form-label small">Amount (due {{ money(paymentSummary.due) }})</label>
                            <input id="payment-amount" v-model="paymentForm.amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" :max="paymentSummary.due" required />
                        </div>
                        <div class="col-md-3">
                            <label for="payment-method" class="form-label small">Method</label>
                            <select id="payment-method" v-model="paymentForm.payment_method" class="form-select form-select-sm">
                                <option v-for="method in paymentMethods" :key="method.value" :value="method.value">{{ method.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="payment-note" class="form-label small">Note (optional)</label>
                            <input id="payment-note" v-model="paymentForm.note" class="form-control form-control-sm" maxlength="255" />
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-svtp w-100" :disabled="paymentForm.processing">Collect</button>
                        </div>
                    </form>
                    <div v-if="paymentForm.hasErrors" class="text-danger small mt-2" role="alert"><div v-for="(error, key) in paymentForm.errors" :key="key">{{ error }}</div></div>
                    <form v-if="permissions.record_payment" @submit.prevent="saveDueDate" class="row g-2 align-items-end mt-3 border-top pt-3">
                        <div class="col-md-4">
                            <label for="due-date" class="form-label small">Payment due date (optional — drives auto-reminders)</label>
                            <input id="due-date" v-model="dueDateForm.payment_due_date" type="date" class="form-control form-control-sm" />
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-sm btn-outline-secondary w-100" :disabled="dueDateForm.processing">Save due date</button>
                        </div>
                        <div v-if="Number(paymentSummary.due) > 0" class="col-md-5">
                            <button type="button" class="btn btn-sm btn-outline-primary w-100" @click="sendPaymentReminder">Send manual reminder now</button>
                        </div>
                    </form>
                    <p v-if="booking.payment_due_date" class="small text-muted mt-2 mb-0">Due {{ formatDate(booking.payment_due_date) }} · auto-reminders at configured offsets.</p>
                </section>

                <section v-if="permissions.reschedule" class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Reschedule</h5>
                    <p class="text-muted small">Date changes recheck availability server-side. The old date, both totals and the reason stay in history. Totals move with the change; refunds/payments stay explicit actions.</p>
                    <div v-if="booking.reschedules?.length" class="table-responsive mb-3">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>From</th><th>To</th><th class="text-end">Difference</th><th>Reason</th><th>By</th></tr></thead>
                            <tbody>
                                <tr v-for="change in booking.reschedules" :key="change.id">
                                    <td class="text-nowrap">{{ formatDate(change.old_travel_date) }}</td>
                                    <td class="text-nowrap">{{ formatDate(change.new_travel_date) }}</td>
                                    <td class="text-end text-nowrap">{{ Number(change.price_difference) > 0 ? '+' : '' }}{{ money(change.price_difference) }}</td>
                                    <td>{{ change.reason }}</td>
                                    <td class="text-muted">{{ change.requester?.name ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <form @submit.prevent="reschedule" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label for="new-travel-date" class="form-label small">New travel date</label>
                            <input id="new-travel-date" v-model="rescheduleForm.new_travel_date" type="date" class="form-control form-control-sm" required />
                        </div>
                        <div class="col-md-5">
                            <label for="reschedule-reason" class="form-label small">Reason</label>
                            <input id="reschedule-reason" v-model="rescheduleForm.reason" class="form-control form-control-sm" maxlength="2000" required placeholder="e.g. Customer requested date shift" />
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-sm btn-outline-primary w-100" :disabled="rescheduleForm.processing">Reschedule</button>
                        </div>
                    </form>
                    <div v-if="rescheduleForm.hasErrors" class="text-danger small mt-2" role="alert"><div v-for="(error, key) in rescheduleForm.errors" :key="key">{{ error }}</div></div>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Operational Notes</h5>
                    <div v-if="booking.notes?.length" class="mb-3">
                        <div v-for="note in booking.notes" :key="note.id" class="border rounded p-2 mb-2 small">
                            <p class="mb-1">{{ note.body }}</p>
                            <p class="text-muted mb-0">{{ note.author?.name ?? 'Staff' }} · {{ formatDateTime(note.created_at) }}{{ note.is_internal ? ' · internal' : '' }}</p>
                        </div>
                    </div>
                    <p v-else class="text-muted small">No notes yet.</p>
                    <form @submit.prevent="addNote" class="row g-2 align-items-end">
                        <div class="col-md-9">
                            <label for="note-body" class="form-label small">Add note</label>
                            <input id="note-body" v-model="noteForm.body" class="form-control form-control-sm" maxlength="2000" required />
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-sm btn-outline-secondary w-100" :disabled="noteForm.processing">Add Note</button>
                        </div>
                    </form>
                    <div v-if="noteForm.hasErrors" class="text-danger small mt-2" role="alert"><div v-for="(error, key) in noteForm.errors" :key="key">{{ error }}</div></div>
                </section>

                <section v-if="booking.vendor_profile" class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Refunds (manual accounting)</h5>
                    <p class="text-muted small">Recording is accounting only — no money moves electronically. Vendor reversal is prorated from the original snapshot ratio, never the current commission setting.</p>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Gross paid</dt><dd class="col-sm-8">{{ money(booking.gross_amount ?? booking.total_amount) }}</dd>
                        <dt class="col-sm-4 text-muted">Refunded total</dt><dd class="col-sm-8">{{ money(refundSummary.refunded_total) }}</dd>
                        <dt class="col-sm-4 text-muted">Refundable remaining</dt><dd class="col-sm-8 fw-bold">{{ money(refundSummary.refundable_remaining) }}</dd>
                        <dt class="col-sm-4 text-muted">Vendor earning reversed</dt><dd class="col-sm-8">{{ money(refundSummary.vendor_reversed_total) }}</dd>
                        <dt class="col-sm-4 text-muted">Vendor earning remaining</dt><dd class="col-sm-8">{{ money(refundSummary.vendor_earning_remaining) }}</dd>
                    </dl>
                    <div v-if="booking.refunds?.length" class="table-responsive mt-3">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>Date</th><th class="text-end">Amount</th><th class="text-end">Vendor reversal</th><th>Reason</th><th>Reference</th></tr></thead>
                            <tbody>
                                <tr v-for="refund in booking.refunds" :key="refund.id">
                                    <td class="text-muted text-nowrap">{{ formatDateTime(refund.processed_at) }}</td>
                                    <td class="text-end text-nowrap">{{ money(refund.amount) }}</td>
                                    <td class="text-end text-nowrap">{{ money(refund.vendor_reversal_amount) }}</td>
                                    <td>{{ refund.reason }}</td>
                                    <td class="text-muted">{{ refund.external_reference ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <form v-if="Number(refundSummary.refundable_remaining) > 0 && booking.payment_status === 'paid'" @submit.prevent="recordRefund" class="row g-2 align-items-end mt-3">
                        <div class="col-md-3">
                            <label for="refund-amount" class="form-label small">Amount</label>
                            <input id="refund-amount" v-model="refundForm.amount" type="number" step="0.01" min="0" class="form-control form-control-sm" :max="refundSummary.refundable_remaining" />
                        </div>
                        <div class="col-md-4">
                            <label for="refund-reason" class="form-label small">Reason</label>
                            <input id="refund-reason" v-model="refundForm.reason" class="form-control form-control-sm" maxlength="255" />
                        </div>
                        <div class="col-md-3">
                            <label for="refund-extref" class="form-label small">External ref (optional)</label>
                            <input id="refund-extref" v-model="refundForm.external_reference" class="form-control form-control-sm" maxlength="100" />
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-sm btn-outline-danger w-100" :disabled="refundForm.processing">Record Refund</button>
                        </div>
                    </form>
                    <div v-if="refundForm.hasErrors" class="text-danger small mt-2" role="alert"><div v-for="(error, key) in refundForm.errors" :key="key">{{ error }}</div></div>
                </section>

                <section v-if="booking.cancellation_requests?.length" class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Cancellation Requests</h5>
                    <div v-for="request in booking.cancellation_requests" :key="request.id" class="border rounded p-3 mb-2">
                        <div class="d-flex flex-wrap align-items-center gap-2 small">
                            <span class="badge" :class="request.status === 'pending' ? 'bg-warning text-dark' : request.status === 'approved' ? 'bg-success' : 'bg-secondary'">{{ request.status }}</span>
                            <span>by {{ request.requester?.name ?? 'Customer' }}</span>
                            <span class="text-muted">{{ formatDateTime(request.created_at) }}</span>
                        </div>
                        <p v-if="request.reason" class="small mt-2 mb-1"><span class="text-muted">Reason:</span> {{ request.reason }}</p>
                        <p v-if="request.review_note" class="small mt-1 mb-1"><span class="text-muted">Review note:</span> {{ request.review_note }}</p>
                        <p v-if="request.reviewer" class="small text-muted mb-2">Reviewed by {{ request.reviewer.name }}</p>
                        <div v-if="request.status === 'pending'" class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-success" @click="reviewCancellation(request, 'approve')">Approve &amp; Cancel Booking</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="reviewCancellation(request, 'reject')">Reject</button>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Approving moves the booking to cancelled through the normal status flow. No refund is issued automatically.</p>
                </section>

                <section class="card p-3 p-md-4">
                    <h5 class="mb-3">Operational Timeline</h5>
                    <ul v-if="booking.status_histories?.length" class="list-unstyled mb-0 small">
                        <li v-for="entry in booking.status_histories" :key="entry.id" class="border-bottom py-2">
                            <div>
                                <template v-if="entry.payment_from && entry.payment_from !== entry.payment_to">
                                    Payment <strong>{{ entry.payment_from }} → {{ entry.payment_to }}</strong>
                                </template>
                                <template v-else-if="entry.from_status">
                                    Status <strong>{{ entry.from_status }} → {{ entry.to_status }}</strong>
                                </template>
                                <template v-else>
                                    Booking <strong>{{ entry.to_status }}</strong>
                                </template>
                            </div>
                            <div class="text-muted">{{ entry.note || '—' }} · {{ entry.changer?.name || 'System' }} · {{ formatDateTime(entry.created_at) }}</div>
                        </li>
                    </ul>
                    <p v-else class="text-muted small mb-0">No history yet.</p>
                </section>
            </div>

            <div class="col-lg-4">
                <form class="card p-3 p-md-4" @submit.prevent="saveStatus">
                    <h5 class="mb-3">Update Status</h5>
                    <fieldset :disabled="statusForm.processing">
                        <label for="booking-status" class="form-label small">Booking status</label>
                        <select id="booking-status" v-model="statusForm.booking_status" class="form-select mb-3">
                            <option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <label for="payment-status" class="form-label small">Payment status</label>
                        <select id="payment-status" v-model="statusForm.payment_status" class="form-select mb-3">
                            <option v-for="option in paymentStatuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <label for="status-note" class="form-label small">Note (optional)</label>
                        <input id="status-note" v-model="statusForm.note" class="form-control mb-2" maxlength="255" placeholder="e.g. Confirmed on call" />
                        <div v-if="statusForm.hasErrors" class="text-danger small mb-2" role="alert"><div v-for="(error, key) in statusForm.errors" :key="key">{{ error }}</div></div>
                        <button class="btn btn-svtp w-100" :disabled="statusForm.processing || !statusForm.isDirty">Save Status</button>
                    </fieldset>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
</style>
