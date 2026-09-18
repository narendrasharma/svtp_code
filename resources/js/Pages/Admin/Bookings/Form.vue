<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    packages: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    paymentStatuses: { type: Array, default: () => [] },
});

const packageOptions = computed(() => props.packages.map((pkg) => ({
    value: pkg.id,
    label: pkg.title,
    meta: pkg.discounted_price > 0 ? `₹${pkg.discounted_price} (was ₹${pkg.price})` : `₹${pkg.price}`,
})));

const endpoint = appUrl('/admin/bookings');
const form = useForm({
    package_id: '', customer_name: '', customer_phone: '', customer_email: '',
    country: '', pickup_address: '', special_requests: '',
    travel_date: '', total_adults: 1, total_children: 0,
    booking_status: 'pending', payment_status: 'unpaid',
});

const selectedPackage = computed(() => props.packages.find(pkg => Number(pkg.id) === Number(form.package_id)));
const effectivePrice = computed(() => {
    if (!selectedPackage.value) return 0;
    const discounted = Number(selectedPackage.value.discounted_price);
    return discounted > 0 ? discounted : Number(selectedPackage.value.price || 0);
});
const estimate = computed(() => {
    const total = effectivePrice.value * (Number(form.total_adults || 0) + Number(form.total_children || 0) * 0.5);
    return total > 0 ? `INR ${total.toFixed(2)}` : '—';
});

function submit() {
    form.post(endpoint);
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>All Bookings</Link>
            <h2 class="mt-2 mb-1">New Manual Booking</h2>
            <p class="text-muted mb-0">For phone, WhatsApp and walk-in tour bookings. The total is always recalculated server-side.</p>
        </div>
        <form class="card p-3 p-md-4" style="max-width: 760px;" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6"><SmartSelect v-model="form.package_id" label="Tour Package" :options="packageOptions" placeholder="Search tour packages..." :error="form.errors.package_id" required /><small class="text-danger">{{ form.errors.package_id }}</small></div>
                <div class="col-md-6"><label for="travel-date" class="form-label">Travel Date*</label><input id="travel-date" v-model="form.travel_date" type="date" class="form-control" required /><small class="text-danger">{{ form.errors.travel_date }}</small></div>
                <div class="col-md-3"><label for="adults" class="form-label">Adults*</label><input id="adults" v-model.number="form.total_adults" type="number" min="1" max="100" class="form-control" required /><small class="text-danger">{{ form.errors.total_adults }}</small></div>
                <div class="col-md-3"><label for="children" class="form-label">Children</label><input id="children" v-model.number="form.total_children" type="number" min="0" max="100" class="form-control" /><small class="text-danger">{{ form.errors.total_children }}</small></div>
                <div class="col-md-6 d-flex align-items-end"><p class="mb-1 small text-muted">Estimated total: <strong class="text-white">{{ estimate }}</strong><br />Recalculated on save — never taken from this page.</p></div>
                <div class="col-md-6"><label for="customer-name" class="form-label">Customer Name*</label><input id="customer-name" v-model="form.customer_name" class="form-control" required maxlength="255" /><small class="text-danger">{{ form.errors.customer_name }}</small></div>
                <div class="col-md-6"><label for="phone" class="form-label">Phone*</label><input id="phone" v-model="form.customer_phone" class="form-control" required maxlength="20" /><small class="text-danger">{{ form.errors.customer_phone }}</small></div>
                <div class="col-md-6"><label for="email" class="form-label">Email</label><input id="email" v-model="form.customer_email" type="email" class="form-control" maxlength="255" /><small class="text-danger">{{ form.errors.customer_email }}</small></div>
                <div class="col-md-6"><label for="country" class="form-label">Country</label><input id="country" v-model="form.country" class="form-control" maxlength="100" /><small class="text-danger">{{ form.errors.country }}</small></div>
                <div class="col-12"><label for="pickup" class="form-label">Pickup Address</label><input id="pickup" v-model="form.pickup_address" class="form-control" maxlength="255" /><small class="text-danger">{{ form.errors.pickup_address }}</small></div>
                <div class="col-12"><label for="requests" class="form-label">Special Requests</label><textarea id="requests" v-model="form.special_requests" class="form-control" rows="2" maxlength="1000"></textarea><small class="text-danger">{{ form.errors.special_requests }}</small></div>
                <div class="col-md-6"><label for="booking-status" class="form-label">Booking Status</label><select id="booking-status" v-model="form.booking_status" class="form-select"><option v-for="option in statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select><small class="text-danger">{{ form.errors.booking_status }}</small></div>
                <div class="col-md-6"><label for="payment-status" class="form-label">Payment Status</label><select id="payment-status" v-model="form.payment_status" class="form-select"><option v-for="option in paymentStatuses" :key="option.value" :value="option.value">{{ option.label }}</option></select><small class="text-danger">{{ form.errors.payment_status }}</small></div>
                <div class="col-12"><button class="btn btn-svtp" :disabled="form.processing">Create Booking</button></div>
            </div>
        </form>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
</style>
