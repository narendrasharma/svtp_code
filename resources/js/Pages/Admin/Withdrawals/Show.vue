<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    withdrawal: Object,
    vendor: Object,
    kyc: Object,
    balances: Object,
    statuses: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/withdrawals');

const reviewForm = useForm({
    rejection_reason: '',
    payout_reference: props.withdrawal.payout_reference ?? '',
    admin_note: props.withdrawal.admin_note ?? '',
});

function approve() {
    reviewForm.patch(`${endpoint}/${props.withdrawal.id}/approve`, { preserveScroll: true });
}
function reject() {
    if (!reviewForm.rejection_reason) {
        reviewForm.setError('rejection_reason', 'A rejection reason is required.');
        return;
    }
    reviewForm.patch(`${endpoint}/${props.withdrawal.id}/reject`, { preserveScroll: true });
}
function markPaid() {
    if (!window.confirm(`Mark withdrawal #${props.withdrawal.id} as paid? The hold converts to a settlement — this cannot be undone except by a manual adjustment.`)) return;
    reviewForm.patch(`${endpoint}/${props.withdrawal.id}/mark-paid`, { preserveScroll: true });
}
function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
function money(value) {
    return `₹${Number(value ?? 0).toFixed(2)}`;
}
function statusBadge(status) {
    return {
        pending: 'bg-warning text-dark',
        approved: 'bg-info text-dark',
        rejected: 'bg-secondary',
        paid: 'bg-success',
        cancelled: 'bg-secondary',
    }[status] ?? 'bg-secondary';
}
function entryLabel(type) {
    return {
        booking_earning: 'Booking earning',
        refund_reversal: 'Refund reversal',
        adjustment_credit: 'Adjustment credit',
        adjustment_debit: 'Adjustment debit',
        withdrawal_hold: 'Withdrawal hold',
        withdrawal_release: 'Withdrawal release',
        payout_settlement: 'Payout settlement',
    }[type] ?? type;
}
const isOpen = ['pending', 'approved'].includes(props.withdrawal.status);
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>All Withdrawals</Link>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                <h2 class="mb-0">Withdrawal #{{ withdrawal.id }}</h2>
                <span class="badge" :class="statusBadge(withdrawal.status)">{{ withdrawal.status }}</span>
            </div>
            <p class="text-muted mb-0 mt-1">Requested {{ formatDateTime(withdrawal.requested_at) }}</p>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Request</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Vendor</dt><dd class="col-sm-8">{{ vendor?.business_name ?? '—' }}<span v-if="vendor?.email" class="text-muted"> · {{ vendor.email }}</span><span v-if="vendor?.id" class="ms-2"><Link :href="appUrl(`/admin/vendor-finances/${vendor.id}`)" class="small">Finance detail</Link></span></dd>
                        <dt class="col-sm-4 text-muted">KYC</dt><dd class="col-sm-8"><span class="badge" :class="kyc.verified ? 'bg-success' : 'bg-warning text-dark'">{{ kyc.verified ? 'Verified' : `Unverified (${kyc.status ?? 'no verification'})` }}</span></dd>
                        <dt class="col-sm-4 text-muted">Amount</dt><dd class="col-sm-8 fw-bold">{{ money(withdrawal.amount) }}</dd>
                        <dt class="col-sm-4 text-muted">Payout destination</dt><dd class="col-sm-8">{{ withdrawal.payout_destination_masked ?? '— (pre-snapshot request)' }}<span v-if="withdrawal.payout_method" class="text-muted"> · {{ withdrawal.payout_method }}</span></dd>
                        <dt v-if="withdrawal.vendor_note" class="col-sm-4 text-muted">Vendor note</dt><dd v-if="withdrawal.vendor_note" class="col-sm-8">{{ withdrawal.vendor_note }}</dd>
                        <dt v-if="withdrawal.rejection_reason" class="col-sm-4 text-muted">Rejection reason</dt><dd v-if="withdrawal.rejection_reason" class="col-sm-8">{{ withdrawal.rejection_reason }}</dd>
                        <dt v-if="withdrawal.payout_reference" class="col-sm-4 text-muted">Payout reference</dt><dd v-if="withdrawal.payout_reference" class="col-sm-8">{{ withdrawal.payout_reference }}</dd>
                        <dt v-if="withdrawal.admin_note" class="col-sm-4 text-muted">Admin note</dt><dd v-if="withdrawal.admin_note" class="col-sm-8">{{ withdrawal.admin_note }}</dd>
                        <dt v-if="withdrawal.paid_at" class="col-sm-4 text-muted">Paid at</dt><dd v-if="withdrawal.paid_at" class="col-sm-8">{{ formatDateTime(withdrawal.paid_at) }}</dd>
                    </dl>
                </section>

                <section v-if="balances" class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Vendor Balances (ledger-derived)</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Recorded earnings</dt><dd class="col-sm-8">{{ money(balances.recorded_earnings) }}</dd>
                        <dt class="col-sm-4 text-muted">Held for withdrawal</dt><dd class="col-sm-8">{{ money(balances.held_amount) }}</dd>
                        <dt class="col-sm-4 text-muted">Available balance</dt><dd class="col-sm-8">{{ money(balances.available_balance) }}</dd>
                        <dt class="col-sm-4 text-muted">Paid out</dt><dd class="col-sm-8">{{ money(balances.paid_out) }}</dd>
                    </dl>
                </section>

                <section class="card p-3 p-md-4">
                    <h5 class="mb-3">Ledger Context</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>Date</th><th>Type</th><th class="text-end">Credit</th><th class="text-end">Debit</th></tr></thead>
                            <tbody>
                                <tr v-for="entry in withdrawal.ledger_entries" :key="entry.id">
                                    <td class="text-muted text-nowrap">{{ formatDateTime(entry.created_at) }}</td>
                                    <td>{{ entryLabel(entry.type) }}</td>
                                    <td class="text-end text-nowrap">{{ entry.direction === 'credit' ? money(entry.amount) : '—' }}</td>
                                    <td class="text-end text-nowrap">{{ entry.direction === 'debit' ? money(entry.amount) : '—' }}</td>
                                </tr>
                                <tr v-if="!withdrawal.ledger_entries?.length">
                                    <td colspan="4" class="text-center text-muted py-3">No ledger entries for this request.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <form class="card p-3 p-md-4" @submit.prevent>
                    <h5 class="mb-3">Review</h5>
                    <fieldset :disabled="reviewForm.processing || !isOpen">
                        <label for="rejection-reason" class="form-label small">Rejection reason (required to reject)</label>
                        <input id="rejection-reason" v-model="reviewForm.rejection_reason" class="form-control mb-3" maxlength="255" placeholder="e.g. Bank details pending" />
                        <label for="payout-reference" class="form-label small">Payout reference (for Mark Paid)</label>
                        <input id="payout-reference" v-model="reviewForm.payout_reference" class="form-control mb-3" maxlength="100" placeholder="e.g. UTR / txn id" />
                        <label for="admin-note" class="form-label small">Admin note (optional)</label>
                        <input id="admin-note" v-model="reviewForm.admin_note" class="form-control mb-2" maxlength="255" />
                        <div v-if="reviewForm.hasErrors" class="text-danger small mb-2" role="alert"><div v-for="(error, key) in reviewForm.errors" :key="key">{{ error }}</div></div>
                        <div v-if="isOpen" class="d-grid gap-2">
                            <button v-if="withdrawal.status === 'pending'" type="button" class="btn btn-svtp" :disabled="reviewForm.processing" @click="approve">Approve (keep held)</button>
                            <button type="button" class="btn btn-outline-danger" :disabled="reviewForm.processing" @click="reject">Reject (release hold)</button>
                            <button v-if="withdrawal.status === 'approved'" type="button" class="btn btn-success" :disabled="reviewForm.processing" @click="markPaid">Mark Paid</button>
                        </div>
                        <p v-else class="text-muted small mb-0">This request is {{ withdrawal.status }} — no further actions.</p>
                    </fieldset>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
</style>
