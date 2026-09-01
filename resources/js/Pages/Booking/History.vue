<script setup>
import { appUrl } from '../../appUrl';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({ bookings: Object });

const statusBadge = {
    confirmed: 'bg-primary',
    completed: 'bg-success',
    cancelled: 'bg-danger',
};
</script>

<template>
    <AppLayout>
        <div class="container py-4">
            <h2>My Bookings</h2>
            <table class="table align-middle mt-3">
                <thead>
                    <tr><th>Reference</th><th>Package</th><th>Travel Date</th><th>Amount</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    <tr v-for="b in bookings.data" :key="b.id">
                        <td>{{ b.booking_reference_id }}</td>
                        <td>{{ b.package.title }}</td>
                        <td>{{ b.travel_date }}</td>
                        <td>₹{{ b.total_amount }}</td>
                        <td><span class="badge" :class="statusBadge[b.booking_status]">{{ b.booking_status }}</span></td>
                        <td><a :href="`${appUrl('/bookings')}/${b.id}/invoice`">Invoice</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
