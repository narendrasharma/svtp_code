<script setup>
import { Link } from '@inertiajs/vue3';
import VendorLayout from '../../Layouts/VendorLayout.vue';
import { appUrl } from '../../appUrl';

defineProps({
    profile: Object,
    verification: Object,
    kycSummary: Object,
    metrics: { type: Object, default: null },
    metricsRange: { type: Object, default: () => ({ preset: 'last30' }) },
    extras: { type: Object, default: null },
    plan: { type: Object, default: null },
    usage: { type: Object, default: null },
    storefrontUrl: { type: String, default: null },
});

function usageText(entry) {
    if (!entry) return '—';
    return entry.limit === null || entry.limit === undefined ? `${entry.usage} (unlimited)` : `${entry.usage} of ${entry.limit}`;
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Vendor Dashboard</h2>
                <p class="text-muted mb-0">Welcome, {{ profile.business_name }}</p>
            </div>
            <span class="badge bg-success fs-6">Vendor Active</span>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <h6 class="text-muted small">Business</h6>
                    <h5>{{ profile.business_name }}</h5>
                    <p class="small text-muted mb-1">{{ profile.entity_type }} · {{ profile.country_code }}</p>
                    <p class="small mb-1"><i class="bi bi-geo-alt me-1"></i>{{ profile.city }}, {{ profile.state }}</p>
                    <p class="small mb-1"><i class="bi bi-telephone me-1"></i>{{ profile.phone }}</p>
                    <p class="small mb-3"><i class="bi bi-envelope me-1"></i>{{ profile.email }}</p>
                    <Link :href="appUrl('/vendor/profile')" class="btn btn-sm btn-outline-primary">View Profile</Link>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <h6 class="text-muted small">Account Status</h6>
                    <p><span class="badge bg-success">Approved</span> since {{ profile.approved_at ? new Date(profile.approved_at).toLocaleDateString() : '—' }}</p>
                    <p class="small text-muted">Your vendor account is active. You can manage your profile and will be able to list tours, taxis and hotels in future phases.</p>
                    <Link :href="appUrl('/vendor/profile/edit')" class="btn btn-sm btn-outline-secondary">Edit Profile</Link>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <h6 class="text-muted small">KYC Verification</h6>
                    <p class="mb-1">Status: <span class="badge" :class="verification?.status === 'verified' ? 'bg-success' : 'bg-warning text-dark'">{{ verification?.status ?? 'unknown' }}</span></p>
                    <div v-if="kycSummary" class="small text-muted">
                        <div>Verified docs: {{ kycSummary.verified }} / {{ kycSummary.required }}</div>
                        <div>Rejected: {{ kycSummary.rejected }}</div>
                        <div v-if="kycSummary.is_complete" class="text-success">All requirements satisfied</div>
                        <div v-else class="text-warning">Some requirements pending — you can continue uploading documents.</div>
                    </div>
                    <Link :href="appUrl('/vendor/application')" class="btn btn-sm btn-outline-secondary mt-3">View Application</Link>
                    <Link v-if="verification?.status !== 'verified'" :href="appUrl('/vendor/application')" class="btn btn-sm btn-warning ms-2 mt-3">Complete KYC</Link>
                </div>
            </div>
        </div>

        <div class="card p-4 mt-4">
            <h5>Next Steps</h5>
            <p class="text-muted small">Phase 5 will add Tour management for vendors. No earnings or payouts are available yet — they will appear here once those modules ship. Your verification stays with your vendor account and will apply to future Taxi/Hotel offerings as well.</p>
            <div class="d-flex gap-2">
                <Link :href="appUrl('/vendor/application')" class="btn btn-sm btn-svtp">Application & KYC</Link>
                <Link :href="appUrl('/packages')" class="btn btn-sm btn-outline-secondary">Browse Tours</Link>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <h6 class="text-muted small">Current Plan</h6>
                    <h5>{{ plan?.name ?? '—' }}</h5>
                    <p class="small text-muted mb-1">Tours: {{ usageText(usage?.tours) }}</p>
                    <p class="small text-muted mb-3">Coupons: {{ usageText(usage?.coupons) }}</p>
                    <p class="small text-muted mb-0">Plan changes are handled by the platform team — billing is not live yet.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <h6 class="text-muted small">My Performance</h6>
                    <p class="small mb-1">Bookings: <strong>{{ metrics?.bookings_total ?? '—' }}</strong> (paid: {{ metrics?.bookings_paid ?? '—' }})</p>
                    <p class="small mb-1">Paid booking value: <strong>₹{{ metrics?.paid_booking_value ?? '0.00' }}</strong></p>
                    <p class="small mb-1">Recorded earnings: <strong>₹{{ metrics?.recorded_earnings ?? '0.00' }}</strong></p>
                    <p class="small mb-1">Refunded: ₹{{ metrics?.refunded_amount ?? '0.00' }}</p>
                    <p class="small mb-0">Tours: {{ metrics?.tours_active ?? 0 }} active / {{ metrics?.tours_count ?? 0 }} total · Rating: {{ metrics?.average_rating ?? '—' }} ({{ metrics?.reviews_count ?? 0 }})</p>
                    <template v-if="extras">
                        <hr class="my-2" />
                        <p class="small mb-1">Available payout: <strong>₹{{ extras.available_payout }}</strong> · Paid out: ₹{{ extras.paid_out }}</p>
                        <p class="small mb-1">Open support tickets: <strong>{{ extras.open_support_tickets }}</strong></p>
                        <p class="small mb-0">Trips in next 7 days: <strong>{{ extras.upcoming_travel }}</strong></p>
                    </template>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <h6 class="text-muted small">Public Storefront</h6>
                    <a v-if="storefrontUrl" :href="storefrontUrl" class="btn btn-sm btn-outline-primary mb-2">View My Storefront</a>
                    <p v-else class="small text-muted">Storefront unavailable.</p>
                    <p class="small text-muted mb-0">Only approved, active tours appear publicly. The Verified badge shows only after KYC verification.</p>
                    <Link :href="appUrl('/vendor/profile/edit')" class="btn btn-sm btn-outline-secondary mt-2">Edit Storefront Details</Link>
                </div>
            </div>
        </div>
    </VendorLayout>
</template>
