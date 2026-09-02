<script setup>
import { appUrl } from '../../appUrl';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';

defineProps({ bookings: Object });

function updateStatus(booking, status) {
    router.patch(`${appUrl('/admin/bookings')}/${booking.id}/status`, { booking_status: status });
}

function removeBooking(booking) {
    if (window.confirm(`Delete booking ${booking.booking_reference_id}?`)) {
        router.delete(`${appUrl('/admin/bookings')}/${booking.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center">
            <h2>Bookings</h2>
            <Link :href="appUrl('/admin/bookings/create')" class="btn btn-svtp">Add Manual Booking</Link>
        </div>
        <div class="table-responsive mt-3"><table class="table align-middle">
            <thead><tr><th>Reference</th><th>Customer</th><th>Package</th><th>Travel Date</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <tr v-for="b in bookings.data" :key="b.id">
                    <td>{{ b.booking_reference_id }}</td>
                    <td><strong>{{ b.customer_name || b.user.name }}</strong><div class="small">{{ b.customer_phone }}</div></td>
                    <td>{{ b.package.title }}</td>
                    <td>{{ new Date(b.travel_date).toLocaleDateString('en-IN') }}</td>
                    <td>
                        <select :value="b.booking_status" @change="updateStatus(b, $event.target.value)" class="form-select form-select-sm">
                            <option value="confirmed">Confirmed</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option>
                        </select>
                    </td>
                    <td class="text-nowrap"><Link :href="`${appUrl('/admin/bookings')}/${b.id}/edit`" class="btn btn-sm btn-outline-primary me-2">Edit</Link><button class="btn btn-sm btn-outline-danger" @click="removeBooking(b)">Delete</button></td>
                </tr>
            </tbody>
        </table></div>
    </AdminLayout>
</template>
