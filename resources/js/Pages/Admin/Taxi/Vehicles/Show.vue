<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    vehicle: { type: Object, required: true },
    documentTypes: { type: Array, default: () => [] },
    expiringDocuments: { type: Number, default: 0 },
});

const vehicle = computed(() => props.vehicle);
const documents = computed(() => props.vehicle?.documents ?? []);
const unavailable = computed(() => props.vehicle?.unavailable_periods ?? []);

const documentForm = useForm({
    document_type: props.documentTypes?.[0]?.value ?? '',
    document_file: null,
    document_number: '',
    issue_date: '',
    expiry_date: '',
});

const blockForm = useForm({
    from_at: '',
    to_at: '',
    type: 'maintenance',
    reason: '',
});

function uploadDocument() {
    documentForm.post(appUrl(`/admin/taxi/vehicles/${vehicle.value.id}/documents`), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => documentForm.reset('document_number', 'issue_date', 'expiry_date', 'document_file'),
    });
}

function verifyDocument(document, approved) {
    router.post(appUrl(`/admin/taxi/vehicles/documents/${document.id}/verify`), {
        approved,
        review_note: '',
    }, { preserveScroll: true });
}

function addBlock() {
    blockForm.post(appUrl(`/admin/taxi/vehicles/${vehicle.value.id}/unavailable`), {
        preserveScroll: true,
        onSuccess: () => blockForm.reset('from_at', 'to_at', 'reason'),
    });
}

function removeBlock(periodId) {
    if (!window.confirm('Remove this unavailable period?')) return;
    router.delete(appUrl(`/admin/taxi/vehicles/unavailable/${periodId}`), { preserveScroll: true });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">{{ vehicle.reference }} · {{ vehicle.name }}</h2>
                <p class="text-muted mb-0">{{ vehicle.registration_number }}</p>
            </div>
            <div class="d-flex gap-2">
                <Link :href="appUrl(`/admin/taxi/vehicles/${vehicle.id}/edit`)" class="btn btn-outline-light">Edit vehicle</Link>
                <Link :href="appUrl('/admin/taxi/vehicles')" class="btn btn-outline-light"><i class="bi bi-arrow-left me-2"></i>Back to list</Link>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h6 class="text-uppercase text-muted small">Vendor</h6>
                                <div>{{ vehicle.vendor_profile?.business_name ?? 'Platform managed' }}</div>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-uppercase text-muted small">Type</h6>
                                <div>{{ vehicle.vehicle_type?.name ?? '—' }}</div>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-uppercase text-muted small">Status</h6>
                                <span class="badge text-uppercase bg-secondary">{{ vehicle.status }}</span>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-uppercase text-muted small">Passengers</h6>
                                <div>{{ vehicle.passenger_capacity }}</div>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-uppercase text-muted small">Luggage</h6>
                                <div>{{ vehicle.luggage_capacity ?? 0 }}</div>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-uppercase text-muted small">Fuel</h6>
                                <div class="text-capitalize">{{ vehicle.fuel_type ?? '—' }}</div>
                            </div>
                            <div class="col-md-3">
                                <h6 class="text-uppercase text-muted small">Transmission</h6>
                                <div class="text-capitalize">{{ vehicle.transmission ?? '—' }}</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6 class="text-uppercase text-muted small">Notes</h6>
                            <p class="mb-0">{{ vehicle.notes || '—' }}</p>
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
                    <div class="card-header"><strong>Unavailable periods</strong></div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div v-for="period in unavailable" :key="period.id" class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-capitalize">{{ period.type }}</div>
                                    <div class="small text-muted">{{ formatDate(period.from_at) }} → {{ formatDate(period.to_at) }}</div>
                                    <div class="small text-muted">{{ period.reason || '—' }}</div>
                                </div>
                                <button class="btn btn-sm btn-outline-light" @click="removeBlock(period.id)"><i class="bi bi-x"></i></button>
                            </div>
                            <div v-if="!unavailable.length" class="p-4 text-center text-muted">No maintenance or blocked periods recorded.</div>
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
                            <label class="form-label small">Document file (PDF/JPG/PNG)</label>
                            <input type="file" class="form-control" @change="documentForm.document_file = $event.target.files[0]" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Document number (masked)</label>
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
                    <div class="card-header"><strong>Add unavailable period</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small">From</label>
                            <input v-model="blockForm.from_at" type="datetime-local" class="form-control" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">To</label>
                            <input v-model="blockForm.to_at" type="datetime-local" class="form-control" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Type</label>
                            <select v-model="blockForm.type" class="form-select">
                                <option value="maintenance">Maintenance</option>
                                <option value="blocked">Blocked</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Reason</label>
                            <textarea v-model="blockForm.reason" class="form-control" rows="2" maxlength="255"></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-outline-light w-100" :disabled="blockForm.processing" @click.prevent="addBlock">Add period</button>
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
