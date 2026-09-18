<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({ plans: { type: Array, default: () => [] } });

const endpoint = appUrl('/admin/vendor-plans');

function toggle(plan) {
    router.patch(`${endpoint}/${plan.id}/toggle`, {}, { preserveScroll: true });
}
function destroy(plan) {
    if (!window.confirm(`Delete plan ${plan.name}? Only plans without assignment history can be deleted.`)) return;
    router.delete(`${endpoint}/${plan.id}`);
}
function featureText(plan, key) {
    const feature = (plan.features ?? []).find((f) => f.key === key);
    if (!feature) return '—';
    if (feature.value_type === 'unlimited') return 'Unlimited';
    if (feature.value_type === 'integer') return feature.integer_value ?? '—';
    if (feature.value_type === 'boolean') return feature.boolean_value ? 'Yes' : 'No';
    return feature.string_value ?? '—';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Vendor Plans</h2>
                <p class="text-muted mb-0 small">Feature entitlements only — no billing in this phase. Downgrades never delete vendor content.</p>
            </div>
            <Link :href="`${endpoint}/create`" class="btn btn-svtp ms-auto">New Plan</Link>
        </div>
        <div class="card p-3 p-md-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead><tr><th>Plan</th><th>Active tours</th><th>Coupons</th><th>Add-ons/tour</th><th>Featured</th><th>Storefront</th><th>Analytics</th><th>Vendors</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="plan in plans" :key="plan.id">
                            <td><strong>{{ plan.name }}</strong><span v-if="plan.is_default" class="badge bg-info text-dark ms-2">Default</span><div class="text-muted">{{ plan.slug }}</div></td>
                            <td>{{ featureText(plan, 'max_active_tours') }}</td>
                            <td>{{ featureText(plan, 'max_coupons') }}</td>
                            <td>{{ featureText(plan, 'max_addons_per_tour') }}</td>
                            <td>{{ featureText(plan, 'featured_listing') }}</td>
                            <td>{{ featureText(plan, 'storefront_enabled') }}</td>
                            <td>{{ featureText(plan, 'analytics_level') }}</td>
                            <td>{{ plan.vendors_count ?? '—' }}</td>
                            <td><span class="badge" :class="plan.is_active ? 'bg-success' : 'bg-secondary'">{{ plan.is_active ? 'Active' : 'Off' }}</span></td>
                            <td class="text-end text-nowrap">
                                <Link :href="`${endpoint}/${plan.id}/edit`" class="btn btn-sm btn-outline-secondary me-1">Edit</Link>
                                <button type="button" class="btn btn-sm btn-outline-secondary me-1" @click="toggle(plan)">{{ plan.is_active ? 'Deactivate' : 'Activate' }}</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="destroy(plan)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
