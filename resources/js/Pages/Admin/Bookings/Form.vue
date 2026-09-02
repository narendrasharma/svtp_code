<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ booking: { type: Object, default: null }, packages: { type: Array, default: () => [] } });
const form = useForm({
    package_id: props.booking?.package_id || '', customer_name: props.booking?.customer_name || '',
    customer_phone: props.booking?.customer_phone || '', customer_email: props.booking?.customer_email || '',
    pickup_address: props.booking?.pickup_address || '', travel_date: props.booking?.travel_date?.slice(0, 10) || '',
    total_adults: props.booking?.total_adults || 1, total_children: props.booking?.total_children || 0,
    total_amount: props.booking?.total_amount || '', payment_status: props.booking?.payment_status || 'pending',
    booking_status: props.booking?.booking_status || 'confirmed',
});

function submit() {
    const url = props.booking ? `${appUrl('/admin/bookings')}/${props.booking.id}` : appUrl('/admin/bookings');
    props.booking ? form.put(url) : form.post(url);
}
</script>

<template>
    <AdminLayout>
        <h2>{{ booking ? 'Edit Booking' : 'Add Manual Booking' }}</h2>
        <form class="card p-4 mt-3" style="max-width: 760px;" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Customer Name*</label><input v-model="form.customer_name" class="form-control" required /><small class="text-danger">{{ form.errors.customer_name }}</small></div>
                <div class="col-md-6"><label class="form-label">Phone*</label><input v-model="form.customer_phone" class="form-control" required /><small class="text-danger">{{ form.errors.customer_phone }}</small></div>
                <div class="col-md-6"><label class="form-label">Email</label><input v-model="form.customer_email" type="email" class="form-control" /><small class="text-danger">{{ form.errors.customer_email }}</small></div>
                <div class="col-md-6"><label class="form-label">Pickup Address</label><input v-model="form.pickup_address" class="form-control" /></div>
                <div class="col-md-6"><label class="form-label">Package*</label><select v-model="form.package_id" class="form-select" required><option value="" disabled>Select package</option><option v-for="pkg in packages" :key="pkg.id" :value="pkg.id">{{ pkg.title }}</option></select><small class="text-danger">{{ form.errors.package_id }}</small></div>
                <div class="col-md-6"><label class="form-label">Travel Date*</label><input v-model="form.travel_date" type="date" class="form-control" required /></div>
                <div class="col-md-3"><label class="form-label">Adults*</label><input v-model.number="form.total_adults" type="number" min="1" class="form-control" required /></div>
                <div class="col-md-3"><label class="form-label">Children</label><input v-model.number="form.total_children" type="number" min="0" class="form-control" /></div>
                <div class="col-md-6"><label class="form-label">Total Amount*</label><input v-model="form.total_amount" type="number" min="0" step="0.01" class="form-control" required /></div>
                <div class="col-md-6"><label class="form-label">Payment Status*</label><select v-model="form.payment_status" class="form-select"><option value="pending">Pending</option><option value="paid">Paid</option><option value="failed">Failed</option></select></div>
                <div class="col-md-6"><label class="form-label">Booking Status*</label><select v-model="form.booking_status" class="form-select"><option value="confirmed">Confirmed</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
                <div class="col-12"><button class="btn btn-svtp" :disabled="form.processing">{{ booking ? 'Update Booking' : 'Create Booking' }}</button></div>
            </div>
        </form>
    </AdminLayout>
</template>
