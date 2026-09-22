<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({ country: { type: Object, default: null } });
const form = useForm({
    name: props.country?.name ?? '',
    iso2: props.country?.iso2 ?? '',
    iso3: props.country?.iso3 ?? '',
    phone_code: props.country?.phone_code ?? '',
    currency_code: props.country?.currency_code ?? '',
    is_active: props.country?.is_active ?? true,
    sort_order: props.country?.sort_order ?? 0,
});

function submit() {
    const url = props.country ? `${appUrl('/admin/countries')}/${props.country.id}` : appUrl('/admin/countries');
    const payload = (data) => {
        const out = { ...data };
        out.iso2 = (out.iso2 || '').toUpperCase();
        if (!out.iso3) out.iso3 = null;
        if (!out.phone_code) out.phone_code = null;
        if (!out.currency_code) out.currency_code = null; else out.currency_code = out.currency_code.toUpperCase();
        return out;
    };
    if (props.country) {
        form.transform((data) => ({ ...payload(data), _method: 'put' })).put(url);
    } else {
        form.transform(payload).post(url);
    }
}
</script>

<template>
    <AdminLayout>
        <h2>{{ country ? 'Edit' : 'New' }} Country</h2>
        <form class="mt-3" style="max-width: 640px;" @submit.prevent="submit">
            <label class="form-label">Name</label>
            <input v-model="form.name" class="form-control" required>
            <small class="text-danger">{{ form.errors.name }}</small>

            <div class="row">
                <div class="col-md-4">
                    <label class="form-label mt-3">ISO2 *</label>
                    <input v-model="form.iso2" class="form-control text-uppercase" required maxlength="2" minlength="2" placeholder="IN">
                    <small class="text-danger">{{ form.errors.iso2 }}</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label mt-3">ISO3</label>
                    <input v-model="form.iso3" class="form-control text-uppercase" maxlength="3" placeholder="IND">
                    <small class="text-danger">{{ form.errors.iso3 }}</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label mt-3">Phone code</label>
                    <input v-model="form.phone_code" class="form-control" maxlength="8" placeholder="+91">
                    <small class="text-danger">{{ form.errors.phone_code }}</small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <label class="form-label mt-3">Currency code</label>
                    <input v-model="form.currency_code" class="form-control text-uppercase" maxlength="3" placeholder="INR">
                    <small class="d-block text-muted mt-1">Reference metadata only — property pricing stays authoritative.</small>
                    <small class="text-danger">{{ form.errors.currency_code }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label mt-3">Sort order</label>
                    <input v-model="form.sort_order" type="number" min="0" class="form-control">
                    <small class="text-danger">{{ form.errors.sort_order }}</small>
                </div>
            </div>

            <div class="form-check form-switch mt-3">
                <input class="form-check-input" type="checkbox" id="countryActiveSwitch" v-model="form.is_active">
                <label class="form-check-label" for="countryActiveSwitch">
                    {{ form.is_active ? 'Active' : 'Inactive' }}
                </label>
                <small class="text-danger">{{ form.errors.is_active }}</small>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">Save Country</button>
                <Link :href="appUrl('/admin/countries')" class="btn btn-outline-secondary">Cancel</Link>
            </div>
        </form>
    </AdminLayout>
</template>
