<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    account: Object,
    vendor: Object,
    kyc: Object,
    reviewer: Object,
});

const endpoint = appUrl('/admin/payout-accounts');

const reviewForm = useForm({
    rejection_reason: '',
});

function verify() {
    if (!window.confirm(`Verify this payout destination? The vendor can then request withdrawals to ${props.account.masked_destination}.`)) return;
    reviewForm.patch(`${endpoint}/${props.account.id}/verify`, { preserveScroll: true });
}
function reject() {
    if (!reviewForm.rejection_reason) {
        reviewForm.setError('rejection_reason', 'A rejection reason is required.');
        return;
    }
    reviewForm.patch(`${endpoint}/${props.account.id}/reject`, { preserveScroll: true });
}
function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
function statusBadge(status) {
    return {
        pending: 'bg-warning text-dark',
        verified: 'bg-success',
        rejected: 'bg-danger',
    }[status] ?? 'bg-secondary';
}
const isPending = props.account.status === 'pending';
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>All Payout Accounts</Link>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                <h2 class="mb-0">{{ vendor?.business_name ?? 'Payout account' }}</h2>
                <span class="badge" :class="statusBadge(account.status)">{{ account.status_label ?? account.status }}</span>
            </div>
            <p class="text-muted mb-0 mt-1">Updated {{ formatDateTime(account.updated_at) }}</p>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Destination (masked)</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Vendor</dt><dd class="col-sm-8">{{ vendor?.business_name ?? '—' }}<span v-if="vendor?.email" class="text-muted"> · {{ vendor.email }}</span><span v-if="vendor?.phone" class="text-muted"> · {{ vendor.phone }}</span></dd>
                        <dt class="col-sm-4 text-muted">KYC</dt><dd class="col-sm-8"><span class="badge" :class="kyc.verified ? 'bg-success' : 'bg-warning text-dark'">{{ kyc.verified ? 'Verified' : `Unverified (${kyc.status ?? 'no verification'})` }}</span></dd>
                        <dt class="col-sm-4 text-muted">Method</dt><dd class="col-sm-8">{{ account.method_label ?? account.method }}</dd>
                        <dt class="col-sm-4 text-muted">Account holder</dt><dd class="col-sm-8">{{ account.account_holder_name }}</dd>
                        <dt v-if="account.bank_name" class="col-sm-4 text-muted">Bank</dt><dd v-if="account.bank_name" class="col-sm-8">{{ account.bank_name }}</dd>
                        <dt class="col-sm-4 text-muted">Destination</dt><dd class="col-sm-8 fw-bold">{{ account.masked_destination }}</dd>
                        <dt v-if="account.ifsc" class="col-sm-4 text-muted">IFSC</dt><dd v-if="account.ifsc" class="col-sm-8">{{ account.ifsc }}</dd>
                        <dt v-if="account.rejection_reason" class="col-sm-4 text-muted">Rejection reason</dt><dd v-if="account.rejection_reason" class="col-sm-8">{{ account.rejection_reason }}</dd>
                        <dt v-if="account.verified_at" class="col-sm-4 text-muted">Verified</dt><dd v-if="account.verified_at" class="col-sm-8">{{ formatDateTime(account.verified_at) }}<span v-if="reviewer" class="text-muted"> by {{ reviewer.name }}</span></dd>
                    </dl>
                    <p class="text-muted small mb-0 mt-3">Full account numbers / UPI IDs are encrypted and never rendered. Verify against the vendor's uploaded KYC proof (cancelled cheque) where needed.</p>
                </section>
            </div>

            <div class="col-lg-4">
                <form class="card p-3 p-md-4" @submit.prevent>
                    <h5 class="mb-3">Review</h5>
                    <fieldset :disabled="reviewForm.processing || !isPending">
                        <label for="rejection-reason" class="form-label small">Rejection reason (required to reject)</label>
                        <input id="rejection-reason" v-model="reviewForm.rejection_reason" class="form-control mb-2" maxlength="255" placeholder="e.g. Name mismatch with bank records" />
                        <div v-if="reviewForm.hasErrors" class="text-danger small mb-2" role="alert"><div v-for="(error, key) in reviewForm.errors" :key="key">{{ error }}</div></div>
                        <div v-if="isPending" class="d-grid gap-2">
                            <button type="button" class="btn btn-svtp" :disabled="reviewForm.processing" @click="verify">Verify</button>
                            <button type="button" class="btn btn-outline-danger" :disabled="reviewForm.processing" @click="reject">Reject</button>
                        </div>
                        <p v-else class="text-muted small mb-0">This account is {{ account.status }} — no further actions. A vendor update re-opens review.</p>
                    </fieldset>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
</style>
