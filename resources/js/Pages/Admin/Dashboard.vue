<script setup>
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineProps({ stats: Object, recentBookings: Array });
</script>

<template>
    <AdminLayout>
        <h2>Dashboard</h2>
        <div class="row g-3 mt-2">
            <div class="col-md-3"><div class="card p-3"><small>Total Bookings</small><h3>{{ stats.total_bookings }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Revenue</small><h3>₹{{ stats.revenue }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Active Tours</small><h3>{{ stats.active_tours }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Pending Reviews</small><h3>{{ stats.pending_reviews }}</h3></div></div>
        </div>

        <h4 class="mt-4">Recent Bookings</h4>
        <table class="table">
            <tbody>
                <tr v-for="b in recentBookings" :key="b.id">
                    <td>{{ b.booking_reference_id }}</td>
                    <td>{{ b.package.title }}</td>
                    <td>{{ b.user.name }}</td>
                    <td>₹{{ b.total_amount }}</td>
                    <td>{{ b.booking_status }}</td>
                </tr>
            </tbody>
        </table>
    </AdminLayout>
</template>
