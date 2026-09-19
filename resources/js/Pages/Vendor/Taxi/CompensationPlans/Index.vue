<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    plans: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    vendors: { type: Array, default: () => [] },
    calculationTypes: { type: Array, default: () => [] },
});

const endpoint = appUrl('/vendor/taxi/plans');

const form = useForm({
    scope: props.filters.scope ?? '',
    vendor_id: props.filters.vendor_id ?? '',
    currency: props.filters.currency ?? '',
});

function applyFilters() {
    form.get(endpoint, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    form.reset();
    applyFilters();
}

function scopeLabel(plan) {
    if (plan.driver) {
        return `Driver: ${plan.driver.first_name} ${plan.driver.last_name}`;
    }
    if (plan.vendor_profile) {
        return `Vendor: ${plan.vendor_profile.business_name}`;
    }
    return 'Platform default';
}
</script>

<template>
    <VendorLayout>
        <div class="mb-3 d-flex align-items-center">
            <div>
                <h2 class="mt-2 mb-1">Compensation Plans</h2>
                <p class="text-muted mb-0">Driver &rarr; vendor &rarr; platform precedence. Edits never rewrite history.</p>
            </div>
            <Link :href="`${endpoint}/create`" class="btn btn-sm btn-svtp ms-auto">New plan</Link>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Scope</label>
                    <select v-model="form.scope" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="platform">Platform default</option>
                        <option value="vendor">Vendor default</option>
                        <option value="driver">Driver specific</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Vendor</label>
                    <select v-model="form.vendor_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.business_name }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Currency</label>
                    <input v-model="form.currency" class="form-control form-control-sm" maxlength="3" />
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-sm btn-svtp" :disabled="form.processing">Apply</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Scope</th>
                            <th>Calculation</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="plan in plans.data" :key="plan.id">
                            <td>
                                <div class="fw-semibold">{{ plan.name }}</div>
                                <div class="small text-muted">{{ plan.currency }}</div>
                            </td>
                            <td class="small">{{ scopeLabel(plan) }}</td>
                            <td class="small">{{ plan.calculation_type }}</td>
                            <td>
                                <span class="badge" :class="plan.is_active ? 'bg-success' : 'bg-secondary'">{{ plan.is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="text-end">
                                <Link :href="`${endpoint}/${plan.id}/edit`" class="btn btn-sm btn-outline-secondary me-1">Edit</Link>
                                <button class="btn btn-sm btn-outline-warning" @click="router.patch(`${endpoint}/${plan.id}/toggle`)">{{ plan.is_active ? 'Deactivate' : 'Activate' }}</button>
                            </td>
                        </tr>
                        <tr v-if="!plans.data.length"><td colspan="5" class="text-center text-muted py-4">No plans yet.</td></tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="plans.links" class="px-3 py-2" />
        </div>
    </VendorLayout>
</template>
