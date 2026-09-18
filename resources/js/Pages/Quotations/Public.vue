<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    quotation: Object,
    decisionUrls: { type: Object, default: () => ({}) },
    canDecide: { type: Boolean, default: false },
});

const note = ref('');
const answerForm = useForm({ customer_note: '' });

function accept() {
    answerForm.customer_note = note.value;
    answerForm.post(props.decisionUrls.accept_url, { preserveScroll: true });
}

function reject() {
    answerForm.post(props.decisionUrls.reject_url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <div class="container py-5" style="max-width: 720px;">
            <p class="text-muted mb-1">Quotation</p>
            <h1 class="mb-1">{{ quotation.reference }}</h1>
            <p class="mb-4"><span class="badge bg-secondary">{{ quotation.status }}</span><span v-if="quotation.is_expired" class="badge bg-warning text-dark ms-2">Expired</span></p>

            <div class="card p-4 mb-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            <tr v-for="(item, i) in quotation.items" :key="i">
                                <td>{{ item.description }}</td>
                                <td class="text-end">{{ item.quantity }}</td>
                                <td class="text-end">{{ item.unit_price }}</td>
                                <td class="text-end">{{ item.total_amount }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <dl class="row mb-0 mt-3">
                    <dt class="col-6 text-muted">Subtotal</dt><dd class="col-6 text-end">{{ quotation.currency }} {{ quotation.subtotal }}</dd>
                    <dt class="col-6 text-muted">Discount</dt><dd class="col-6 text-end">−{{ quotation.currency }} {{ quotation.discount_amount }}</dd>
                    <dt class="col-6 text-muted">Tax</dt><dd class="col-6 text-end">{{ quotation.currency }} {{ quotation.tax_amount }}</dd>
                    <dt class="col-6">Total</dt><dd class="col-6 text-end fw-bold">{{ quotation.currency }} {{ quotation.total_amount }}</dd>
                </dl>
            </div>

            <div v-if="quotation.customer_note" class="card p-4 mb-3">
                <h5>Note</h5>
                <p class="mb-0">{{ quotation.customer_note }}</p>
            </div>

            <div v-if="quotation.terms" class="card p-4 mb-3">
                <h5>Terms</h5>
                <p class="mb-0 text-muted">{{ quotation.terms }}</p>
            </div>

            <p class="text-muted small">Valid until {{ quotation.valid_until ?? '—' }}. To accept this offer, reply to us on phone or WhatsApp quoting <strong>{{ quotation.reference }}</strong>.</p>

            <div v-if="canDecide" class="card p-4 mb-3">
                <h5>Respond online</h5>
                <p class="text-muted small">This secure link lets you accept or reject without logging in. Accepting notifies our team, who confirm availability before creating your booking.</p>
                <label for="decision-note" class="form-label small">Note for our team (optional)</label>
                <textarea id="decision-note" v-model="note" class="form-control mb-3" rows="2" maxlength="2000" placeholder="e.g. Prefer morning pickup"></textarea>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-svtp" :disabled="answerForm.processing" @click="accept">Accept Quotation</button>
                    <button type="button" class="btn btn-outline-secondary" :disabled="answerForm.processing" @click="reject">Reject</button>
                </div>
                <div v-if="answerForm.hasErrors" class="text-danger small mt-2"><div v-for="(e, k) in answerForm.errors" :key="k">{{ e }}</div></div>
            </div>
            <p v-else-if="['accepted', 'converted'].includes(quotation.status)" class="alert alert-success small">This quotation was accepted — our team will be in touch to confirm your booking.</p>
            <p v-else-if="['rejected', 'expired'].includes(quotation.status)" class="text-muted small">This quotation is {{ quotation.status }} and can no longer be decided here.</p>
        </div>
    </AppLayout>
</template>
