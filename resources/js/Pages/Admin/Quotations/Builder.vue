<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    quotation: { type: Object, default: null },
    history: { type: Array, default: () => [] },
    lead: { type: Object, default: null },
    itemTypes: { type: Array, default: () => [] },
    serviceTypes: { type: Array, default: () => [] },
    tours: { type: Array, default: () => [] },
});

const isEdit = computed(() => props.quotation !== null);
const endpoint = appUrl('/admin/quotations');

function blankLine() {
    return { item_type: 'manual', product_id: '', description: '', quantity: 1, unit_price: '', tax_amount: 0, discount_amount: 0, metadata: null };
}

const initialLines = props.quotation?.items?.length
    ? props.quotation.items.map((i) => ({ item_type: i.item_type, product_id: i.product_id ?? '', description: i.description, quantity: i.quantity, unit_price: Number(i.unit_price), tax_amount: Number(i.tax_amount), discount_amount: Number(i.discount_amount), metadata: i.metadata ?? null }))
    : [blankLine()];

const form = useForm({
    lead_id: props.quotation?.lead_id ?? props.lead?.id ?? '',
    customer_user_id: props.quotation?.customer_user_id ?? '',
    service_type: props.quotation?.service_type ?? 'tour',
    currency: props.quotation?.currency ?? 'INR',
    valid_until: props.quotation?.valid_until ?? '',
    discount_amount: Number(props.quotation?.discount_amount ?? 0),
    terms: props.quotation?.terms ?? '',
    internal_note: props.quotation?.internal_note ?? '',
    customer_note: props.quotation?.customer_note ?? '',
    items: initialLines,
});

const leadResults = ref([]);
const leadLoading = ref(false);
let leadSeq = 0;
const customerResults = ref([]);
const customerLoading = ref(false);
let customerSeq = 0;

async function searchLeads(query) {
    const seq = ++leadSeq;
    leadLoading.value = true;
    try {
        const response = await axios.get(appUrl('/admin/select-options'), { params: { type: 'leads', search: query } });
        if (seq !== leadSeq) return [];
        return response.data.options ?? [];
    } catch (e) {
        return [];
    } finally {
        if (seq === leadSeq) leadLoading.value = false;
    }
}

async function searchCustomers(query) {
    const seq = ++customerSeq;
    customerLoading.value = true;
    try {
        const response = await axios.get(appUrl('/admin/customers/search'), { params: { q: query } });
        if (seq !== customerSeq) return [];
        customerResults.value = response.data.options ?? [];
        return customerResults.value;
    } catch (e) {
        return [];
    } finally {
        if (seq === customerSeq) customerLoading.value = false;
    }
}

function addLine() {
    form.items.push(blankLine());
}
function removeLine(index) {
    if (form.items.length > 1) form.items.splice(index, 1);
}
function fillFromTour(index, tourId) {
    const tour = props.tours.find((t) => Number(t.id) === Number(tourId));
    const line = form.items[index];
    if (!tour || !line) return;
    line.description = tour.title;
    line.unit_price = Number(tour.discounted_price) > 0 ? Number(tour.discounted_price) : Number(tour.price);
    line.metadata = { ...(line.metadata ?? {}), package_id: tour.id, package_title: tour.title };
}

const totals = computed(() => {
    let subtotal = 0, discount = Number(form.discount_amount || 0), tax = 0;
    form.items.forEach((line) => {
        const qty = Math.max(1, Number(line.quantity) || 1);
        const unit = Math.max(0, Number(line.unit_price) || 0);
        discount += Math.min(Math.max(0, Number(line.discount_amount) || 0), qty * unit);
        tax += Math.max(0, Number(line.tax_amount) || 0);
        subtotal += qty * unit;
    });
    discount = Math.min(discount, subtotal + tax);
    return { subtotal, discount, tax, total: Math.max(0, subtotal - discount + tax) };
});

