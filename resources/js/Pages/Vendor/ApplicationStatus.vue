<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    application: Object,
    verification: Object,
    documents: { type: Array, default: () => [] },
    requirements: { type: Array, default: () => [] },
    summary: Object,
    catalog: Object,
    documentTypes: { type: Array, default: () => [] },
    entityTypes: Array,
});

const uploadForm = useForm({
    document_type: '',
    document_file: null,
    document_number_masked: '',
});

const selectedFile = ref(null);

function handleFile(e) {
    const file = e.target.files?.[0] ?? null;
    selectedFile.value = file;
    uploadForm.document_file = file;
}

function uploadDocument() {
    uploadForm.post(appUrl('/vendor/documents'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset('document_file', 'document_number_masked');
            selectedFile.value = null;
            document.getElementById('doc-file-input').value = '';
        },
    });
}

function statusBadge(status) {
    return {
        pending: 'bg-warning text-dark',
        approved: 'bg-success',
        rejected: 'bg-danger',
        resubmission_requested: 'bg-info text-dark',
        not_started: 'bg-secondary',
        under_review: 'bg-info text-dark',
        verified: 'bg-success',
        needs_resubmission: 'bg-info text-dark',
    }[status] ?? 'bg-secondary';
}

function docBadge(status) {
    return {
        pending: 'bg-warning text-dark',
        verified: 'bg-success',
        rejected: 'bg-danger',
    }[status] ?? 'bg-secondary';
}

function docLabel(type) {
    const found = props.documentTypes.find(d => d.value === type);
    return found ? found.label : type;
}

function requirementLabel(req) {
    if (Array.isArray(req)) {
        return req.map(docLabel).join(' OR ') + ' (choose one)';
    }
    return docLabel(req);
}

const groupedCatalog = computed(() => {
    const groups = { identity: [], address_proof: [], business: [], bank: [], industry: [] };
    props.documentTypes.forEach(d => {
        if (groups[d.category]) groups[d.category].push(d);
        else groups[d.category] = [d];
    });
    return groups;
});

const canUpload = computed(() => {
    if (['pending', 'resubmission_requested'].includes(props.application.status)) return true;
    if (props.application.status === 'approved' && props.verification.status !== 'verified') return true;
    return false;
});
</script>

