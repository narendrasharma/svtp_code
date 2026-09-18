<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    user: Object,
    recentBookings: { type: Array, default: () => [] },
    vendorProfile: Object,
    vendorApplication: Object,
    kycStatus: String,
    kycDocumentsCount: Number,
    vendorPlan: { type: Object, default: null },
    vendorUsage: { type: Object, default: null },
    vendorPlans: { type: Array, default: () => [] },
});

const planForm = useForm({ vendor_plan_id: props.vendorProfile?.vendor_plan_id ?? '', note: '' });

function impersonate() {
    if (!confirm(`Impersonate ${props.user.name}? You will view the site as this user.`)) return;
    router.post(appUrl(`/admin/users/${props.user.id}/impersonate`));
}
function sendInvite() {
    if (!confirm(`Email an account-claim invitation to ${props.user.email}? The link expires in 72 hours.`)) return;
    router.post(appUrl(`/admin/users/${props.user.id}/invite`), {}, { preserveScroll: true });
}
function formatDate(d) {
    return d ? new Date(d).toLocaleString() : '—';
}
function assignPlan() {
    planForm.post(appUrl(`/admin/vendor-profiles/${props.vendorProfile.id}/plan`), { preserveScroll: true });
}
function overLimit(entry) {
    return entry && entry.limit !== null && entry.limit !== undefined && entry.usage > entry.limit;
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="mb-1">{{ user.name }}</h2>
                <p class="text-muted mb-1">{{ user.email }} · {{ user.phone || 'No phone' }} · <span class="badge" :class="{ admin: 'bg-danger', customer: 'bg-info text-dark', vendor: 'bg-success' }[user.role] || 'bg-secondary'">{{ user.role_label }}</span></p>
                <p class="small text-muted mb-0">Registered {{ formatDate(user.created_at) }} · Verified: {{ user.email_verified_at ? 'Yes' : 'No' }}</p>
            </div>
            <div class="d-flex gap-2">
                <button v-if="user.can_impersonate" type="button" class="btn btn-warning btn-sm" @click="impersonate"><i class="bi bi-eye me-1"></i>Impersonate</button>
                <span v-else class="badge bg-secondary align-self-center">Impersonation unavailable</span>
                <Link :href="appUrl(`/admin/messages/create?user_id=${user.id}`)" class="btn btn-outline-primary btn-sm"><i class="bi bi-chat-text me-1"></i>Message</Link>
                <button v-if="user.role === 'customer'" type="button" class="btn btn-outline-secondary btn-sm" @click="sendInvite"><i class="bi bi-envelope-plus me-1"></i>Invite</button>
                <Link :href="appUrl('/admin/users')" class="btn btn-outline-secondary btn-sm">Back to Users</Link>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card p-4 mb-4">
                    <h5 class="mb-3">Account</h5>
                    <dl class="row small mb-0">
                        <dt class="col-sm-4 text-muted">Name</dt><dd class="col-sm-8">{{ user.name }}</dd>
                        <dt class="col-sm-4 text-muted">Email</dt><dd class="col-sm-8">{{ user.email }}</dd>
                        <dt class="col-sm-4 text-muted">Phone</dt><dd class="col-sm-8">{{ user.phone || '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Role</dt><dd class="col-sm-8">{{ user.role_label }}</dd>
                        <dt class="col-sm-4 text-muted">Member since</dt><dd class="col-sm-8">{{ formatDate(user.created_at) }}</dd>
                        <dt class="col-sm-4 text-muted">Bookings</dt><dd class="col-sm-8">{{ user.bookings_count }}</dd>
                    </dl>
                    <p class="small text-muted mt-3 mb-0">Passwords, hashes and KYC storage paths are never exposed. This page is for support/inspection only.</p>
                </div>

                <div v-if="vendorProfile" class="card p-4 mb-4">
                    <h5 class="mb-3">Vendor Profile</h5>
                    <dl class="row small mb-0">
                        <dt class="col-sm-4 text-muted">Business</dt><dd class="col-sm-8">{{ vendorProfile.business_name }}</dd>
                        <dt class="col-sm-4 text-muted">Entity</dt><dd class="col-sm-8">{{ vendorProfile.entity_type }}</dd>
                        <dt class="col-sm-4 text-muted">Location</dt><dd class="col-sm-8">{{ vendorProfile.city }}, {{ vendorProfile.state }} ({{ vendorProfile.country_code }})</dd>
                        <dt class="col-sm-4 text-muted">Active</dt><dd class="col-sm-8"><span class="badge" :class="vendorProfile.is_active ? 'bg-success' : 'bg-secondary'">{{ vendorProfile.is_active ? 'Yes' : 'No' }}</span></dd>
                        <dt class="col-sm-4 text-muted">Approved</dt><dd class="col-sm-8">{{ formatDate(vendorProfile.approved_at) }}</dd>
                        <dt class="col-sm-4 text-muted">KYC</dt><dd class="col-sm-8">{{ vendorProfile.verification_status || kycStatus || '—' }}</dd>
                    </dl>
                    <div class="mt-3">
                        <Link :href="appUrl('/admin/vendor-applications')" class="btn btn-sm btn-outline-primary">Vendor Applications</Link>
                    </div>
                </div>

                <div v-if="vendorProfile" class="card p-4 mb-4">
                    <h5 class="mb-3">Vendor Plan</h5>
                    <dl class="row small mb-0">
                        <dt class="col-sm-4 text-muted">Current</dt><dd class="col-sm-8">{{ vendorPlan?.name ?? '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Tours</dt><dd class="col-sm-8">{{ vendorUsage?.tours?.usage ?? '—' }} / {{ vendorUsage?.tours?.limit ?? 'Unlimited' }} <span v-if="overLimit(vendorUsage?.tours)" class="badge bg-warning text-dark ms-1">Over limit — new submits blocked</span></dd>
                        <dt class="col-sm-4 text-muted">Coupons</dt><dd class="col-sm-8">{{ vendorUsage?.coupons?.usage ?? '—' }} / {{ vendorUsage?.coupons?.limit ?? 'Unlimited' }} <span v-if="overLimit(vendorUsage?.coupons)" class="badge bg-warning text-dark ms-1">Over limit</span></dd>
                    </dl>
                    <form class="row g-2 mt-3" @submit.prevent="assignPlan">
                        <div class="col-7"><label for="plan" class="form-label small">Change plan</label><select id="plan" v-model="planForm.vendor_plan_id" class="form-select form-select-sm"><option value="" disabled>Select plan</option><option v-for="plan in vendorPlans" :key="plan.id" :value="plan.id">{{ plan.name }}</option></select><small class="text-danger">{{ planForm.errors.vendor_plan_id }}</small></div>
                        <div class="col-5"><label for="plan-note" class="form-label small">Note</label><input id="plan-note" v-model="planForm.note" class="form-control form-control-sm" maxlength="500" /></div>
                        <div class="col-12"><button class="btn btn-sm btn-svtp" :disabled="planForm.processing">Assign Plan</button></div>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Downgrades keep existing content; only new submits/activations are blocked until usage falls below the limit.</p>
                </div>

                <div v-else-if="vendorApplication" class="card p-4 mb-4">
                    <h5 class="mb-3">Vendor Application</h5>
                    <dl class="row small mb-0">
                        <dt class="col-sm-4 text-muted">Business</dt><dd class="col-sm-8">{{ vendorApplication.business_name }}</dd>
                        <dt class="col-sm-4 text-muted">Entity</dt><dd class="col-sm-8">{{ vendorApplication.entity_type }}</dd>
                        <dt class="col-sm-4 text-muted">Status</dt><dd class="col-sm-8"><span class="badge bg-secondary">{{ vendorApplication.status }}</span></dd>
                        <dt class="col-sm-4 text-muted">KYC</dt><dd class="col-sm-8">{{ kycStatus || '—' }} ({{ kycDocumentsCount }} docs)</dd>
                        <dt class="col-sm-4 text-muted">Applied</dt><dd class="col-sm-8">{{ formatDate(vendorApplication.created_at) }}</dd>
                    </dl>
                    <div class="mt-3">
                        <Link :href="appUrl(`/admin/vendor-applications/${vendorApplication.id}`)" class="btn btn-sm btn-outline-primary">Review Application</Link>
                    </div>
                </div>

                <div v-else class="card p-4 mb-4">
                    <h5 class="mb-3">Vendor Status</h5>
                    <p class="small text-muted mb-0">No vendor application or profile. This is a customer account.</p>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card p-4">
                    <h5 class="mb-3">Recent Bookings ({{ user.bookings_count }})</h5>
                    <div v-if="recentBookings.length" class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Reference</th><th>Tour</th><th>Travel</th><th>Status</th></tr></thead>
                            <tbody>
                                <tr v-for="b in recentBookings" :key="b.id">
                                    <td class="small fw-semibold">{{ b.booking_reference_id }}</td>
                                    <td class="small">{{ b.package?.title ?? '—' }}</td>
                                    <td class="small text-nowrap">{{ b.travel_date ? new Date(b.travel_date).toLocaleDateString() : '—' }}</td>
                                    <td><span class="badge bg-secondary">{{ b.booking_status }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="small text-muted mb-0">No bookings yet.</p>
                    <p class="small text-muted mt-3 mb-0">Historical bookings are scoped to this user only — other users’ data is never shown.</p>
                </div>

                <div class="card p-4 mt-4">
                    <h6 class="mb-2">Support Actions</h6>
                    <p class="small text-muted">Use impersonation to view the site as this user. Vendor role is granted only via Vendor approval — not via user edit. No hard-delete for accounts with history.</p>
                    <div class="d-flex gap-2">
                        <button v-if="user.can_impersonate" type="button" class="btn btn-warning btn-sm" @click="impersonate"><i class="bi bi-eye me-1"></i>Impersonate {{ user.role_label }}</button>
                        <Link :href="appUrl('/admin/users')" class="btn btn-outline-secondary btn-sm">Back</Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
