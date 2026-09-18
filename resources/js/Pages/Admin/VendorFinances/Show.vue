<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    vendor: Object,
    kyc: Object,
    payoutAccount: Object,
    balances: Object,
    ledger: Object,
    withdrawals: { type: Array, default: () => [] },
    adjustmentTypes: { type: Array, default: () => [] },
});

const endpoint = appUrl(`/admin/vendor-finances/${props.vendor.id}`);

const adjustForm = useForm({
    type: 'adjustment_credit',
    amount: '',
    reason: '',
    external_reference: '',
    confirm: false,
});

function submitAdjustment() {
    if (!adjustForm.confirm && !window.confirm(`Record ${adjustForm.type === 'adjustment_credit' ? 'credit' : 'debit'} of ₹${adjustForm.amount}? Ledger entries are append-only and cannot be edited.`)) return;
    adjustForm.confirm = true;
    adjustForm.post(`${endpoint}/adjustments`, {
        preserveScroll: true,
        onFinish: () => { adjustForm.confirm = false; },
    });
}

function money(value) {
    return `₹${Number(value ?? 0).toFixed(2)}`;
}
function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
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
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="appUrl('/admin/withdrawals')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Withdrawals</Link>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                <h2 class="mb-0">{{ vendor.business_name }}</h2>
                <span class="badge" :class="kyc.verified ? 'bg-success' : 'bg-warning text-dark'">KYC {{ kyc.verified ? 'verified' : 'unverified' }}</span>
            </div>
            <p class="text-muted mb-0 mt-1">Vendor finance detail · append-only ledger</p>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6 col-lg-3"><div class="card p-3 h-100"><div class="text-muted small">Recorded earnings</div><div class="fs-5 fw-bold">{{ money(balances.recorded_earnings) }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="card p-3 h-100"><div class="text-muted small">Available</div><div class="fs-5 fw-bold">{{ money(balances.available_balance) }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="card p-3 h-100"><div class="text-muted small">Held</div><div class="fs-5 fw-bold">{{ money(balances.held_amount) }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="card p-3 h-100"><div class="text-muted small">Paid out</div><div class="fs-5 fw-bold">{{ money(balances.paid_out) }}</div></div></div>
        </div>

        <div v-if="payoutAccount" class="card p-3 mb-3 small">
            <strong>Payout:</strong> {{ payoutAccount.method_label ?? payoutAccount.method }} · {{ payoutAccount.masked_destination }}
            <span class="badge ms-2" :class="payoutAccount.status === 'verified' ? 'bg-success' : 'bg-warning text-dark'">{{ payoutAccount.status }}</span>
        </div>
        <div v-else class="card p-3 mb-3 small text-muted">No payout account on file.</div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Ledger Timeline</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>Date</th><th>Type</th><th>Booking</th><th>Actor</th><th class="text-end">Credit</th><th class="text-end">Debit</th></tr></thead>
                            <tbody>
                                <tr v-for="entry in ledger.data" :key="entry.id">
                                    <td class="text-muted text-nowrap">{{ formatDateTime(entry.created_at) }}</td>
                                    <td>{{ entryLabel(entry.type) }}</td>
                                    <td>{{ entry.booking?.booking_reference_id ?? '—' }}</td>
                                    <td>{{ entry.creator?.name ?? 'System' }}</td>
                                    <td class="text-end text-nowrap">{{ entry.direction === 'credit' ? money(entry.amount) : '—' }}</td>
                                    <td class="text-end text-nowrap">{{ entry.direction === 'debit' ? money(entry.amount) : '—' }}</td>
                                </tr>
                                <tr v-if="!ledger.data.length"><td colspan="6" class="text-center text-muted py-3">No ledger entries.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <Pagination :links="ledger.links" />
                </section>

                <section v-if="withdrawals.length" class="card p-3 p-md-4">
                    <h5 class="mb-3">Recent Withdrawals</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>ID</th><th>Amount</th><th>Status</th><th>Destination</th></tr></thead>
                            <tbody>
                                <tr v-for="item in withdrawals" :key="item.id">
                                    <td><Link :href="appUrl(`/admin/withdrawals/${item.id}`)" class="text-decoration-none fw-semibold">#{{ item.id }}</Link></td>
                                    <td>{{ money(item.amount) }}</td>
                                    <td><span class="badge" :class="statusBadge(item.status)">{{ item.status }}</span></td>
                                    <td>{{ item.payout_destination_masked ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <form class="card p-3 p-md-4" @submit.prevent="submitAdjustment">
                    <h5 class="mb-1">Add Adjustment</h5>
                    <p class="text-muted small">Append-only. A debit may drive available negative for genuine corrections — withdrawals stay blocked until it recovers.</p>
                    <fieldset :disabled="adjustForm.processing">
                        <label for="adj-type" class="form-label small">Type</label>
                        <select id="adj-type" v-model="adjustForm.type" class="form-select mb-3">
                            <option v-for="t in adjustmentTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <label for="adj-amount" class="form-label small">Amount (₹)</label>
                        <input id="adj-amount" v-model="adjustForm.amount" type="number" step="0.01" min="0" class="form-control mb-3" />
                        <label for="adj-reason" class="form-label small">Reason (required)</label>
                        <input id="adj-reason" v-model="adjustForm.reason" class="form-control mb-3" maxlength="255" placeholder="e.g. Goodwill credit for incident #12" />
                        <label for="adj-ref" class="form-label small">External reference (optional)</label>
                        <input id="adj-ref" v-model="adjustForm.external_reference" class="form-control mb-2" maxlength="100" />
                        <div v-if="adjustForm.hasErrors" class="text-danger small mb-2" role="alert"><div v-for="(error, key) in adjustForm.errors" :key="key">{{ error }}</div></div>
                        <button class="btn btn-svtp w-100" :disabled="adjustForm.processing">Record Adjustment</button>
                    </fieldset>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
</style>
