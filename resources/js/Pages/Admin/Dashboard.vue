<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import AnalyticsChart from '../../Components/AnalyticsChart.vue';
import PackageCreationChart from '../../Components/PackageCreationChart.vue';
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';

defineProps({
    stats: Object,
    recentBookings: Array,
    recentTourPackages: Array,
    topDestinations: Array,
    packageStats: Object,
    packageCreationChart: Array,
    analytics: { type: Object, default: () => ({}) },
    analyticsRange: { type: Object, default: () => ({ preset: 'last30' }) },
    timeseries: { type: Array, default: () => [] },
    ops: { type: Object, default: () => ({}) },
});

function setPreset(preset) {
    router.get(appUrl('/admin/dashboard'), { preset }, { preserveScroll: true, preserveState: false });
}

// Phase 11.5A: tour-specific widgets hide when the tours module is off.
const toursEnabled = computed(() => {
    const modules = usePage().props.platformModules ?? [];
    const tours = modules.find((m) => m.key === 'tours');
    return tours ? !!tours.enabled : true;
});
</script>

<template>
    <AdminLayout>
        <h2 class="mb-4">Dashboard</h2>

        <!-- Existing statistic cards -->
        <div class="row g-3 mt-2">
            <div class="col-md-3"><div class="card p-3"><small>Total Bookings</small><h3>{{ stats.total_bookings }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Revenue</small><h3>₹{{ stats.revenue }}</h3></div></div>
            <div v-if="toursEnabled" class="col-md-3"><div class="card p-3"><small>Active Tours</small><h3>{{ stats.active_tours }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Pending Reviews</small><h3>{{ stats.pending_reviews }}</h3></div></div>

            <!-- New statistic cards -->
            <template v-if="toursEnabled">
            <div class="col-md-3"><div class="card p-3"><small>Total Tour Packages</small><h3>{{ stats.total_tour_packages }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Categories</small><h3>{{ stats.total_categories }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Tags</small><h3>{{ stats.total_tags }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Destinations</small><h3>{{ stats.total_destinations }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Places</small><h3>{{ stats.total_places }}</h3></div></div>
            </template>
            <div class="col-md-3"><div class="card p-3"><small>Total Users</small><h3>{{ stats.total_users }}</h3></div></div>

            <div v-if="stats.total_vendors !== undefined" class="col-md-3">
                <div class="card p-3"><small>Total Vendors</small><h3>{{ stats.total_vendors }}</h3></div>
            </div>
        </div>

        <!-- -------------------------------------------------------------
             Marketplace Analytics (Phase 11)
        -------------------------------------------------------------- -->
        <h4 class="mt-5">Marketplace Analytics</h4>
        <p class="small text-muted mb-2">Gross Booking Value is customer-paid totals — not platform income. Platform income is Commission only.</p>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button v-for="preset in [['today', 'Today'], ['last7', 'Last 7 days'], ['last30', 'Last 30 days'], ['month', 'This month'], ['all', 'All time']]" :key="preset[0]" type="button" class="btn btn-sm" :class="analyticsRange.preset === preset[0] ? 'btn-svtp' : 'btn-outline-secondary'" @click="setPreset(preset[0])">{{ preset[1] }}</button>
            <span class="small text-muted align-self-center">Range: {{ analyticsRange.from ?? '—' }} → {{ analyticsRange.to ?? '—' }}</span>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3"><div class="card p-3"><small>Total Customers</small><h3>{{ analytics.customers }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Vendors</small><h3>{{ analytics.vendors_total }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Approved Vendors</small><h3>{{ analytics.vendors_approved }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Pending Applications</small><h3>{{ analytics.pending_applications }}</h3></div></div>
            <div v-if="toursEnabled" class="col-md-3"><div class="card p-3"><small>Active Tours</small><h3>{{ analytics.active_tours }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Bookings</small><h3>{{ analytics.bookings_total }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Paid Bookings</small><h3>{{ analytics.bookings_paid }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Gross Booking Value</small><h3>₹{{ analytics.gross_booking_value }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Platform Commission</small><h3>₹{{ analytics.platform_commission }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Vendor Earnings</small><h3>₹{{ analytics.vendor_earnings }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Refunded Amount</small><h3>₹{{ analytics.refunded_amount }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Pending Withdrawals</small><h3>{{ analytics.pending_withdrawals }} (₹{{ analytics.pending_withdrawal_amount }})</h3></div></div>
        </div>
        <div class="card p-3 p-md-4 mt-3">
            <h5 class="mb-3">Bookings &amp; value over time</h5>
            <AnalyticsChart :points="timeseries" />
        </div>
        <div v-if="toursEnabled" class="row g-3 mt-3">
            <div class="col-lg-6">
                <div class="card p-3 p-md-4 h-100">
                    <h5 class="mb-3">Top Tours by paid bookings</h5>
                    <div class="table-responsive"><table class="table mb-0 small"><thead><tr><th>Tour</th><th class="text-end">Bookings</th><th class="text-end">Value</th></tr></thead><tbody><tr v-for="row in analytics.top_tours ?? []" :key="row.package_id"><td>{{ row.title }}</td><td class="text-end">{{ row.paid_bookings }}</td><td class="text-end">₹{{ row.paid_value }}</td></tr></tbody></table></div>
                    <p v-if="!(analytics.top_tours ?? []).length" class="text-muted small mb-0">No paid bookings in range.</p>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card p-3 p-md-4 h-100">
                    <h5 class="mb-3">Top Vendors by paid booking value</h5>
                    <div class="table-responsive"><table class="table mb-0 small"><thead><tr><th>Vendor</th><th class="text-end">Bookings</th><th class="text-end">Value</th></tr></thead><tbody><tr v-for="row in analytics.top_vendors ?? []" :key="row.vendor_profile_id"><td>{{ row.business_name }}</td><td class="text-end">{{ row.paid_bookings }}</td><td class="text-end">₹{{ row.paid_value }}</td></tr></tbody></table></div>
                    <p v-if="!(analytics.top_vendors ?? []).length" class="text-muted small mb-0">No vendor bookings in range.</p>
                </div>
            </div>
        </div>
        <p class="small text-muted mt-3 mb-0">Coupons in range: {{ analytics.coupons?.redemptions ?? 0 }} redemptions · ₹{{ analytics.coupons?.discount_granted ?? '0.00' }} discount granted.</p>

        <!-- -------------------------------------------------------------
             Operations Command Center (Phase 11.5D)
        -------------------------------------------------------------- -->
        <template v-if="ops && ops.kpis">
        <h4 class="mt-5">Operations</h4>
        <p class="small text-muted mb-2">Outstanding balance is derived (total − collected + processed refunds), never stored. Gross Booking Value is customer-paid totals — not platform income.</p>
        <div class="row g-3 mt-1">
            <div class="col-md-3"><div class="card p-3"><small>Customers</small><h3>{{ ops.kpis.customers }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Total Bookings</small><h3>{{ ops.kpis.bookings_total }}</h3></div></div>
            <div v-if="ops.finance_visible" class="col-md-3"><div class="card p-3"><small>Gross Booking Value</small><h3>₹{{ ops.kpis.gross_booking_value }}</h3></div></div>
            <div v-if="ops.finance_visible" class="col-md-3"><div class="card p-3"><small>Platform Commission</small><h3>₹{{ ops.kpis.platform_commission }}</h3></div></div>
            <div v-if="ops.finance_visible" class="col-md-3"><div class="card p-3"><small>Vendor Earnings</small><h3>₹{{ ops.kpis.vendor_earnings }}</h3></div></div>
            <div v-if="ops.finance_visible" class="col-md-3"><div class="card p-3"><small>Refunded</small><h3>₹{{ ops.kpis.refunded_amount }}</h3></div></div>
            <div v-if="ops.finance_visible" class="col-md-3"><div class="card p-3"><small>Outstanding Balance</small><h3>₹{{ ops.kpis.outstanding_balance }}</h3></div></div>
            <div v-if="ops.finance_visible" class="col-md-3"><div class="card p-3"><small>Collected (range)</small><h3>₹{{ ops.kpis.payments_collected }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Leads (won / total)</small><h3>{{ ops.kpis.leads_won }} / {{ ops.kpis.leads_total }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Lead Conversion</small><h3>{{ ops.kpis.lead_conversion_rate }}%</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Open Support Tickets</small><h3>{{ ops.kpis.open_support_tickets }}</h3></div></div>
            <div class="col-md-3"><div class="card p-3"><small>Pending Withdrawals</small><h3>{{ ops.kpis.pending_withdrawals }}</h3></div></div>
        </div>

        <h5 class="mt-4">Needs Attention</h5>
        <div class="list-group mb-2">
            <a v-for="item in ops.needs_attention" :key="item.key" :href="item.url" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                <span>{{ item.label }}</span>
                <span class="badge" :class="item.count > 0 ? 'bg-warning text-dark' : 'bg-success'">{{ item.count }}</span>
            </a>
        </div>
        <p v-if="!(ops.needs_attention ?? []).length" class="small text-muted">Nothing needs attention right now.</p>

        <h5 class="mt-4">Recent Activity</h5>
        <div class="table-responsive">
            <table class="table small mb-0">
                <tbody>
                    <tr v-for="a in ops.recent_activity" :key="a.id">
                        <td class="text-nowrap text-muted">{{ new Date(a.created_at).toLocaleString('en-IN') }}</td>
                        <td>{{ a.actor?.name ?? 'system' }}</td>
                        <td><span class="badge bg-light text-dark">{{ a.event }}</span></td>
                        <td>{{ a.description ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!(ops.recent_activity ?? []).length" class="small text-muted">No recent activity.</p>
        </template>

        <!-- -------------------------------------------------------------
             Recent Tour Packages (tours module only)
        -------------------------------------------------------------- -->
        <template v-if="toursEnabled">
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
                            <span v-if="pkg.duration_days !== undefined && pkg.duration_nights !== undefined">
                                {{ pkg.duration_days }}d {{ pkg.duration_nights }}n
                            </span>
                            <span v-else-if="pkg.duration_days !== undefined">
                                {{ pkg.duration_days }}d
                            </span>
                            <span v-else-if="pkg.duration_nights !== undefined">
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
                            <Link :href="appUrl(`/admin/packages/${pkg.id}/edit`)" class="btn btn-sm btn-primary">Edit</Link>
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
             Packages Creation Chart (last 6 months, tours module only)
        -------------------------------------------------------------- -->
        <h4 class="mt-5">Packages Created (Last 6 Months)</h4>
        <div class="mt-3">
            <PackageCreationChart :chart-data="packageCreationChart" />
        </div>
        </template>

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
