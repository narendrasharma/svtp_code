<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import { ref } from 'vue';

const props = defineProps({
    package: Object,
    vendorKycStatus: String,
    moderationStatuses: Array,
});

const approveForm = useForm({ note: '' });
const changesForm = useForm({ note: '' });
const rejectForm = useForm({ note: '' });
const showChanges = ref(false);
const showReject = ref(false);

function approve() {
    if (!confirm('Approve this tour? It will become publicly visible.')) return;
    approveForm.post(appUrl(`/admin/packages/${props.package.id}/approve`));
}
function requestChanges() {
    if (!changesForm.note) { alert('Note required'); return; }
    changesForm.post(appUrl(`/admin/packages/${props.package.id}/request-changes`));
}
function reject() {
    if (!rejectForm.note) { alert('Note required'); return; }
    rejectForm.post(appUrl(`/admin/packages/${props.package.id}/reject`));
}
function badge(status) {
    return {
        draft: 'bg-secondary',
        pending_review: 'bg-warning text-dark',
        approved: 'bg-success',
        changes_requested: 'bg-info text-dark',
        rejected: 'bg-danger',
    }[status] ?? 'bg-secondary';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h2 class="mb-1">{{ package.title }}</h2>
                <p class="text-muted small mb-1">{{ package.slug }} · {{ package.city?.name }} · {{ package.duration_days }}D</p>
                <span class="badge" :class="badge(package.moderation_status)">{{ package.moderation_status }}</span>
                <span class="badge ms-1" :class="package.is_active ? 'bg-success' : 'bg-secondary'">{{ package.is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <Link :href="appUrl('/admin/packages')" class="btn btn-outline-secondary btn-sm">Back</Link>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4 mb-4">
                    <h5>Tour Details</h5>
                    <div class="row g-3 small">
                        <div class="col-md-6"><strong>Price:</strong> ₹{{ package.discounted_price || package.price }}</div>
                        <div class="col-md-6"><strong>Category:</strong> {{ package.category?.name || '—' }}</div>
                        <div class="col-12"><strong>Overview:</strong> {{ package.overview || '—' }}</div>
                        <div class="col-12" v-if="package.cover_image"><img :src="package.cover_image" alt="" style="max-width: 240px; border-radius: 8px;" /></div>
                        <div class="col-12" v-if="package.gallery?.length">
                            <strong>Gallery:</strong>
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                <img v-for="(img,i) in package.gallery" :key="i" :src="img" alt="" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card p-4 mb-4">
                    <h5>Vendor</h5>
                    <div v-if="package.vendor_profile">
                        <p class="small mb-1"><strong>{{ package.vendor_profile.business_name }}</strong> ({{ package.vendor_profile.entity_type }})</p>
                        <p class="small text-muted mb-1">{{ package.vendor_profile.city }}, {{ package.vendor_profile.country_code }} · KYC: {{ vendorKycStatus || package.vendor_profile.verification_status || '—' }}</p>
                        <Link v-if="package.vendor_profile.id" :href="appUrl(`/admin/users/${package.vendor_profile.user_id}`)" class="btn btn-sm btn-outline-primary">View Vendor User</Link>
                    </div>
                    <p v-else class="small text-muted">Admin-created tour (no vendor)</p>
                    <div class="small text-muted mt-2">Created by: {{ package.creator?.name || '—' }} · Submitted: {{ package.submitted_at ? new Date(package.submitted_at).toLocaleString() : '—' }}</div>
                    <div class="small text-muted">Reviewed by: {{ package.reviewer?.name || '—' }} · {{ package.reviewed_at ? new Date(package.reviewed_at).toLocaleString() : '—' }}</div>
                    <div v-if="package.review_note" class="alert alert-info small mt-2">Review note: {{ package.review_note }}</div>
                </div>

                <div class="card p-4">
                    <h5>Moderation History</h5>
                    <div v-if="package.moderation_histories?.length">
                        <div v-for="h in package.moderation_histories" :key="h.id" class="small border-bottom py-2">
                            <span class="badge bg-secondary me-1">{{ h.from_status || '—' }} → {{ h.to_status }}</span>
                            by {{ h.changer?.name || 'system' }} at {{ new Date(h.created_at).toLocaleString() }}
                            <div v-if="h.note" class="text-muted">{{ h.note }}</div>
                        </div>
                    </div>
                    <p v-else class="small text-muted">No history yet.</p>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card p-4 sticky-top" style="top: 20px;">
                    <h6>Moderation</h6>
                    <p class="small text-muted">Current: <strong>{{ package.moderation_status }}</strong> · Public: {{ package.is_active && package.moderation_status === 'approved' ? 'Visible' : 'Hidden' }}</p>
                    <div v-if="package.moderation_status === 'pending_review'" class="d-grid gap-2">
                        <button class="btn btn-success btn-sm" @click="approve">Approve</button>
                        <button class="btn btn-outline-info btn-sm" @click="showChanges = !showChanges">Request Changes</button>
                        <div v-if="showChanges" class="border rounded p-2">
                            <textarea v-model="changesForm.note" placeholder="Reason" class="form-control form-control-sm mb-2" rows="3"></textarea>
                            <button class="btn btn-sm btn-info w-100" @click="requestChanges">Send Request</button>
                        </div>
                        <button class="btn btn-outline-danger btn-sm" @click="showReject = !showReject">Reject</button>
                        <div v-if="showReject" class="border rounded p-2">
                            <textarea v-model="rejectForm.note" placeholder="Reason" class="form-control form-control-sm mb-2" rows="3"></textarea>
                            <button class="btn btn-sm btn-danger w-100" @click="reject">Confirm Reject</button>
                        </div>
                    </div>
                    <div v-else-if="package.moderation_status === 'approved'" class="d-grid gap-2">
                        <button class="btn btn-outline-info btn-sm" @click="showChanges = !showChanges">Request Changes</button>
                        <div v-if="showChanges" class="border rounded p-2">
                            <textarea v-model="changesForm.note" placeholder="Reason" class="form-control form-control-sm mb-2" rows="3"></textarea>
                            <button class="btn btn-sm btn-info w-100" @click="requestChanges">Send Request</button>
                        </div>
                        <button class="btn btn-outline-danger btn-sm" @click="showReject = !showReject">Reject</button>
                        <div v-if="showReject" class="border rounded p-2">
                            <textarea v-model="rejectForm.note" placeholder="Reason" class="form-control form-control-sm mb-2" rows="3"></textarea>
                            <button class="btn btn-sm btn-danger w-100" @click="reject">Confirm Reject</button>
                        </div>
                    </div>
                    <div v-else class="small text-muted">No actions for {{ package.moderation_status }}</div>
                    <hr />
                    <Link :href="appUrl(`/admin/packages/${package.id}/edit`)" class="btn btn-outline-secondary btn-sm w-100">Edit Tour</Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
