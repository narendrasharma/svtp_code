<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import { appUrl } from '../../../appUrl';
import Pagination from '../../../Components/Pagination.vue';

const props = defineProps({
    balances: { type: Object, required: true },
    ledger: { type: Object, required: true },
    withdrawals: { type: Object, required: true },
    kyc: { type: Object, required: true },
    minimumWithdrawal: { type: String, required: true },
    eligibility: { type: Object, required: true },
    payoutAccount: { type: Object, default: null },
    payoutMethods: { type: Array, default: () => [] },
});

const endpoint = appUrl('/vendor/finance');
const withdrawalsEndpoint = appUrl('/vendor/withdrawals');
const payoutEndpoint = appUrl('/vendor/payout-account');

const withdrawForm = useForm({
    amount: '',
    vendor_note: '',
});

const payoutForm = useForm({
    method: props.payoutAccount?.method ?? 'bank',
    account_holder_name: props.payoutAccount?.account_holder_name ?? '',
    bank_name: props.payoutAccount?.bank_name ?? '',
    account_number: '',
    ifsc: '',
    upi_id: '',
});

function submitWithdrawal() {
    withdrawForm.post(withdrawalsEndpoint, { preserveScroll: true });
}

function submitPayout() {
    payoutForm.post(payoutEndpoint, { preserveScroll: true });
}

function cancelWithdrawal(withdrawal) {
    if (!window.confirm(`Cancel withdrawal request #${withdrawal.id}? Held funds will be released.`)) return;
    router.patch(`${withdrawalsEndpoint}/${withdrawal.id}/cancel`, {}, { preserveScroll: true });
}

function money(value) {
    return `₹${Number(value ?? 0).toFixed(2)}`;
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}

function entryBadge(type) {
    return {
        booking_earning: 'bg-success',
        refund_reversal: 'bg-danger',
        adjustment_credit: 'bg-info text-dark',
        adjustment_debit: 'bg-warning text-dark',
        withdrawal_hold: 'bg-warning text-dark',
        withdrawal_release: 'bg-secondary',
        payout_settlement: 'bg-primary',
    }[type] ?? 'bg-secondary';
}

