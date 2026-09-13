<script setup>
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineProps({ stats: Object, recentBookings: Array });
</script>

<template>
    <AdminLayout>
        <h2>Dashboard</h2>

        <!-- Statistics cards -->
        <div class="row g-3 mt-2">
            <!-- Existing cards -->
            <div class="col-md-3"><div class="card p-3"><small>Total Bookings</small><h3>{{ stats.total_bookings }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Revenue</small><h3>₹{{ stats.revenue }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Active Tours</small><h3>{{ stats.active_tours }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Pending Reviews</small><h3>{{ stats.pending_reviews }}</h3></div></div>

            <!-- New cards -->
            <div class="col-md-3"><div class="card p-3"><small>Total Tour Packages</small><h3>{{ stats.total_tour_packages }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Categories</small><h3>{{ stats.total_categories }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Tags</small><h3>{{ stats.total_tags }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Destinations</small><h3>{{ stats.total_destinations }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Places</small><h3>{{ stats.total_places }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Users</small><h3>{{ stats.total_users }}</h3></div></div>

            <!-- Optional Vendor card – will render only if the stat exists -->
            <div v-if="stats.total_vendors !== undefined" class="col-md-3">
                <div class="card p-3"><small>Total Vendors</small><h3>{{ stats.total_vendors }}</h3></div>
            </div>
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
