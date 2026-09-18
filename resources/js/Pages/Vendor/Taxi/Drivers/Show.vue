<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    driver: { type: Object, required: true },
    documentTypes: { type: Array, default: () => [] },
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
    documentForm.post(appUrl(`/vendor/taxi/drivers/${driver.value.id}/documents`), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => documentForm.reset('document_number', 'issue_date', 'expiry_date', 'document_file'),
    });
}

function addAvailability() {
    availabilityForm.post(appUrl(`/vendor/taxi/drivers/${driver.value.id}/availability`), {
        preserveScroll: true,
        onSuccess: () => availabilityForm.reset('reason'),
    });
}

function removeAvailability(id) {
    if (!window.confirm('Remove this availability window?')) return;
    router.delete(appUrl(`/vendor/taxi/drivers/availability/${id}`), { preserveScroll: true });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">{{ driver.reference }} · {{ driver.first_name }} {{ driver.last_name }}</h2>
                <p class="text-muted mb-0">{{ driver.phone }} · {{ driver.email }}</p>
            </div>
            <div class="d-flex gap-2">
                <Link :href="appUrl(`/vendor/taxi/drivers/${driver.id}/edit`)" class="btn btn-outline-secondary">Edit</Link>
                <Link :href="appUrl('/vendor/taxi/drivers')" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</Link>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <h6 class="text-uppercase small text-muted">Availability</h6>
                                <span class="badge bg-secondary text-uppercase">{{ driver.availability_status }}</span>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-uppercase small text-muted">Employment</h6>
                                <span class="badge bg-secondary text-uppercase">{{ driver.employment_status }}</span>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-uppercase small text-muted">Active</h6>
                                <div>{{ driver.is_active ? 'Yes' : 'No' }}</div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-uppercase small text-muted">Emergency contact</h6>
                                <div>{{ driver.emergency_contact_name || '—' }}</div>
                                <div class="small text-muted">{{ driver.emergency_contact_phone || '' }}</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6 class="text-uppercase small text-muted">Address</h6>
                            <p class="mb-0">{{ driver.address || '—' }}</p>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><strong>Documents</strong></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead><tr><th>Type</th><th>Number</th><th>Issued</th><th>Expires</th><th>Status</th></tr></thead>
                                <tbody>
                                    <tr v-for="doc in documents" :key="doc.id">
                                        <td class="text-capitalize">{{ doc.document_type }}</td>
                                        <td>{{ doc.document_number_masked ?? '—' }}</td>
                                        <td>{{ doc.issue_date ?? '—' }}</td>
                                        <td>{{ doc.expiry_date ?? '—' }}</td>
                                        <td><span class="badge bg-secondary text-uppercase">{{ doc.status }}</span></td>
                                    </tr>
                                    <tr v-if="!documents.length"><td colspan="5" class="text-center text-muted py-3">No documents uploaded yet.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Availability overrides</strong></div>
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
                                <button class="btn btn-sm btn-outline-secondary" @click="removeAvailability(period.id)"><i class="bi bi-x"></i></button>
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
                            <label class="form-label small">Type</label>
                            <select v-model="documentForm.document_type" class="form-select">
                                <option v-for="type in documentTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">File</label>
                            <input type="file" class="form-control" @change="documentForm.document_file = $event.target.files[0]" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Document number</label>
                            <input v-model="documentForm.document_number" type="text" class="form-control" maxlength="60" />
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
                        <button class="btn btn-primary w-100" :disabled="documentForm.processing" @click.prevent="uploadDocument">Upload document</button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Availability override</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small">Date</label>
                            <input v-model="availabilityForm.date" type="date" class="form-control" />
                            <div class="form-text">Use date for full-day leave or specify time range.</div>
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
                        <button class="btn btn-outline-secondary w-100" :disabled="availabilityForm.processing" @click.prevent="addAvailability">Save availability</button>
                    </div>
                </div>
            </div>
        </div>
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 1.25rem; border: 1px solid #e2e8f0; }
.list-group-item { border-color: #e2e8f0; }
</style>