function submit(asRevision = false) {
    const payload = {
        ...form.data(),
        lead_id: form.lead_id || null,
        customer_user_id: form.customer_user_id || null,
        items: form.items.map((line, index) => ({
            item_type: line.item_type,
            product_id: line.product_id || null,
            description: line.description,
            quantity: line.quantity,
            unit_price: line.unit_price,
            tax_amount: line.tax_amount,
            discount_amount: line.discount_amount,
            metadata: line.metadata,
        })),
    };
    if (!isEdit.value) {
        form.transform(() => payload).post(endpoint);
    } else if (asRevision) {
        if (!window.confirm('Create a new revision? The current revision becomes frozen superseded history.')) return;
        form.transform(() => payload).post(`${endpoint}/${props.quotation.id}/revisions`);
    } else {
        form.transform(() => payload).put(`${endpoint}/${props.quotation.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="isEdit ? appUrl(`/admin/quotations/${quotation.id}`) : endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Quotations</Link>
            <h2 class="mt-2 mb-1">{{ isEdit ? `Edit ${quotation.reference} (Rev ${quotation.revision_number})` : 'New Quotation' }}</h2>
            <p class="text-muted mb-0">Live math here is a preview — the server recomputes authoritative totals on save.</p>
        </div>

        <form @submit.prevent="submit(false)">
            <div class="row g-3">
                <div class="col-lg-8">
                    <section class="card p-3 p-md-4 mb-3">
                        <h5 class="mb-3">Line Items</h5>
                        <div v-for="(line, index) in form.items" :key="index" class="border rounded p-3 mb-3">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label small">Type</label>
                                    <select v-model="line.item_type" class="form-select form-select-sm">
                                        <option v-for="t in itemTypes" :key="t" :value="t">{{ t }}</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small">Tour (optional shortcut)</label>
                                    <select :value="line.product_id" class="form-select form-select-sm" @change="line.product_id = $event.target.value; fillFromTour(index, $event.target.value)">
                                        <option value="">—</option>
                                        <option v-for="t in tours" :key="t.id" :value="t.id">{{ t.title }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button v-if="form.items.length > 1" type="button" class="btn btn-sm btn-outline-danger mt-4" @click="removeLine(index)">Remove</button>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Description*</label>
                                    <input v-model="line.description" class="form-control form-control-sm" required maxlength="255" />
                                </div>
                                <div class="col-md-3"><label class="form-label small">Qty</label><input v-model.number="line.quantity" type="number" min="1" max="999" class="form-control form-control-sm" /></div>
                                <div class="col-md-3"><label class="form-label small">Rate</label><input v-model.number="line.unit_price" type="number" step="0.01" min="0" class="form-control form-control-sm" /></div>
                                <div class="col-md-3"><label class="form-label small">Discount</label><input v-model.number="line.discount_amount" type="number" step="0.01" min="0" class="form-control form-control-sm" /></div>
                                <div class="col-md-3"><label class="form-label small">Tax</label><input v-model.number="line.tax_amount" type="number" step="0.01" min="0" class="form-control form-control-sm" /></div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="addLine"><i class="bi bi-plus-lg me-1"></i>Add Line</button>
                        <div v-if="form.errors.items" class="text-danger small mt-2">{{ form.errors.items }}</div>
                    </section>

                    <section class="card p-3 p-md-4 mb-3">
                        <h5 class="mb-3">Notes &amp; Terms</h5>
                        <label for="q-terms" class="form-label small">Terms</label>
                        <textarea id="q-terms" v-model="form.terms" class="form-control form-control-sm mb-2" rows="2" maxlength="5000"></textarea>
                        <label for="q-customer-note" class="form-label small">Customer note (visible on shared view)</label>
                        <textarea id="q-customer-note" v-model="form.customer_note" class="form-control form-control-sm mb-2" rows="2" maxlength="5000"></textarea>
                        <label for="q-internal-note" class="form-label small">Internal note (staff only)</label>
                        <textarea id="q-internal-note" v-model="form.internal_note" class="form-control form-control-sm" rows="2" maxlength="5000"></textarea>
                    </section>
                </div>

                <div class="col-lg-4">
                    <section class="card p-3 p-md-4 mb-3">
                        <h5 class="mb-3">Offer</h5>
                        <SmartSelect v-model="form.lead_id" label="Lead (optional)" :fetch-options="searchLeads" placeholder="Search leads..." :loading="leadLoading" />
                        <div class="mt-2"><SmartSelect v-model="form.customer_user_id" label="Customer (optional)" :fetch-options="searchCustomers" placeholder="Search customers..." :loading="customerLoading" /></div>
                        <div class="row g-2 mt-1">
                            <div class="col-6"><label for="q-service" class="form-label small">Service</label><select id="q-service" v-model="form.service_type" class="form-select form-select-sm"><option v-for="t in serviceTypes" :key="t.value" :value="t.value">{{ t.label }}</option></select></div>
                            <div class="col-6"><label for="q-valid" class="form-label small">Valid until</label><input id="q-valid" v-model="form.valid_until" type="date" class="form-control form-control-sm" /></div>
                            <div class="col-12"><label for="q-discount" class="form-label small">Overall discount</label><input id="q-discount" v-model.number="form.discount_amount" type="number" step="0.01" min="0" class="form-control form-control-sm" /></div>
                        </div>
                    </section>

                    <section class="card p-3 p-md-4">
                        <h5 class="mb-3">Totals (preview)</h5>
                        <dl class="row mb-0 small">
                            <dt class="col-6 text-muted">Subtotal</dt><dd class="col-6 text-end">₹{{ totals.subtotal.toFixed(2) }}</dd>
                            <dt class="col-6 text-muted">Discount</dt><dd class="col-6 text-end">−₹{{ totals.discount.toFixed(2) }}</dd>
                            <dt class="col-6 text-muted">Tax</dt><dd class="col-6 text-end">₹{{ totals.tax.toFixed(2) }}</dd>
                            <dt class="col-6">Total</dt><dd class="col-6 text-end fw-bold">₹{{ totals.total.toFixed(2) }}</dd>
                        </dl>
                        <button class="btn btn-svtp w-100 mt-3" :disabled="form.processing">{{ isEdit ? 'Save Draft Changes' : 'Create Quotation' }}</button>
                        <button v-if="isEdit" type="button" class="btn btn-outline-secondary w-100 mt-2" :disabled="form.processing" @click="submit(true)">Save as New Revision</button>
                        <div v-if="form.hasErrors" class="text-danger small mt-2"><div v-for="(e, k) in form.errors" :key="k">{{ e }}</div></div>
                    </section>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
