<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    application: Object,
    entityTypes: Array,
});

const form = useForm({
    business_name: props.application.business_name,
    entity_type: props.application.entity_type,
    phone: props.application.phone,
    email: props.application.email,
    address: props.application.address,
    city: props.application.city,
    state: props.application.state,
    country_code: props.application.country_code,
    postcode: props.application.postcode ?? '',
    website: props.application.website ?? '',
    business_description: props.application.business_description ?? '',
    consent: false,
});

function submit() {
    form.patch(appUrl('/vendor/application'));
}
</script>

<template>
    <AppLayout>
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="section-title mb-2">Resubmit Application</h1>
                    <p class="text-muted mb-4">Your application was marked for resubmission. Please correct the details and submit again.</p>
                    <div v-if="application.rejection_reason" class="alert alert-warning">Reason: {{ application.rejection_reason }}</div>
                    <div class="card p-4">
                        <form @submit.prevent="submit">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Business Name *</label>
                                    <input v-model="form.business_name" class="form-control" :class="{ 'is-invalid': form.errors.business_name }" />
                                    <div v-if="form.errors.business_name" class="invalid-feedback">{{ form.errors.business_name }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Entity Type *</label>
                                    <select v-model="form.entity_type" class="form-select">
                                        <option v-for="opt in entityTypes" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label">Phone *</label><input v-model="form.phone" class="form-control" /></div>
                                <div class="col-md-6"><label class="form-label">Email *</label><input v-model="form.email" type="email" class="form-control" /></div>
                                <div class="col-12"><label class="form-label">Address *</label><input v-model="form.address" class="form-control" /></div>
                                <div class="col-md-4"><label class="form-label">City *</label><input v-model="form.city" class="form-control" /></div>
                                <div class="col-md-4"><label class="form-label">State *</label><input v-model="form.state" class="form-control" /></div>
                                <div class="col-md-2"><label class="form-label">Country</label><input v-model="form.country_code" class="form-control" maxlength="2" /></div>
                                <div class="col-md-2"><label class="form-label">Postcode</label><input v-model="form.postcode" class="form-control" /></div>
                                <div class="col-md-6"><label class="form-label">Website</label><input v-model="form.website" class="form-control" /></div>
                                <div class="col-12"><label class="form-label">Business Description</label><textarea v-model="form.business_description" class="form-control" rows="3"></textarea></div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input id="consent" v-model="form.consent" type="checkbox" class="form-check-input" :class="{ 'is-invalid': form.errors.consent }" />
                                        <label for="consent" class="form-check-label small">I confirm that I am authorised to submit these documents for verification.</label>
                                        <div v-if="form.errors.consent" class="invalid-feedback d-block">{{ form.errors.consent }}</div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-svtp mt-4" :disabled="form.processing">Resubmit</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
