<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    coupon: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
    tours: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/coupons');
const isEdit = !!props.coupon?.id;

const form = useForm({
    code: props.coupon?.code ?? '',
    name: props.coupon?.name ?? '',
    description: props.coupon?.description ?? '',
    discount_type: props.coupon?.discount_type ?? 'percentage',
    discount_value: props.coupon?.discount_value ?? '',
    minimum_booking_amount: props.coupon?.minimum_booking_amount ?? '',
    maximum_discount_amount: props.coupon?.maximum_discount_amount ?? '',
    starts_at: props.coupon?.starts_at ? String(props.coupon.starts_at).slice(0, 16) : '',
    ends_at: props.coupon?.ends_at ? String(props.coupon.ends_at).slice(0, 16) : '',
    usage_limit: props.coupon?.usage_limit ?? '',
    usage_limit_per_user: props.coupon?.usage_limit_per_user ?? '',
    scope: props.coupon?.scope ?? 'global',
    vendor_profile_id: props.coupon?.vendor_profile_id ?? '',
    tour_ids: props.coupon?.tour_ids ?? [],
    is_active: props.coupon ? !!props.coupon.is_active : true,
});

function submit() {
    if (isEdit) {
        form.put(`${endpoint}/${props.coupon.id}`);
    } else {
        form.post(endpoint);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>All Coupons</Link>
            <h2 class="mt-2 mb-1">{{ isEdit ? `Edit ${coupon.code}` : 'New Coupon' }}</h2>
            <p class="text-muted mb-0 small">Editing never alters past booking totals — snapshots are immutable.</p>
        </div>
        <form class="card p-3 p-md-4" style="max-width: 780px;" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-4"><label for="code" class="form-label">Code*</label><input id="code" v-model="form.code" class="form-control" maxlength="50" required /><small class="text-danger">{{ form.errors.code }}</small></div>
                <div class="col-md-8"><label for="name" class="form-label">Name*</label><input id="name" v-model="form.name" class="form-control" maxlength="255" required /><small class="text-danger">{{ form.errors.name }}</small></div>
                <div class="col-12"><label for="description" class="form-label">Description</label><textarea id="description" v-model="form.description" class="form-control" rows="2" maxlength="1000"></textarea></div>
                <div class="col-md-4"><label for="discount-type" class="form-label">Type*</label><select id="discount-type" v-model="form.discount_type" class="form-select"><option value="percentage">Percentage</option><option value="fixed">Fixed (₹)</option></select></div>
                <div class="col-md-4"><label for="discount-value" class="form-label">Value*</label><input id="discount-value" v-model="form.discount_value" type="number" step="0.01" min="0" class="form-control" required /></div>
                <div class="col-md-4"><label for="max-discount" class="form-label">Max discount (₹)</label><input id="max-discount" v-model="form.maximum_discount_amount" type="number" step="0.01" min="0" class="form-control" /></div>
                <div class="col-md-4"><label for="min-amount" class="form-label">Min booking (₹)</label><input id="min-amount" v-model="form.minimum_booking_amount" type="number" step="0.01" min="0" class="form-control" /></div>
                <div class="col-md-4"><label for="starts" class="form-label">Starts at</label><input id="starts" v-model="form.starts_at" type="datetime-local" class="form-control" /></div>
                <div class="col-md-4"><label for="ends" class="form-label">Ends at</label><input id="ends" v-model="form.ends_at" type="datetime-local" class="form-control" /></div>
                <div class="col-md-4"><label for="usage" class="form-label">Usage limit</label><input id="usage" v-model="form.usage_limit" type="number" min="1" class="form-control" /></div>
                <div class="col-md-4"><label for="per-user" class="form-label">Per-user limit</label><input id="per-user" v-model="form.usage_limit_per_user" type="number" min="1" class="form-control" /></div>
                <div class="col-md-4"><label for="scope" class="form-label">Scope*</label><select id="scope" v-model="form.scope" class="form-select"><option value="global">All tours</option><option value="vendor">Selected vendor</option><option value="tours">Selected tours</option></select></div>
                <div v-if="form.scope !== 'global'" class="col-md-6"><label for="vendor" class="form-label">Vendor</label><select id="vendor" v-model="form.vendor_profile_id" class="form-select"><option value="">Select vendor</option><option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option></select></div>
                <div v-if="form.scope === 'tours'" class="col-12"><label class="form-label">Tours</label><div class="border rounded p-2" style="max-height: 220px; overflow: auto;"><div v-for="tour in tours" :key="tour.id" class="form-check"><input :id="`tour-${tour.id}`" v-model="form.tour_ids" :value="tour.id" type="checkbox" class="form-check-input" /><label :for="`tour-${tour.id}`" class="form-check-label small">{{ tour.title }}</label></div></div></div>
                <div class="col-md-6"><div class="form-check mt-4"><input id="active" v-model="form.is_active" type="checkbox" class="form-check-input" /><label for="active" class="form-check-label">Active</label></div></div>
                <div class="col-12"><button class="btn btn-svtp" :disabled="form.processing">{{ isEdit ? 'Save Changes' : 'Create Coupon' }}</button></div>
            </div>
        </form>
    </AdminLayout>
</template>
