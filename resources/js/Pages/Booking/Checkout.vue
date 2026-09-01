<script setup>
import { appUrl } from '../../appUrl';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ package: Object });

const form = useForm({
    package_id: props.package.id,
    travel_date: '',
    total_adults: 2,
    total_children: 0,
    gateway: 'razorpay',
});

function submit() {
    form.post(appUrl('/bookings'));
}
</script>

<template>
    <AppLayout>
        <div class="container py-4" style="max-width: 560px;">
            <h2>Checkout — {{ package.title }}</h2>
            <form @submit.prevent="submit" class="mt-3">
                <div class="mb-3">
                    <label class="form-label">Travel Date</label>
                    <input v-model="form.travel_date" type="date" class="form-control" required />
                </div>
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">Adults</label>
                        <input v-model.number="form.total_adults" type="number" min="1" class="form-control" />
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">Children</label>
                        <input v-model.number="form.total_children" type="number" min="0" class="form-control" />
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Method</label>
                    <select v-model="form.gateway" class="form-select">
                        <option value="razorpay">Razorpay</option>
                        <option value="paytm">Paytm</option>
                    </select>
                </div>
                <button class="btn btn-svtp w-100" :disabled="form.processing">Proceed to Pay</button>
            </form>
        </div>
    </AppLayout>
</template>
