<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    packages: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/bookings');
const customerEndpoint = appUrl('/admin/customers');

const form = useForm({
    user_id: '',
    customer_name: '', customer_phone: '', customer_email: '',
    country: '', pickup_address: '', special_requests: '',
    package_id: '', travel_date: '', total_adults: 2, total_children: 0,
    source: 'walk_in', booking_status: 'pending', payment_status: 'unpaid',
    initial_payment_amount: '', initial_payment_method: 'cash', initial_payment_note: '',
});

const showNewCustomer = ref(false);
const newCustomer = useForm({ name: '', phone: '', email: '' });
const customerLoading = ref(false);
let customerSeq = 0;

async function searchCustomers(query) {
    const seq = ++customerSeq;
    customerLoading.value = true;
    try {
        const response = await axios.get(appUrl('/admin/customers/search'), { params: { q: query } });
        if (seq !== customerSeq) return [];
        return response.data.options ?? [];
    } catch (e) {
        return [];
    } finally {
        if (seq === customerSeq) customerLoading.value = false;
    }
}

function createCustomer() {
    newCustomer.post(customerEndpoint, {
        preserveScroll: true,
        onSuccess: () => {
            showNewCustomer.value = false;
            newCustomer.reset();
        },
    });
}

const packageOptions = computed(() => props.packages.map((pkg) => ({
    value: pkg.id,
    label: pkg.title,
    meta: pkg.discounted_price > 0 ? `₹${pkg.discounted_price} (was ₹${pkg.price})` : `₹${pkg.price}`,
})));

const selectedPackage = computed(() => props.packages.find((pkg) => Number(pkg.id) === Number(form.package_id)));
const estimate = computed(() => {
    if (!selectedPackage.value) return '—';
    const price = Number(selectedPackage.value.discounted_price) > 0 ? Number(selectedPackage.value.discounted_price) : Number(selectedPackage.value.price || 0);
    const total = price * (Number(form.total_adults || 0) + Number(form.total_children || 0) * 0.5);
    return total > 0 ? `₹${total.toFixed(2)}` : '—';
});