<template>
    <AppLayout>
        <div class="container py-5">
            <p class="section-eyebrow">Vendor Application</p>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h1 class="section-title mb-1">{{ application.business_name }}</h1>
                    <p class="text-muted mb-0">Submitted {{ new Date(application.created_at).toLocaleDateString() }} · {{ application.entity_type }}</p>
                </div>
                <span class="badge fs-6" :class="statusBadge(application.status)">{{ application.status }}</span>
            </div>

            <div v-if="application.rejection_reason" class="alert alert-danger">
                <strong>Admin note:</strong> {{ application.rejection_reason }}
                <div v-if="application.status === 'resubmission_requested'" class="mt-2">
                    <Link :href="appUrl('/vendor/application/edit')" class="btn btn-sm btn-outline-danger">Resubmit Application</Link>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <!-- Application details -->
                    <div class="card p-4 mb-4">
                        <h5 class="mb-3">Application Details</h5>
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-muted">Business</dt><dd class="col-sm-8">{{ application.business_name }} ({{ application.entity_type }})</dd>
                            <dt class="col-sm-4 text-muted">Contact</dt><dd class="col-sm-8">{{ application.phone }} · {{ application.email }}</dd>
                            <dt class="col-sm-4 text-muted">Address</dt><dd class="col-sm-8">{{ application.address }}, {{ application.city }}, {{ application.state }} {{ application.postcode }} ({{ application.country_code }})</dd>
                            <dt v-if="application.website" class="col-sm-4 text-muted">Website</dt><dd v-if="application.website" class="col-sm-8"><a :href="application.website" target="_blank">{{ application.website }}</a></dd>
                            <dt v-if="application.business_description" class="col-sm-4 text-muted">Description</dt><dd v-if="application.business_description" class="col-sm-8">{{ application.business_description }}</dd>
                            <dt class="col-sm-4 text-muted">Consent</dt><dd class="col-sm-8">{{ application.consent_policy_version }} at {{ application.consent_accepted_at ? new Date(application.consent_accepted_at).toLocaleString() : '—' }}</dd>
                        </dl>
                        <div v-if="application.status === 'rejected' || application.status === 'resubmission_requested'" class="mt-3">
                            <Link :href="appUrl('/vendor/application/edit')" class="btn btn-sm btn-outline-primary">Edit & Resubmit</Link>
                        </div>
                    </div>

                    <!-- Verification summary -->
                    <div class="card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">KYC Verification</h5>
                            <span class="badge" :class="statusBadge(verification.status)">{{ verification.status }}</span>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-4"><div class="text-center p-2 border rounded"><strong>{{ summary.verified }}</strong><div class="small text-muted">Verified</div></div></div>
                            <div class="col-4"><div class="text-center p-2 border rounded"><strong>{{ summary.submitted }}</strong><div class="small text-muted">Submitted types</div></div></div>
                            <div class="col-4"><div class="text-center p-2 border rounded"><strong>{{ summary.required }}</strong><div class="small text-muted">Required groups</div></div></div>
                        </div>
                        <div v-if="summary.is_complete" class="alert alert-success py-2 small mb-3">All required documents verified — awaiting admin approval.</div>
                        <div v-else class="alert alert-warning py-2 small mb-3">Please upload missing required documents. Requirements depend on country ({{ verification.country_code }}) and entity type ({{ verification.entity_type }}).</div>

                        <h6 class="small text-muted">Requirements</h6>
                        <ul class="list-group list-group-flush mb-3">
                            <li v-for="(req, i) in requirements" :key="i" class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <span class="small">{{ requirementLabel(req) }}</span>
                                <span class="badge" :class="summary.groups[i]?.satisfied ? 'bg-success' : 'bg-secondary'">{{ summary.groups[i]?.satisfied ? 'Satisfied' : 'Missing' }}</span>
                            </li>
                        </ul>

                        <div v-if="summary.missing.length" class="small text-muted">
                            Missing: <span v-for="(m, idx) in summary.missing" :key="idx">{{ Array.isArray(m) ? m.join(' / ') : m }}<span v-if="idx < summary.missing.length -1">, </span></span>
                        </div>
                        <p class="small text-muted mt-3 mb-0">Documents are stored privately and reviewed manually. For Aadhaar, upload only masked Aadhaar (first 8 digits hidden, e.g. XXXX-XXXX-1234). Never share full Aadhaar number.</p>
                    </div>

                    <!-- Documents -->
                    <div class="card p-4 mb-4">
                        <h5 class="mb-3">Documents</h5>
                        <div v-if="!documents.length" class="text-muted small py-3">No documents uploaded yet. Upload required documents below.</div>
                        <div v-for="doc in documents" :key="doc.id" class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <strong>{{ doc.label }}</strong> <span class="badge ms-2" :class="docBadge(doc.status)">{{ doc.status }}</span>
                                    <div class="small text-muted">{{ doc.original_filename }} · {{ (doc.file_size/1024).toFixed(1) }} KB · {{ new Date(doc.created_at).toLocaleDateString() }}</div>
                                    <div v-if="doc.document_number_masked" class="small">Number: {{ doc.document_number_masked }}</div>
                                    <div v-if="doc.rejection_reason" class="small text-danger">Rejected: {{ doc.rejection_reason }}</div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a :href="appUrl(`/vendor/documents/${doc.id}/download`)" class="btn btn-sm btn-outline-primary">View</a>
                                    <button v-if="doc.status !== 'verified'" type="button" class="btn btn-sm btn-outline-danger" @click="router.delete(appUrl(`/vendor/documents/${doc.id}`), { preserveScroll: true })">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Upload -->
                    <div v-if="canUpload" class="card p-4">
                        <h5 class="mb-3">Upload Document</h5>
                        <form @submit.prevent="uploadDocument" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Document Type *</label>
                                    <select v-model="uploadForm.document_type" class="form-select" :class="{ 'is-invalid': uploadForm.errors.document_type }" required>
                                        <option value="">Select type</option>
                                        <optgroup label="Identity / Address">
                                            <option v-for="d in [...groupedCatalog.identity, ...groupedCatalog.address_proof]" :key="d.value" :value="d.value">{{ d.label }}</option>
                                        </optgroup>
                                        <optgroup label="Business">
                                            <option v-for="d in groupedCatalog.business" :key="d.value" :value="d.value">{{ d.label }}</option>
                                        </optgroup>
                                        <optgroup label="Bank">
                                            <option v-for="d in groupedCatalog.bank" :key="d.value" :value="d.value">{{ d.label }}</option>
                                        </optgroup>
                                        <optgroup label="Industry (Optional)">
                                            <option v-for="d in groupedCatalog.industry" :key="d.value" :value="d.value">{{ d.label }}</option>
                                        </optgroup>
                                    </select>
                                    <div v-if="uploadForm.errors.document_type" class="invalid-feedback">{{ uploadForm.errors.document_type }}</div>
                                    <div v-if="uploadForm.document_type === 'masked_aadhaar'" class="form-text text-warning">Upload masked Aadhaar only (first 8 digits hidden). Do not upload full Aadhaar.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">File * (PDF, JPG, PNG — max 5MB)</label>
                                    <input id="doc-file-input" type="file" class="form-control" :class="{ 'is-invalid': uploadForm.errors.document_file }" accept=".pdf,.jpg,.jpeg,.png" @change="handleFile" required />
                                    <div v-if="uploadForm.errors.document_file" class="invalid-feedback">{{ uploadForm.errors.document_file }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Masked Number (optional, last 4 digits for Aadhaar)</label>
                                    <input v-model="uploadForm.document_number_masked" class="form-control" placeholder="e.g. XXXX-XXXX-1234" />
                                    <div v-if="uploadForm.errors.document_number_masked" class="invalid-feedback d-block">{{ uploadForm.errors.document_number_masked }}</div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-svtp mt-3" :disabled="uploadForm.processing">
                                <span v-if="uploadForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                                Upload
                            </button>
                        </form>
                    </div>
                    <div v-else class="alert alert-secondary small">Upload is disabled for {{ application.status }} applications. Contact support if you need to provide additional documents.</div>
                </div>

                <div class="col-lg-4">
                    <div class="card p-4 sticky-top" style="top: 20px;">
                        <h6>Application Status</h6>
                        <p class="small text-muted">Your application is <strong>{{ application.status }}</strong>. Verification is <strong>{{ verification.status }}</strong>.</p>
                        <Link :href="appUrl('/account')" class="btn btn-outline-secondary btn-sm w-100 mb-2">Back to Account</Link>
                        <Link v-if="application.status === 'approved'" :href="appUrl('/vendor')" class="btn btn-svtp btn-sm w-100">Go to Vendor Dashboard</Link>
                        <div class="small text-muted mt-3">
                            <strong>Privacy note:</strong> KYC documents are used for vendor verification only, stored privately, and never exposed via public URLs. Only you and authorized reviewers can access them.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
