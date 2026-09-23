<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    entityTypes: { type: Array, default: () => [] },
    documentCatalog: { type: Array, default: () => [] },
    requirements: { type: Object, default: () => ({}) },
    consentText: String,
    consentVersion: String,
    latestApplication: Object,
});

const form = useForm({
    business_name: '',
    entity_type: 'individual',
    phone: '',
    email: '',
    address: '',
    city: '',
    state: '',
    country_code: 'IN',
    postcode: '',
    website: '',
    business_description: '',
    consent: false,
});

function submit() {
    form.post(appUrl('/vendor/apply'));
}
</script>

<template>
    <AppLayout>
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <p class="section-eyebrow">Vendor Program</p>
                    <h1 class="section-title mb-2">Become a Vendor</h1>
                    <p class="text-muted mb-4">Join our travel marketplace as a verified vendor. Submit your business details and required KYC documents for review. Documents are used solely for vendor verification and stored securely.</p>

                    <div v-if="latestApplication && latestApplication.status === 'rejected'" class="alert alert-warning">
                        Your previous application was rejected. You may submit a new application below. Reason: {{ latestApplication.rejection_reason }}
                    </div>

                    <div class="card p-4 shadow-sm mb-4">
                        <h5 class="mb-3">Business Information</h5>
                        <form @submit.prevent="submit">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Business Name *</label>
                                    <input v-model="form.business_name" class="form-control" :class="{ 'is-invalid': form.errors.business_name }" placeholder="Your travel business" />
                                    <div v-if="form.errors.business_name" class="invalid-feedback">{{ form.errors.business_name }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Entity Type *</label>
                                    <select v-model="form.entity_type" class="form-select" :class="{ 'is-invalid': form.errors.entity_type }">
                                        <option v-for="opt in entityTypes" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                    <div v-if="form.errors.entity_type" class="invalid-feedback">{{ form.errors.entity_type }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone *</label>
                                    <input v-model="form.phone" class="form-control" :class="{ 'is-invalid': form.errors.phone }" placeholder="+91 9XXXXXXXXX" />
                                    <div v-if="form.errors.phone" class="invalid-feedback">{{ form.errors.phone }}</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }" />
                                    <div v-if="form.errors.email" class="invalid-feedback">{{ form.errors.email }}</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address *</label>
                                    <input v-model="form.address" class="form-control" :class="{ 'is-invalid': form.errors.address }" placeholder="Street, locality" />
                                    <div v-if="form.errors.address" class="invalid-feedback">{{ form.errors.address }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">City *</label>
                                    <input v-model="form.city" class="form-control" :class="{ 'is-invalid': form.errors.city }" />
                                    <div v-if="form.errors.city" class="invalid-feedback">{{ form.errors.city }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">State *</label>
                                    <input v-model="form.state" class="form-control" :class="{ 'is-invalid': form.errors.state }" />
                                    <div v-if="form.errors.state" class="invalid-feedback">{{ form.errors.state }}</div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Country *</label>
                                    <input v-model="form.country_code" class="form-control" maxlength="2" :class="{ 'is-invalid': form.errors.country_code }" />
                                    <div v-if="form.errors.country_code" class="invalid-feedback">{{ form.errors.country_code }}</div>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Postcode</label>
                                    <input v-model="form.postcode" class="form-control" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Website</label>
                                    <input v-model="form.website" class="form-control" placeholder="https://..." />
                                    <div v-if="form.errors.website" class="invalid-feedback d-block">{{ form.errors.website }}</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Business Description</label>
                                    <textarea v-model="form.business_description" class="form-control" rows="3" placeholder="Describe your services, experience, destinations..."></textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check p-3 border rounded bg-light">
                                        <input id="consent" v-model="form.consent" type="checkbox" class="form-check-input" :class="{ 'is-invalid': form.errors.consent }" />
                                        <label for="consent" class="form-check-label small">
                                            {{ consentText }} <span class="text-muted">({{ consentVersion }})</span>
                                        </label>
                                        <div v-if="form.errors.consent" class="invalid-feedback d-block">{{ form.errors.consent }}</div>
                                        <p class="small text-muted mt-2 mb-0">Documents are used for vendor verification only and stored in private storage with restricted access. Never upload a full Aadhaar number — use masked Aadhaar where first 8 digits are hidden.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-svtp" :disabled="form.processing">
                                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                    Submit Application
                                </button>
                                <Link :href="appUrl('/account')" class="btn btn-outline-secondary">Cancel</Link>
                            </div>
                        </form>
                    </div>

                    <div class="card p-4 shadow-sm">
                        <h6 class="mb-2">What happens next?</h6>
                        <ol class="small text-muted mb-0">
                            <li>Submit business details (this form)</li>
                            <li>Upload required KYC documents on the next page</li>
                            <li>Admin reviews and verifies documents</li>
                            <li>Upon approval, you receive Vendor access</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
