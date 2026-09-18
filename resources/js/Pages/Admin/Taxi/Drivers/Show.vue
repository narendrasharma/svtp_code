<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    driver: { type: Object, required: true },
    documentTypes: { type: Array, default: () => [] },
    expiringDocuments: { type: Number, default: 0 },
    activeTrips: { type: Number, default: 0 },
});

const driver = computed(() => props.driver);
const documents = computed(() => props.driver?.documents ?? []);
const availabilities = computed(() => props.driver?.availabilities ?? []);

const documentForm = useForm({
    document_type: props.documentTypes?.[0]?.value ?? '',
    document_file: null,
    document_number: '',
    issue_date: '',
    expiry_date: '',
});

const availabilityForm = useForm({
    date: '',
    from_at: '',
    to_at: '',
    status: 'unavailable',
    reason: '',
});

function uploadDocument() {
    documentForm.post(appUrl(`/admin/taxi/drivers/${driver.value.id}/documents`), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => documentForm.reset('document_number', 'issue_date', 'expiry_date', 'document_file'),
    });
}

function verifyDocument(document, approved) {
    router.post(appUrl(`/admin/taxi/drivers/documents/${document.id}/verify`), {
        approved,
        review_note: '',
    }, { preserveScroll: true });
}

function addAvailability() {
    availabilityForm.post(appUrl(`/admin/taxi/drivers/${driver.value.id}/availability`), {
        preserveScroll: true,
        onSuccess: () => availabilityForm.reset('reason'),
    });
}

function removeAvailability(id) {
    if (!window.confirm('Remove this availability window?')) return;
    router.delete(appUrl(`/admin/taxi/drivers/availability/${id}`), { preserveScroll: true });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">{{ driver.reference }} · {{ driver.first_name }} {{ driver.last_name }}</h2>
                <p class="text-muted mb-0">{{ driver.phone }} · {{ driver.email }}</p>
            </div>
            <div class="d-flex gap-2">
                <Link :href="appUrl(`/admin/taxi/drivers/${driver.id}/edit`)" class="btn btn-outline-light">Edit driver</Link>
                <Link :href="appUrl('/admin/taxi/drivers')" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Back to drivers</Link>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="text-uppercase small text-muted">Vendor</h6>
                                <div>{{ driver.vendor_profile?.business_name ?? '—' }}</div>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-uppercase small text-muted">Availability</h6>
                                <span class="badge text-uppercase bg-secondary">{{ driver.availability_status }}</span>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-uppercase small text-muted">Employment</h6>
                                <span class="badge text-uppercase bg-secondary">{{ driver.employment_status }}</span>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-uppercase small text-muted">Emergency contact</h6>
                                <div>{{ driver.emergency_contact_name || '—' }}</div>
                                <div class="small text-muted">{{ driver.emergency_contact_phone || '' }}</div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-uppercase small text-muted">Active trips</h6>
                                <div>{{ props.activeTrips }}</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6 class="text-uppercase small text-muted">Notes</h6>
                            <p class="mb-0">{{ driver.notes || '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center"><strong>Documents</strong><span class="badge bg-warning text-dark">{{ expiringDocuments }} expiring soon</span></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Type</th><th>Number</th><th>Issued</th><th>Expires</th><th>Status</th><th></th></tr></thead>
                                <tbody>
                                    <tr v-for="doc in documents" :key="doc.id">
                                        <td class="text-capitalize">{{ doc.document_type }}</td>
                                        <td>{{ doc.document_number_masked ?? '—' }}</td>
                                        <td>{{ doc.issue_date ?? '—' }}</td>
                                        <td>{{ doc.expiry_date ?? '—' }}</td>
                                        <td><span class="badge text-uppercase" :class="doc.status === 'verified' ? 'bg-success' : (doc.status === 'rejected' ? 'bg-danger' : 'bg-secondary')">{{ doc.status }}</span></td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-light" @click="verifyDocument(doc, true)">Approve</button>
                                                <button class="btn btn-outline-danger" @click="verifyDocument(doc, false)">Reject</button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!documents.length"><td colspan="6" class="text-center text-muted py-3">No documents uploaded yet.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Availability windows</strong></div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div v-for="period in availabilities" :key="period.id" class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-capitalize">{{ period.status }}</div>
                                    <div class="small text-muted">
                                        <span v-if="period.date">{{ period.date }}</span>
                                        <span v-else>{{ formatDate(period.from_at) }} → {{ formatDate(period.to_at) }}</span>
                                    </div>
                                    <div class="small text-muted">{{ period.reason || '—' }}</div>
                                </div>
                                <button class="btn btn-sm btn-outline-light" @click="removeAvailability(period.id)"><i class="bi bi-x"></i></button>
                            </div>
                            <div v-if="!availabilities.length" class="p-4 text-center text-muted">No availability overrides recorded.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card mb-3">
                    <div class="card-header"><strong>Upload document</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small">Document type</label>
                            <select v-model="documentForm.document_type" class="form-select">
                                <option v-for="type in documentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Document file</label>
                            <input type="file" class="form-control" @change="documentForm.document_file = $event.target.files[0]" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Document number</label>
                            <input v-model="documentForm.document_number" type="text" class="form-control" maxlength="60" placeholder="Optional" />
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">Issue date</label>
                                <input v-model="documentForm.issue_date" type="date" class="form-control" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Expiry date</label>
                                <input v-model="documentForm.expiry_date" type="date" class="form-control" />
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-svtp w-100" :disabled="documentForm.processing" @click.prevent="uploadDocument">Upload document</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Schedule availability override</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small">Day (optional)</label>
                            <input v-model="availabilityForm.date" type="date" class="form-control" />
                            <div class="form-text">Fill either date or from/to for a range.</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">From</label>
                                <input v-model="availabilityForm.from_at" type="datetime-local" class="form-control" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">To</label>
                                <input v-model="availabilityForm.to_at" type="datetime-local" class="form-control" />
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label small">Status</label>
                            <select v-model="availabilityForm.status" class="form-select">
                                <option value="available">Available</option>
                                <option value="unavailable">Unavailable</option>
                                <option value="on_leave">On leave</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Reason</label>
                            <textarea v-model="availabilityForm.reason" class="form-control" rows="2" maxlength="255"></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-outline-light w-100" :disabled="availabilityForm.processing" @click.prevent="addAvailability">Save availability</button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); background: #101827; color: #e2e8f0; }
.list-group-item { background: transparent; color: inherit; border-color: rgba(148, 163, 184, .1); }
.table { --bs-table-bg: transparent; color: inherit; }
.form-control, .form-select, textarea { background: rgba(15, 23, 42, .65); border-color: rgba(148, 163, 184, .25); color: #f8fafc; }
.form-control:focus, .form-select:focus, textarea:focus { border-color: #f59e0b; box-shadow: 0 0 0 .2rem rgba(245, 158, 11, .18); }
.btn.btn-outline-light { color: #f8fafc; border-color: rgba(148, 163, 184, .35); }
</style>
