<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    earning: { type: Object, required: true },
    unpaidRemainder: { type: [String, Number], default: '0.00' },
});

const endpoint = appUrl(`/vendor/taxi/earnings/${props.earning.id}`);

const adjustForm = useForm({ amount: '', kind: 'bonus', reason: '' });
const voidForm = useForm({ reason: '' });

function submitAdjustment() {
    adjustForm.post(appUrl(`/vendor/taxi/earnings/${props.earning.id}/adjustments`), {
        preserveScroll: true,
        onSuccess: () => adjustForm.reset(),
    });
}

function markPayable() {
    router.patch(`${endpoint}/payable`, {}, { preserveScroll: true });
}

function voidEarning() {
    if (!confirm('Void this earning? Unpaid rows only.')) {
        return;
    }
    voidForm.patch(`${endpoint}/void`, { preserveScroll: true });
}

function money(value) {
    return `${props.earning.currency} ${Number(value).toFixed(2)}`;
}

const snapshot = props.earning.calculation_snapshot ?? {};
</script>

<template>
    <VendorLayout>
        <div class="mb-3">
            <Link :href="appUrl('/vendor/taxi/earnings')" class="small">&larr; All earnings</Link>
            <h2 class="mt-2 mb-1">{{ earning.earning_number }}</h2>
            <p class="text-muted mb-0">
                {{ earning.driver?.first_name }} {{ earning.driver?.last_name }} ·
                Trip {{ earning.booking?.reference ?? '—' }} ·
                <span class="badge bg-secondary">{{ earning.status }}</span>
            </p>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card p-3">
                    <h6>Earning amounts</h6>
                    <dl class="row mb-0 small">
                        <dt class="col-6">Gross</dt><dd class="col-6 text-end">{{ money(earning.gross_earning) }}</dd>
                        <dt class="col-6">Adjustments</dt><dd class="col-6 text-end">{{ money(earning.adjustments_total) }}</dd>
                        <dt class="col-6">Net</dt><dd class="col-6 text-end fw-semibold">{{ money(earning.net_earning) }}</dd>
                        <dt class="col-6">Paid</dt><dd class="col-6 text-end">{{ money(earning.paid_amount) }}</dd>
                        <dt class="col-6">Unpaid remainder</dt><dd class="col-6 text-end fw-semibold">{{ money(unpaidRemainder) }}</dd>
                        <dt class="col-6">Payable at</dt><dd class="col-6 text-end">{{ earning.payable_at ?? '—' }}</dd>
                        <dt class="col-6">Paid at</dt><dd class="col-6 text-end">{{ earning.paid_at ?? '—' }}</dd>
                    </dl>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <Link :href="appUrl(`/vendor/taxi/payouts/create?driver_id=${earning.driver_id}`)" class="btn btn-sm btn-outline-secondary">Create payout</Link>
                        <button v-if="earning.status === 'pending'" class="btn btn-sm btn-svtp" @click="markPayable">Mark payable</button>
                        
                    </div>
                </div>

                <div class="card p-3 mt-3">
                    <h6>Original calculation</h6>
                    <pre class="small bg-light p-2 rounded mb-0" style="white-space: pre-wrap;">{{ JSON.stringify(snapshot, null, 2) }}</pre>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card p-3">
                    <h6>Adjustments</h6>
                    <ul v-if="earning.adjustments?.length" class="list-unstyled small mb-3">
                        <li v-for="a in earning.adjustments" :key="a.id" class="border-bottom py-1">
                            <span class="fw-semibold">{{ a.kind }}</span>
                            {{ earning.currency }} {{ Number(a.amount).toFixed(2) }} — {{ a.reason }}
                            <span class="text-muted">({{ a.creator?.name ?? 'system' }})</span>
                        </li>
                    </ul>
                    <p v-else class="small text-muted">No adjustments.</p>
                    <form v-if="!['paid', 'void'].includes(earning.status) && !earning.payout_items?.length" @submit.prevent="submitAdjustment" class="row g-2">
                        <div v-for="(error, key) in adjustForm.errors" :key="key" class="text-danger small">{{ error }}</div>
                        <div class="col-4">
                            <input v-model="adjustForm.amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="± amount" required />
                        </div>
                        <div class="col-4">
                            <select v-model="adjustForm.kind" class="form-select form-select-sm">
                                <option value="bonus">Bonus</option>
                                <option value="penalty">Penalty</option>
                                <option value="correction">Correction</option>
                                <option value="toll_reimbursement">Toll reimbursement</option>
                                <option value="misc">Misc</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <input v-model="adjustForm.reason" class="form-control form-control-sm" placeholder="Reason (required)" maxlength="500" required />
                        </div>
                        <div class="col-12">
                            <button class="btn btn-sm btn-svtp" :disabled="adjustForm.processing">Post adjustment</button>
                        </div>
                    </form>
                </div>

                <div class="card p-3 mt-3">
                    <h6>Payout allocations</h6>
                    <ul v-if="earning.payout_items?.length" class="list-unstyled small mb-0">
                        <li v-for="item in earning.payout_items" :key="item.id">
                            <Link :href="appUrl(`/vendor/taxi/payouts/${item.payout.id}`)">{{ item.payout.payout_number }}</Link>
                            — {{ earning.currency }} {{ Number(item.amount).toFixed(2) }}
                            <span class="badge bg-secondary">{{ item.payout.status }}</span>
                        </li>
                    </ul>
                    <p v-else class="small text-muted mb-0">Not allocated to any payout.</p>
                </div>
            </div>
        </div>
    </VendorLayout>
</template>