function withdrawalBadge(status) {
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
    <VendorLayout>
        <div class="mb-3">
            <h2 class="mb-1">Earnings &amp; Payouts</h2>
            <p class="text-muted small mb-0">Ledger-derived balances. Earnings credit only paid bookings; refunds reverse via the ledger — snapshots never change.</p>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100"><div class="text-muted small">Recorded earnings</div><div class="fs-4 fw-bold">{{ money(balances.recorded_earnings) }}</div><div class="text-muted small">Earnings minus reversals</div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100"><div class="text-muted small">Available balance</div><div class="fs-4 fw-bold text-success">{{ money(balances.available_balance) }}</div><div class="text-muted small">Requestable now</div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100"><div class="text-muted small">Held for withdrawal</div><div class="fs-4 fw-bold">{{ money(balances.held_amount) }}</div><div class="text-muted small">Reserved by open requests</div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card p-3 h-100"><div class="text-muted small">Paid out</div><div class="fs-4 fw-bold">{{ money(balances.paid_out) }}</div><div class="text-muted small">Settled to you</div></div>
            </div>
        </div>

        <div v-if="!kyc.verified" class="alert alert-warning small" role="alert">
            <i class="bi bi-shield-exclamation me-1"></i>{{ kyc.message ?? 'Complete KYC verification to request payouts.' }}
            <Link :href="appUrl('/vendor/application')" class="alert-link ms-1">Review my application</Link>
        </div>

        <section class="card p-3 p-md-4 mb-3">
            <h5 class="mb-1">Request Withdrawal</h5>
            <p class="text-muted small">Minimum {{ money(minimumWithdrawal) }} · up to your available balance. Funds are held immediately and released if the request is rejected or cancelled. Payouts are settled manually by the platform team.</p>
            <form @submit.prevent="submitWithdrawal" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="withdraw-amount" class="form-label small">Amount (₹)</label>
                    <input id="withdraw-amount" v-model="withdrawForm.amount" type="number" step="0.01" min="0" class="form-control" placeholder="e.g. 5000.00" :disabled="withdrawForm.processing || !eligibility.allowed" />
                </div>
                <div class="col-md-5">
                    <label for="withdraw-note" class="form-label small">Note (optional)</label>
                    <input id="withdraw-note" v-model="withdrawForm.vendor_note" maxlength="255" class="form-control" placeholder="Anything the team should know" :disabled="withdrawForm.processing || !eligibility.allowed" />
                </div>
                <div class="col-md-3">
                    <button class="btn btn-svtp w-100" :disabled="withdrawForm.processing || !eligibility.allowed">Request Payout</button>
                </div>
            </form>
            <div v-if="withdrawForm.hasErrors" class="text-danger small mt-2" role="alert">
                <div v-for="(error, key) in withdrawForm.errors" :key="key">{{ error }}</div>
            </div>
            <p v-if="!eligibility.allowed" class="text-muted small mb-0 mt-2">{{ eligibility.reason }}</p>
        </section>

        <section class="card p-3 p-md-4 mb-3">
            <h5 class="mb-1">Payout Details</h5>
            <p class="text-muted small">Where your withdrawals are sent. Only masked identifiers are ever shown — secrets are encrypted and never displayed. Saving new details sends them for verification.</p>
            <div v-if="payoutAccount" class="d-flex flex-wrap align-items-center gap-2 mb-3 small">
                <span class="badge bg-light text-dark border">{{ payoutAccount.method_label ?? payoutAccount.method }}</span>
                <span class="fw-semibold">{{ payoutAccount.masked_destination }}</span>
                <span v-if="payoutAccount.ifsc" class="text-muted">{{ payoutAccount.ifsc }}</span>
                <span class="badge" :class="payoutAccount.status === 'verified' ? 'bg-success' : payoutAccount.status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'">{{ payoutAccount.status_label ?? payoutAccount.status }}</span>
                <span v-if="payoutAccount.rejection_reason" class="text-danger">{{ payoutAccount.rejection_reason }}</span>
            </div>
            <p v-else class="text-muted small">No payout details saved yet — add them below to unlock withdrawals.</p>
            <form @submit.prevent="submitPayout" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="payout-method" class="form-label small">Method</label>
                    <select id="payout-method" v-model="payoutForm.method" class="form-select" :disabled="payoutForm.processing">
                        <option v-for="m in payoutMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="payout-holder" class="form-label small">Account holder name</label>
                    <input id="payout-holder" v-model="payoutForm.account_holder_name" maxlength="150" class="form-control" placeholder="As per bank records" :disabled="payoutForm.processing" />
                </div>
                <template v-if="payoutForm.method === 'bank'">
                    <div class="col-md-2">
                        <label for="payout-bank" class="form-label small">Bank name</label>
                        <input id="payout-bank" v-model="payoutForm.bank_name" maxlength="150" class="form-control" :disabled="payoutForm.processing" />
                    </div>
                    <div class="col-md-2">
                        <label for="payout-ac" class="form-label small">Account number</label>
                        <input id="payout-ac" v-model="payoutForm.account_number" inputmode="numeric" maxlength="24" class="form-control" placeholder="New number" autocomplete="off" :disabled="payoutForm.processing" />
                    </div>
                    <div class="col-md-2">
                        <label for="payout-ifsc" class="form-label small">IFSC</label>
                        <input id="payout-ifsc" v-model="payoutForm.ifsc" maxlength="11" class="form-control text-uppercase" placeholder="ABCD0123456" :disabled="payoutForm.processing" />
                    </div>
                </template>
                <div v-else class="col-md-4">
                    <label for="payout-upi" class="form-label small">UPI ID</label>
                    <input id="payout-upi" v-model="payoutForm.upi_id" maxlength="255" class="form-control" placeholder="name@upi" autocomplete="off" :disabled="payoutForm.processing" />
                </div>
                <div class="col-12">
                    <button class="btn btn-svtp" :disabled="payoutForm.processing">{{ payoutAccount ? 'Replace Details (re-verification required)' : 'Save Payout Details' }}</button>
                </div>
            </form>
            <div v-if="payoutForm.hasErrors" class="text-danger small mt-2" role="alert">
                <div v-for="(error, key) in payoutForm.errors" :key="key">{{ error }}</div>
            </div>
        </section>

        <section class="card p-3 p-md-4 mb-3">
            <h5 class="mb-3">Withdrawal Requests</h5>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>ID</th><th>Amount</th><th>Status</th><th>Destination</th><th>Requested</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="item in withdrawals.data" :key="item.id">
                            <td class="fw-semibold">#{{ item.id }}</td>
                            <td class="text-nowrap">{{ money(item.amount) }}</td>
                            <td><span class="badge" :class="withdrawalBadge(item.status)">{{ item.status }}</span></td>
                            <td class="small">{{ item.payout_destination_masked ?? '—' }}</td>
                            <td class="small text-muted text-nowrap">{{ formatDateTime(item.requested_at) }}</td>
                            <td class="text-end">
                                <button v-if="item.status === 'pending'" type="button" class="btn btn-sm btn-outline-secondary" @click="cancelWithdrawal(item)">Cancel</button>
                                <span v-else class="text-muted small">—</span>
                            </td>
                        </tr>
                        <tr v-if="!withdrawals.data.length">
                            <td colspan="6" class="text-center text-muted py-3">No withdrawal requests yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="withdrawals.links" />
        </section>

        <section class="card p-3 p-md-4">
            <h5 class="mb-3">Ledger History</h5>
            <p class="text-muted small">Append-only record. Positive amounts with direction — credits add, debits subtract.</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Date</th><th>Type</th><th>Booking</th><th>Description</th><th class="text-end">Credit</th><th class="text-end">Debit</th></tr></thead>
                    <tbody>
                        <tr v-for="entry in ledger.data" :key="entry.id">
                            <td class="small text-muted text-nowrap">{{ formatDateTime(entry.created_at) }}</td>
                            <td><span class="badge" :class="entryBadge(entry.type)">{{ entryLabel(entry.type) }}</span></td>
                            <td class="small">{{ entry.booking?.booking_reference_id ?? '—' }}</td>
                            <td class="small">{{ entry.description ?? '—' }}</td>
                            <td class="small text-end text-nowrap">{{ entry.direction === 'credit' ? money(entry.amount) : '—' }}</td>
                            <td class="small text-end text-nowrap">{{ entry.direction === 'debit' ? money(entry.amount) : '—' }}</td>
                        </tr>
                        <tr v-if="!ledger.data.length">
                            <td colspan="6" class="text-center text-muted py-3">No ledger entries yet — earnings appear here once bookings are paid.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="ledger.links" />
        </section>
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
</style>
