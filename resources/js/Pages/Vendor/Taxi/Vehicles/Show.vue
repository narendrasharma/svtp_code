<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    vehicle: { type: Object, required: true },
    documentTypes: { type: Array, default: () => [] },
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
    documentForm.post(appUrl(`/vendor/taxi/vehicles/${vehicle.value.id}/documents`), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => documentForm.reset('document_number', 'issue_date', 'expiry_date', 'document_file'),
    });
}

function addBlock() {
    blockForm.post(appUrl(`/vendor/taxi/vehicles/${vehicle.value.id}/unavailable`), {
        preserveScroll: true,
        onSuccess: () => blockForm.reset('reason'),
    });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">{{ vehicle.reference }} · {{ vehicle.name }}</h2>
                <p class="text-muted mb-0">{{ vehicle.registration_number }}</p>
            </div>
            <div class="d-flex gap-2">
                <Link :href="appUrl(`/vendor/taxi/vehicles/${vehicle.id}/edit`)" class="btn btn-outline-secondary">Edit</Link>
                <Link :href="appUrl('/vendor/taxi/vehicles')" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</Link>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <h6 class="text-uppercase small text-muted">Vehicle type</h6>
                                <div>{{ vehicle.vehicle_type?.name ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-uppercase small text-muted">Status</h6>
                                <span class="badge bg-secondary text-uppercase">{{ vehicle.status }}</span>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-uppercase small text-muted">Capacity</h6>
                                <div>{{ vehicle.passenger_capacity }} pax · {{ vehicle.luggage_capacity ?? 0 }} bags</div>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-uppercase small text-muted">Fuel</h6>
                                <div class="text-capitalize">{{ vehicle.fuel_type ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-uppercase small text-muted">Transmission</h6>
                                <div class="text-capitalize">{{ vehicle.transmission ?? '—' }}</div>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-uppercase small text-muted">Air conditioned</h6>
                                <div>{{ vehicle.is_air_conditioned ? 'Yes' : 'No' }}</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6 class="text-uppercase small text-muted">Notes</h6>
                            <p class="mb-0">{{ vehicle.notes || '—' }}</p>
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
                                        <td><span class="badge text-uppercase bg-secondary">{{ doc.status }}</span></td>
                                    </tr>
                                    <tr v-if="!documents.length"><td colspan="5" class="text-center text-muted py-3">No documents uploaded yet.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong>Unavailable periods</strong></div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div v-for="period in unavailable" :key="period.id" class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="fw-semibold text-capitalize">{{ period.type }}</div>
                                        <div class="small text-muted">{{ formatDate(period.from_at) }} → {{ formatDate(period.to_at) }}</div>
                                        <div class="small text-muted">{{ period.reason || '—' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="!unavailable.length" class="p-4 text-center text-muted">No maintenance periods recorded.</div>
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
                            <label class="form-label small">Document file</label>
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
                    <div class="card-header"><strong>Block vehicle</strong></div>
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
                        <button class="btn btn-outline-secondary w-100" :disabled="blockForm.processing" @click.prevent="addBlock">Add period</button>
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