function submit() {
    form.transform((data) => ({ ...data, user_id: data.user_id || null })).post(endpoint);
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>All Bookings</Link>
            <h2 class="mt-2 mb-1">Reservation Desk</h2>
            <p class="text-muted mb-0">One screen for walk-in, phone and WhatsApp bookings. Pricing is always recalculated server-side.</p>
        </div>

        <form @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-lg-8">
                    <section class="card p-3 p-md-4 mb-3">
                        <h5 class="mb-3">1 · Customer</h5>
                        <SmartSelect v-model="form.user_id" label="Existing customer" :fetch-options="searchCustomers" placeholder="Search by phone, email or name..." :loading="customerLoading" />
                        <small class="text-danger">{{ form.errors.user_id }}</small>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" @click="showNewCustomer = !showNewCustomer">
                            {{ showNewCustomer ? 'Hide quick-create' : 'Quick-create new customer' }}
                        </button>
                    </section>

                    <section class="card p-3 p-md-4 mb-3">
                        <h5 class="mb-3">2 · Service &amp; Trip</h5>
                        <div class="row g-3">
                            <div class="col-12"><SmartSelect v-model="form.package_id" label="Tour Package" :options="packageOptions" placeholder="Search tour packages..." :error="form.errors.package_id" required /></div>
                            <div class="col-md-4"><label for="desk-travel" class="form-label">Travel Date*</label><input id="desk-travel" v-model="form.travel_date" type="date" class="form-control" required /><small class="text-danger">{{ form.errors.travel_date }}</small></div>
                            <div class="col-md-4"><label for="desk-adults" class="form-label">Adults*</label><input id="desk-adults" v-model.number="form.total_adults" type="number" min="1" max="100" class="form-control" required /><small class="text-danger">{{ form.errors.total_adults }}</small></div>
                            <div class="col-md-4"><label for="desk-children" class="form-label">Children</label><input id="desk-children" v-model.number="form.total_children" type="number" min="0" max="100" class="form-control" /></div>
                            <div class="col-md-6"><label for="desk-source" class="form-label">Channel*</label><select id="desk-source" v-model="form.source" class="form-select"><option v-for="s in sources" :key="s.value" :value="s.value">{{ s.label }}</option></select><small class="text-danger">{{ form.errors.source }}</small></div>
                            <div class="col-md-6 d-flex align-items-end"><p class="mb-1 small text-muted">Estimate: <strong class="text-white">{{ estimate }}</strong><br />Recalculated on save.</p></div>
                            <div class="col-md-6"><label for="desk-pickup" class="form-label">Pickup Address</label><input id="desk-pickup" v-model="form.pickup_address" class="form-control" maxlength="255" /></div>
                            <div class="col-md-6"><label for="desk-requests" class="form-label">Special Requests</label><input id="desk-requests" v-model="form.special_requests" class="form-control" maxlength="1000" /></div>
                        </div>
                    </section>

                    <section class="card p-3 p-md-4 mb-3">
                        <h5 class="mb-3">3 · First Collection (optional)</h5>
                        <div class="row g-3">
                            <div class="col-md-4"><label for="desk-pay-amount" class="form-label">Amount</label><input id="desk-pay-amount" v-model="form.initial_payment_amount" type="number" step="0.01" min="0" class="form-control" /><small class="text-danger">{{ form.errors.initial_payment_amount }}</small></div>
                            <div class="col-md-4"><label for="desk-pay-method" class="form-label">Method</label><select id="desk-pay-method" v-model="form.initial_payment_method" class="form-select"><option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option></select></div>
                            <div class="col-md-4"><label for="desk-pay-note" class="form-label">Note</label><input id="desk-pay-note" v-model="form.initial_payment_note" class="form-control" maxlength="255" /></div>
                        </div>
                        <button class="btn btn-svtp mt-3" :disabled="form.processing">Confirm Booking</button>
                        <div v-if="form.hasErrors" class="text-danger small mt-2"><div v-for="(e, k) in form.errors" :key="k">{{ e }}</div></div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <section v-if="showNewCustomer" class="card p-3 p-md-4 mb-3">
                        <h5 class="mb-3">Quick-create Customer</h5>
                        <label for="nc-name" class="form-label small">Name*</label>
                        <input id="nc-name" v-model="newCustomer.name" class="form-control form-control-sm mb-2" maxlength="255" />
                        <label for="nc-phone" class="form-label small">Phone*</label>
                        <input id="nc-phone" v-model="newCustomer.phone" class="form-control form-control-sm mb-2" maxlength="30" />
                        <label for="nc-email" class="form-label small">Email (optional)</label>
                        <input id="nc-email" v-model="newCustomer.email" type="email" class="form-control form-control-sm mb-2" maxlength="255" />
                        <div v-if="newCustomer.hasErrors" class="text-danger small mb-2"><div v-for="(e, k) in newCustomer.errors" :key="k">{{ e }}</div></div>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" :disabled="newCustomer.processing" @click="createCustomer">Create &amp; Search Above</button>
                        <p class="text-muted small mt-2 mb-0">No password is set — the account is invite-ready for a later phase. Duplicate emails are refused; search and link instead.</p>
                    </section>

                    <section class="card p-3 p-md-4">
                        <h5 class="mb-3">Guest Snapshot</h5>
                        <p class="text-muted small">Used when no customer account is linked.</p>
                        <label for="desk-name" class="form-label small">Name*</label>
                        <input id="desk-name" v-model="form.customer_name" class="form-control form-control-sm mb-2" maxlength="255" required />
                        <label for="desk-phone" class="form-label small">Phone*</label>
                        <input id="desk-phone" v-model="form.customer_phone" class="form-control form-control-sm mb-2" maxlength="20" required />
                        <label for="desk-email" class="form-label small">Email</label>
                        <input id="desk-email" v-model="form.customer_email" type="email" class="form-control form-control-sm mb-2" maxlength="255" />
                        <label for="desk-country" class="form-label small">Country</label>
                        <input id="desk-country" v-model="form.country" class="form-control form-control-sm" maxlength="100" />
                    </section>
                </div>
            </div>
        </form>
    </AdminLayout>
</template>
