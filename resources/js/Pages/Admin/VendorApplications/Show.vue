<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    application: Object,
    verification: Object,
    documents: Array,
    summary: Object,
    requirements: Array,
    catalog: Object,
    canApprove: Boolean,
    requireKycBeforeApproval: Boolean,
    kycIncomplete: Boolean,
});

const approveForm = useForm({ admin_note: '', allow_incomplete_kyc: true });
const rejectForm = useForm({ rejection_reason: '', admin_note: '' });
const resubmitForm = useForm({ rejection_reason: '', admin_note: '' });
const verifyForm = useForm({ review_note: '' });

const rejectReason = ref('');
const showReject = ref(false);
const showResubmit = ref(false);

function approve() {
    const msg = props.kycIncomplete
        ? 'KYC verification is incomplete. Approve vendor with incomplete KYC? The vendor will be approved but KYC will remain pending.'
        : 'Approve this vendor application? Vendor profile will be created and role assigned.';
    if (!confirm(msg)) return;
    approveForm.post(appUrl(`/admin/vendor-applications/${props.application.id}/approve`), { preserveScroll: true });
}
function reject() {
    rejectForm.post(appUrl(`/admin/vendor-applications/${props.application.id}/reject`), { preserveScroll: true });
}
function requestResubmission() {
    resubmitForm.post(appUrl(`/admin/vendor-applications/${props.application.id}/request-resubmission`), { preserveScroll: true });
}
function verifyKyc() {
    verifyForm.post(appUrl(`/admin/vendor-applications/${props.application.id}/verify-kyc`), { preserveScroll: true });
}
function verifyDocument(doc) {
    router.post(appUrl(`/admin/vendor-documents/${doc.id}/verify`), {}, { preserveScroll: true });
}
function rejectDocument(doc) {
    const reason = prompt('Rejection reason for ' + doc.label + ':');
    if (!reason) return;
    router.post(appUrl(`/admin/vendor-documents/${doc.id}/reject`), { rejection_reason: reason }, { preserveScroll: true });
}
function docBadge(status) {
    return {
        pending: 'bg-warning text-dark',
        verified: 'bg-success',
        rejected: 'bg-danger',
    }[status] ?? 'bg-secondary';
}
function impersonate() {
    if (!confirm(`Impersonate ${props.application.user.name}? You will view the site as this user.`)) return;
    router.post(appUrl(`/admin/users/${props.application.user.id}/impersonate`));
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="mb-1">{{ application.business_name }}</h2>
                <p class="text-muted mb-1">Applicant: {{ application.user?.name }} ({{ application.user?.email }}) · {{ application.entity_type }} · {{ application.country_code }}</p>
                <div class="d-flex gap-2">
                    <span class="badge" :class="application.status === 'pending' ? 'bg-warning text-dark' : application.status === 'approved' ? 'bg-success' : application.status === 'rejected' ? 'bg-danger' : 'bg-info text-dark'">{{ application.status }}</span>
                    <span class="badge" :class="verification.status === 'verified' ? 'bg-success' : verification.status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary'">{{ verification.status }}</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button v-if="application.user && !application.user.is_admin" type="button" class="btn btn-sm btn-outline-warning" @click="impersonate"><i class="bi bi-eye me-1"></i>Impersonate</button>
                <Link :href="appUrl('/admin/vendor-applications')" class="btn btn-sm btn-outline-secondary">Back</Link>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4 mb-4">
                    <h5>Business Details</h5>
                    <dl class="row small mb-0">
                        <dt class="col-sm-3 text-muted">Business</dt><dd class="col-sm-9">{{ application.business_name }}</dd>
                        <dt class="col-sm-3 text-muted">Entity Type</dt><dd class="col-sm-9">{{ application.entity_type }}</dd>
                        <dt class="col-sm-3 text-muted">Contact</dt><dd class="col-sm-9">{{ application.phone }} · {{ application.email }}</dd>
                        <dt class="col-sm-3 text-muted">Address</dt><dd class="col-sm-9">{{ application.address }}, {{ application.city }}, {{ application.state }} {{ application.postcode }} ({{ application.country_code }})</dd>
                        <dt v-if="application.website" class="col-sm-3 text-muted">Website</dt><dd v-if="application.website" class="col-sm-9"><a :href="application.website" target="_blank">{{ application.website }}</a></dd>
                        <dt v-if="application.business_description" class="col-sm-3 text-muted">Description</dt><dd v-if="application.business_description" class="col-sm-9">{{ application.business_description }}</dd>
                        <dt class="col-sm-3 text-muted">Consent</dt><dd class="col-sm-9">Version {{ application.consent_policy_version }} at {{ application.consent_accepted_at ? new Date(application.consent_accepted_at).toLocaleString() : '—' }}</dd>
                        <dt class="col-sm-3 text-muted">Applied</dt><dd class="col-sm-9">{{ new Date(application.created_at).toLocaleString() }}</dd>
                        <dt v-if="application.rejection_reason" class="col-sm-3 text-muted">Rejection Reason</dt><dd v-if="application.rejection_reason" class="col-sm-9 text-danger">{{ application.rejection_reason }}</dd>
                    </dl>
                </div>

                <div class="card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">KYC Verification</h5>
                        <span class="badge fs-6" :class="verification.status === 'verified' ? 'bg-success' : 'bg-warning text-dark'">{{ verification.status }}</span>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-3"><div class="text-center p-2 border rounded"><strong>{{ summary.verified }}</strong><div class="small text-muted">Verified</div></div></div>
                        <div class="col-3"><div class="text-center p-2 border rounded"><strong>{{ summary.submitted }}</strong><div class="small text-muted">Submitted</div></div></div>
                        <div class="col-3"><div class="text-center p-2 border rounded"><strong>{{ summary.required }}</strong><div class="small text-muted">Required groups</div></div></div>
                        <div class="col-3"><div class="text-center p-2 border rounded"><strong>{{ summary.rejected }}</strong><div class="small text-muted">Rejected</div></div></div>
                    </div>
                    <div v-if="summary.is_complete" class="alert alert-success py-2 small">All required document groups satisfied.</div>
                    <div v-else class="alert alert-warning py-2 small">
                        <strong>KYC verification is incomplete.</strong> You can still approve this vendor manually.
                        <div class="mt-1 small">
                            Verified: {{ summary.verified }} · Submitted: {{ summary.submitted }} · Required groups: {{ summary.required }} · Missing: {{ summary.missing.length }}
                        </div>
                    </div>
                    <p class="small text-muted">Requirements for {{ verification.country_code }} / {{ verification.entity_type }}:</p>
                    <ul class="small mb-3">
                        <li v-for="(req, i) in requirements" :key="i" :class="summary.groups[i]?.satisfied ? 'text-success' : 'text-danger'">
                            {{ Array.isArray(req) ? req.join(' / ') + ' (one of)' : req }} — {{ summary.groups[i]?.satisfied ? 'Satisfied' : 'Missing' }}
                        </li>
                    </ul>
                    <div v-if="verification.status !== 'verified'" class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-success" :disabled="!summary.is_complete" @click="verifyKyc">Mark KYC Verified</button>
                        <span v-if="!summary.is_complete" class="small text-muted align-self-center">Verify required docs first</span>
                    </div>
                </div>

                <div class="card p-4 mb-4">
                    <h5>Documents</h5>
                    <div v-if="!documents.length" class="text-muted small py-3">No documents uploaded.</div>
                    <div v-for="doc in documents" :key="doc.id" class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between gap-3">
                            <div>
                                <strong>{{ doc.label }}</strong> <span class="badge ms-2" :class="docBadge(doc.status)">{{ doc.status }}</span>
                                <div class="small text-muted">{{ doc.original_filename }} · {{ (doc.file_size/1024).toFixed(1) }} KB · uploaded {{ new Date(doc.created_at).toLocaleDateString() }}</div>
                                <div v-if="doc.document_number_masked" class="small">Masked: {{ doc.document_number_masked }}</div>
                                <div v-if="doc.rejection_reason" class="small text-danger">Rejected: {{ doc.rejection_reason }}</div>
                                <div v-if="doc.reviewed_by" class="small text-muted">Reviewed by {{ doc.reviewed_by }} {{ doc.reviewed_at ? new Date(doc.reviewed_at).toLocaleDateString() : '' }}</div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <a :href="appUrl(`/admin/vendor-documents/${doc.id}/download`)" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>View</a>
                                <button v-if="doc.status !== 'verified'" type="button" class="btn btn-sm btn-success" @click="verifyDocument(doc)">Verify</button>
                                <button v-if="doc.status !== 'rejected'" type="button" class="btn btn-sm btn-outline-danger" @click="rejectDocument(doc)">Reject</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card p-4 sticky-top" style="top: 20px;">
                    <h6>Admin Actions</h6>
                    <div v-if="application.status === 'pending' || application.status === 'resubmission_requested'">
                        <div class="mb-3">
                            <label class="form-label small">Admin Note (optional)</label>
                            <input v-model="approveForm.admin_note" class="form-control form-control-sm" placeholder="Internal note" />
                        </div>
                        <button type="button" class="btn btn-success w-100 mb-2" @click="approve">Approve Vendor</button>
                        <p v-if="kycIncomplete" class="small text-warning">KYC incomplete — approval will create an active vendor with pending verification. You can verify documents later.</p>
                        <p v-else class="small text-success">KYC complete — ready to approve.</p>

                        <hr />

                        <button type="button" class="btn btn-outline-danger w-100 mb-2" @click="showReject = !showReject">Reject</button>
                        <div v-if="showReject" class="border p-3 rounded mb-3">
                            <label class="form-label small">Rejection Reason *</label>
                            <textarea v-model="rejectForm.rejection_reason" class="form-control form-control-sm" rows="2"></textarea>
                            <input v-model="rejectForm.admin_note" class="form-control form-control-sm mt-2" placeholder="Admin note (optional)" />
                            <button type="button" class="btn btn-sm btn-danger mt-2 w-100" @click="reject">Confirm Reject</button>
                        </div>

                        <button type="button" class="btn btn-outline-info w-100 mb-2" @click="showResubmit = !showResubmit">Request Resubmission</button>
                        <div v-if="showResubmit" class="border p-3 rounded">
                            <label class="form-label small">Reason *</label>
                            <textarea v-model="resubmitForm.rejection_reason" class="form-control form-control-sm" rows="2"></textarea>
                            <button type="button" class="btn btn-sm btn-info mt-2 w-100" @click="requestResubmission">Send Request</button>
                        </div>
                    </div>
                    <div v-else>
                        <p class="small text-muted">Application is {{ application.status }}. No actions available.</p>
                        <p v-if="application.reviewed_at" class="small text-muted">Reviewed {{ new Date(application.reviewed_at).toLocaleString() }}</p>
                    </div>

                    <hr />
                    <p class="small text-muted">KYC documents are stored privately on the <code>vendor_kyc</code> disk. Access is policy-protected. Masked Aadhaar displays only last 4 digits; full numbers are never stored or rendered.</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
