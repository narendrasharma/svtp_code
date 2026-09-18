<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    plan: { type: Object, default: null },
    supportedKeys: { type: Array, default: () => [] },
    vendorsCount: { type: Number, default: 0 },
});

const endpoint = appUrl('/admin/vendor-plans');
const isEdit = !!props.plan?.id;

function featureFor(key) {
    return (props.plan?.features ?? []).find((f) => f.key === key) ?? {};
}

const form = useForm({
    name: props.plan?.name ?? '',
    slug: props.plan?.slug ?? '',
    description: props.plan?.description ?? '',
    is_active: props.plan ? !!props.plan.is_active : true,
    is_default: props.plan ? !!props.plan.is_default : false,
    sort_order: props.plan?.sort_order ?? 0,
    features: (props.supportedKeys ?? []).map((key) => ({
        key,
        value_type: featureFor(key).value_type ?? (key === 'analytics_level' ? 'string' : key.startsWith('max_') ? 'integer' : 'boolean'),
        integer_value: featureFor(key).integer_value ?? (key === 'max_active_tours' ? 10 : key === 'max_coupons' ? 5 : key === 'max_addons_per_tour' ? 5 : ''),
        boolean_value: featureFor(key).boolean_value ?? (key === 'storefront_enabled'),
        string_value: featureFor(key).string_value ?? (key === 'analytics_level' ? 'basic' : ''),
    })),
});

function submit() {
    if (isEdit) form.put(`${endpoint}/${props.plan.id}`);
    else form.post(endpoint);
}

function labelFor(key) {
    return { max_active_tours: 'Max active tours', max_coupons: 'Max coupons', max_addons_per_tour: 'Max add-ons per tour', featured_listing: 'Featured listing', storefront_enabled: 'Public storefront', analytics_level: 'Analytics level (basic/advanced)' }[key] ?? key;
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>All Plans</Link>
            <h2 class="mt-2 mb-1">{{ isEdit ? `Edit ${plan.name}` : 'New Plan' }}</h2>
            <p class="text-muted mb-0 small">Only one plan can be the default. Deleting is blocked once vendors have been assigned.</p>
        </div>
        <form class="card p-3 p-md-4" style="max-width: 820px;" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6"><label for="name" class="form-label">Name*</label><input id="name" v-model="form.name" class="form-control" maxlength="255" required /><small class="text-danger">{{ form.errors.name }}</small></div>
                <div class="col-md-6"><label for="slug" class="form-label">Slug (auto if empty)</label><input id="slug" v-model="form.slug" class="form-control" maxlength="100" /><small class="text-danger">{{ form.errors.slug }}</small></div>
                <div class="col-12"><label for="description" class="form-label">Description</label><textarea id="description" v-model="form.description" class="form-control" rows="2" maxlength="2000"></textarea></div>
                <div class="col-md-4"><label for="sort" class="form-label">Sort order</label><input id="sort" v-model="form.sort_order" type="number" min="0" class="form-control" /></div>
                <div class="col-md-4"><div class="form-check mt-4"><input id="active" v-model="form.is_active" type="checkbox" class="form-check-input" /><label for="active" class="form-check-label">Active</label></div></div>
                <div class="col-md-4"><div class="form-check mt-4"><input id="default" v-model="form.is_default" type="checkbox" class="form-check-input" /><label for="default" class="form-check-label">Default for new vendors</label></div><small class="text-danger">{{ form.errors.is_default }}</small></div>
            </div>
            <h5 class="mt-4 mb-2">Feature entitlements</h5>
            <p class="small text-muted">Use “Unlimited” where no cap should apply. Never use magic negatives.</p>
            <div v-for="(feature, i) in form.features" :key="feature.key" class="border rounded p-3 mb-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4"><strong class="small">{{ labelFor(feature.key) }}</strong><div class="text-muted small">{{ feature.key }}</div></div>
                    <div class="col-md-3"><label :for="`type-${i}`" class="form-label small">Type</label><select :id="`type-${i}`" v-model="feature.value_type" class="form-select form-select-sm"><option value="integer">Integer limit</option><option value="boolean">Yes / No</option><option value="string">Text</option><option value="unlimited">Unlimited</option></select></div>
                    <div class="col-md-5">
                        <label v-if="feature.value_type === 'integer'" :for="`int-${i}`" class="form-label small">Limit</label>
                        <input v-if="feature.value_type === 'integer'" :id="`int-${i}`" v-model="feature.integer_value" type="number" min="0" class="form-control form-control-sm" />
                        <div v-if="feature.value_type === 'boolean'" class="form-check mt-2"><input :id="`bool-${i}`" v-model="feature.boolean_value" type="checkbox" class="form-check-input" /><label :for="`bool-${i}`" class="form-check-label small">Enabled</label></div>
                        <input v-if="feature.value_type === 'string'" :id="`str-${i}`" v-model="feature.string_value" class="form-control form-control-sm" maxlength="100" />
                        <span v-if="feature.value_type === 'unlimited'" class="small text-muted">No cap.</span>
                    </div>
                </div>
            </div>
            <button class="btn btn-svtp mt-2" :disabled="form.processing">{{ isEdit ? 'Save Changes' : 'Create Plan' }}</button>
        </form>
    </AdminLayout>
</template>
