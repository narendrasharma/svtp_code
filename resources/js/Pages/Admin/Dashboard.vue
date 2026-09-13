<script setup>
import AdminLayout from '../../Layouts/AdminLayout.vue';
import PackageCreationChart from '../../Components/PackageCreationChart.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    stats: Object,
    recentBookings: Array,
    recentTourPackages: Array,
    topDestinations: Array,
    packageStats: Object,
    packageCreationChart: Array,
});
</script>

<template>
    <AdminLayout>
        <h2 class="mb-4">Dashboard</h2>

        <!-- Existing statistic cards -->
        <div class="row g-3 mt-2">
            <div class="col-md-3"><div class="card p-3"><small>Total Bookings</small><h3>{{ stats.total_bookings }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Revenue</small><h3>₹{{ stats.revenue }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Active Tours</small><h3>{{ stats.active_tours }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Pending Reviews</small><h3>{{ stats.pending_reviews }}</h3></div></div>

            <!-- New statistic cards -->
            <div class="col-md-3"><div class="card p-3"><small>Total Tour Packages</small><h3>{{ stats.total_tour_packages }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Categories</small><h3>{{ stats.total_categories }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Tags</small><h3>{{ stats.total_tags }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Destinations</small><h3>{{ stats.total_destinations }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Places</small><h3>{{ stats.total_places }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Users</small><h3>{{ stats.total_users }}</h3></div></div>

            <div v-if="stats.total_vendors !== undefined" class="col-md-3">
                <div class="card p-3"><small>Total Vendors</small><h3>{{ stats.total_vendors }}</h3></div>
            </div>
        </div>

        <!-- -------------------------------------------------------------
             Recent Tour Packages
        -------------------------------------------------------------- -->
        <h4 class="mt-5">Recent Tour Packages</h4>
        <div class="table-responsive">
            <table class="table mt-2">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="pkg in recentTourPackages" :key="pkg.id">
                        <td>{{ pkg.title }}</td>
                        <td>₹{{ pkg.price }}</td>
                        <td>
                            <!-- Combine days and nights into a readable format -->
                            <span v-if="pkg.duration_days && pkg.duration_nights">
                                {{ pkg.duration_days }}d {{ pkg.duration_nights }}n
                            </span>
                            <span v-else-if="pkg.duration_days">
                                {{ pkg.duration_days }}d
                            </span>
                            <span v-else-if="pkg.duration_nights">
                                {{ pkg.duration_nights }}n
                            </span>
                            <span v-else>—</span>
                        </td>
                        <td>
                            <span v-if="pkg.is_active" class="badge bg-success">Active</span>
                            <span v-else class="badge bg-secondary">Inactive</span>
                        </td>
                        <td>{{ new Date(pkg.created_at).toLocaleDateString() }}</td>
                        <td>
                            <Link :href="`/admin/packages/${pkg.id}/edit`" class="btn btn-sm btn-primary">Edit</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- -------------------------------------------------------------
             Top Destinations
        -------------------------------------------------------------- -->
        <h4 class="mt-5">Top Destinations (by Packages)</h4>
        <div class="table-responsive">
            <table class="table mt-2">
                <thead>
                    <tr>
                        <th>Destination</th>
                        <th>Package Count</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="dest in topDestinations" :key="dest.id">
                        <td>{{ dest.name }}</td>
                        <td>{{ dest.tour_packages_count }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- -------------------------------------------------------------
             Package Statistics (active / inactive)
        -------------------------------------------------------------- -->
        <h4 class="mt-5">Package Statistics</h4>
        <div class="row g-3 mt-2">
            <div class="col-md-3"><div class="card p-3"><small>Active Packages</small><h3>{{ packageStats.active }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Inactive Packages</small><h3>{{ packageStats.inactive }}</h3></div></div>
        </div>

        <!-- -------------------------------------------------------------
             Packages Creation Chart (last 6 months)
        -------------------------------------------------------------- -->
        <h4 class="mt-5">Packages Created (Last 6 Months)</h4>
        <div class="mt-3">
            <PackageCreationChart :chart-data="packageCreationChart" />
        </div>

        <!-- -------------------------------------------------------------
             Recent Bookings (original section)
        -------------------------------------------------------------- -->
        <h4 class="mt-5">Recent Bookings</h4>
        <table class="table mt-2">
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
