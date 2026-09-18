<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    tours: { type: Array, default: () => [] },
});

const endpoint = appUrl('/vendor/bookings');

const form = useForm({
    package_id: '', travel_date: '', total_adults: 2, total_children: 0,
    customer_name: '', customer_phone: '', customer_email: '',
    pickup_address: '', special_requests: '',
});

const tourOptions = computed(() => props.tours.map((t) => ({
    value: t.id,
    label: t.title,
    meta: t.discounted_price > 0 ? `₹${t.discounted_price} (was ₹${t.price})` : `₹${t.price}`,
})));

function submit() {
    form.post(endpoint);
}
</script>

<template>
    <VendorLayout>
        <div class="mb-4">
            <Link :href="endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>My Bookings</Link>
            <h2 class="mt-2 mb-1">Sell Your Tour Offline</h2>
            <p class="text-muted mb-0">Phone and walk-in guests for <strong>your own tours only</strong>. Pricing is recalculated server-side; payment is recorded by admin (booking starts unpaid).</p>
        </div>

        <form class="card p-3 p-md-4" style="max-width: 760px;" @submit.prevent="submit">
            <div v-if="!tours.length" class="alert alert-warning">You have no active approved tours to sell yet.</div>
            <div class="row g-3">
                <div class="col-12"><SmartSelect v-model="form.package_id" label="Your Tour" :options="tourOptions" placeholder="Search your tours..." :error="form.errors.package_id" required /></div>
                <div class="col-md-4"><label for="v-travel" class="form-label">Travel Date*</label><input id="v-travel" v-model="form.travel_date" type="date" class="form-control" required /><small class="text-danger">{{ form.errors.travel_date }}</small></div>
                <div class="col-md-4"><label for="v-adults" class="form-label">Adults*</label><input id="v-adults" v-model.number="form.total_adults" type="number" min="1" max="100" class="form-control" required /></div>
                <div class="col-md-4"><label for="v-children" class="form-label">Children</label><input id="v-children" v-model.number="form.total_children" type="number" min="0" max="100" class="form-control" /></div>
                <div class="col-md-6"><label for="v-name" class="form-label">Customer Name*</label><input id="v-name" v-model="form.customer_name" class="form-control" required maxlength="255" /><small class="text-danger">{{ form.errors.customer_name }}</small></div>
                <div class="col-md-6"><label for="v-phone" class="form-label">Phone*</label><input id="v-phone" v-model="form.customer_phone" class="form-control" required maxlength="20" /><small class="text-danger">{{ form.errors.customer_phone }}</small></div>
                <div class="col-md-6"><label for="v-email" class="form-label">Email</label><input id="v-email" v-model="form.customer_email" type="email" class="form-control" maxlength="255" /></div>
                <div class="col-md-6"><label for="v-pickup" class="form-label">Pickup Address</label><input id="v-pickup" v-model="form.pickup_address" class="form-control" maxlength="255" /></div>
                <div class="col-12"><label for="v-requests" class="form-label">Special Requests</label><input id="v-requests" v-model="form.special_requests" class="form-control" maxlength="1000" /></div>
                <div class="col-12"><button class="btn btn-svtp" :disabled="form.processing || !tours.length">Create Booking</button></div>
                <div v-if="form.hasErrors" class="col-12 text-danger small"><div v-for="(e, k) in form.errors" :key="k">{{ e }}</div></div>
            </div>
        </form>
    </VendorLayout>
</template>
