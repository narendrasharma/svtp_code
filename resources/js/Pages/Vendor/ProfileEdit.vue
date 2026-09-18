<script setup>
import { useForm } from '@inertiajs/vue3';
import VendorLayout from '../../Layouts/VendorLayout.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({ profile: Object });

const form = useForm({
    business_name: props.profile.business_name,
    phone: props.profile.phone,
    email: props.profile.email,
    address: props.profile.address,
    city: props.profile.city,
    state: props.profile.state,
    country_code: props.profile.country_code,
    postcode: props.profile.postcode ?? '',
    website: props.profile.website ?? '',
    business_description: props.profile.business_description ?? '',
    public_description: props.profile.public_description ?? '',
    public_phone: props.profile.public_phone ?? '',
    public_email: props.profile.public_email ?? '',
    social_links: props.profile.social_links ?? {},
    storefront_enabled: props.profile.storefront_enabled ?? true,
    logo_upload: null,
    cover_upload: null,
    remove_logo: false,
    remove_cover: false,
});

function submit() {
    form.patch(appUrl('/vendor/profile'), { forceFormData: true });
}
</script>

<template>
    <VendorLayout>
        <h2 class="mb-4">Edit Vendor Profile</h2>
        <div class="card p-4">
            <form @submit.prevent="submit">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Business Name *</label><input v-model="form.business_name" class="form-control" :class="{ 'is-invalid': form.errors.business_name }" /><div v-if="form.errors.business_name" class="invalid-feedback">{{ form.errors.business_name }}</div></div>
                    <div class="col-md-3"><label class="form-label">Phone *</label><input v-model="form.phone" class="form-control" :class="{ 'is-invalid': form.errors.phone }" /><div v-if="form.errors.phone" class="invalid-feedback">{{ form.errors.phone }}</div></div>
                    <div class="col-md-3"><label class="form-label">Email *</label><input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }" /><div v-if="form.errors.email" class="invalid-feedback">{{ form.errors.email }}</div></div>
                    <div class="col-12"><label class="form-label">Address</label><input v-model="form.address" class="form-control" /></div>
                    <div class="col-md-4"><label class="form-label">City</label><input v-model="form.city" class="form-control" /></div>
                    <div class="col-md-4"><label class="form-label">State</label><input v-model="form.state" class="form-control" /></div>
                    <div class="col-md-2"><label class="form-label">Country</label><input v-model="form.country_code" class="form-control" maxlength="2" /></div>
                    <div class="col-md-2"><label class="form-label">Postcode</label><input v-model="form.postcode" class="form-control" /></div>
                    <div class="col-md-6"><label class="form-label">Website</label><input v-model="form.website" class="form-control" /></div>
                    <div class="col-12"><label class="form-label">Business Description</label><textarea v-model="form.business_description" class="form-control" rows="3"></textarea></div>
                </div>
                <hr class="my-4" />
                <h5>Public Storefront</h5>
                <p class="small text-muted">Only these presentation fields appear on your public page. Legal phone/email/address stay private unless you opt in below.</p>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Public description</label><textarea v-model="form.public_description" class="form-control" rows="3" maxlength="2000" placeholder="Falls back to business description if empty"></textarea></div>
                    <div class="col-md-6"><label class="form-label">Public phone (optional)</label><input v-model="form.public_phone" class="form-control" maxlength="30" /></div>
                    <div class="col-md-6"><label class="form-label">Public email (optional)</label><input v-model="form.public_email" type="email" class="form-control" maxlength="255" /></div>
                    <div class="col-md-6"><label class="form-label">Logo</label><input type="file" accept="image/*" class="form-control" @input="form.logo_upload = $event.target.files[0]" /><div class="form-check mt-1"><input id="remove-logo" v-model="form.remove_logo" type="checkbox" class="form-check-input" /><label for="remove-logo" class="form-check-label small">Remove current logo</label></div></div>
                    <div class="col-md-6"><label class="form-label">Cover/banner</label><input type="file" accept="image/*" class="form-control" @input="form.cover_upload = $event.target.files[0]" /><div class="form-check mt-1"><input id="remove-cover" v-model="form.remove_cover" type="checkbox" class="form-check-input" /><label for="remove-cover" class="form-check-label small">Remove current cover</label></div></div>
                    <div class="col-12"><div class="form-check"><input id="storefront" v-model="form.storefront_enabled" type="checkbox" class="form-check-input" /><label for="storefront" class="form-check-label">Show my public storefront</label></div></div>
                </div>
                <p class="small text-muted mt-3">Role, verification status and approval metadata cannot be changed here.</p>
                <button type="submit" class="btn btn-svtp mt-2" :disabled="form.processing">Save Changes</button>
            </form>
        </div>
    </VendorLayout>
</template>
