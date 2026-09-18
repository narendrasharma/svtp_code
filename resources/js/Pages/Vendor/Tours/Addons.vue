<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ tour: Object, addons: { type: Array, default: () => [] } });
const base = appUrl(`/vendor/tours/${props.tour.id}/addons`);

const form = useForm({
    name: '', description: '', pricing_type: 'fixed', price: '',
    is_required: false, is_active: true, max_quantity: '', sort_order: 0,
});

function submit() {
    form.post(base, { preserveScroll: true, onSuccess: () => form.reset() });
}
function toggle(addon) {
    router.patch(`${base}/${addon.id}/toggle`, {}, { preserveScroll: true });
}
function remove(addon) {
    if (!window.confirm(`Remove ${addon.name}? Past bookings keep their snapshot.`)) return;
    router.delete(`${base}/${addon.id}`, { preserveScroll: true });
}
</script>

<template>
    <VendorLayout>
        <div class="mb-4">
            <Link :href="appUrl('/vendor/tours')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>All Tours</Link>
            <h2 class="mt-2 mb-1">Extras — {{ tour.title }}</h2>
            <p class="text-muted mb-0 small">Optional extras for your tour. Past bookings keep their snapshot.</p>
        </div>
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card p-3 p-md-4">
                    <div v-for="addon in addons" :key="addon.id" class="border rounded p-3 mb-2 small">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <strong>{{ addon.name }}</strong>
                            <span class="badge bg-secondary">{{ addon.pricing_type }}</span>
                            <span>₹{{ addon.price }}</span>
                            <span v-if="addon.is_required" class="badge bg-info text-dark">Required</span>
                            <span v-if="!addon.is_active" class="badge bg-secondary">Off</span>
                            <span class="ms-auto d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="toggle(addon)">{{ addon.is_active ? 'Deactivate' : 'Activate' }}</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="remove(addon)">Remove</button>
                            </span>
                        </div>
                    </div>
                    <p v-if="!addons.length" class="text-muted small mb-0">No extras yet.</p>
                </div>
            </div>
            <div class="col-lg-5">
                <form class="card p-3 p-md-4" @submit.prevent="submit">
                    <h5 class="mb-3">Add Extra</h5>
                    <div class="row g-2">
                        <div class="col-12"><label for="addon-name" class="form-label small">Name*</label><input id="addon-name" v-model="form.name" class="form-control form-control-sm" required maxlength="255" /></div>
                        <div class="col-6"><label for="addon-type" class="form-label small">Pricing*</label><select id="addon-type" v-model="form.pricing_type" class="form-select form-select-sm"><option value="fixed">Fixed (once)</option><option value="per_person">Per person</option><option value="per_quantity">Per quantity</option></select></div>
                        <div class="col-6"><label for="addon-price" class="form-label small">Price (₹)*</label><input id="addon-price" v-model="form.price" type="number" step="0.01" min="0" class="form-control form-control-sm" required /></div>
                        <div class="col-6"><label for="addon-max" class="form-label small">Max qty</label><input id="addon-max" v-model="form.max_quantity" type="number" min="1" max="30" class="form-control form-control-sm" /></div>
                        <div class="col-6"><div class="form-check mt-2"><input id="addon-active" v-model="form.is_active" type="checkbox" class="form-check-input" /><label for="addon-active" class="form-check-label small">Active</label></div></div>
                        <div class="col-12"><button class="btn btn-svtp btn-sm w-100" :disabled="form.processing">Add Extra</button></div>
                    </div>
                </form>
            </div>
        </div>
    </VendorLayout>
</template>
