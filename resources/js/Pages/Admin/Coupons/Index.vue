<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    coupons: Object,
    filters: { type: Object, default: () => ({}) },
    vendors: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/coupons');

function toggle(coupon) {
    router.patch(`${endpoint}/${coupon.id}/toggle`, {}, { preserveScroll: true });
}
function destroy(coupon) {
    if (!window.confirm(`Delete coupon ${coupon.code}? Only unused coupons can be deleted.`)) return;
    router.delete(`${endpoint}/${coupon.id}`);
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <div>
                <h2 class="mb-1">Coupons</h2>
                <p class="text-muted mb-0 small">Platform and vendor promo codes. Past bookings keep their snapshot.</p>
            </div>
            <Link :href="`${endpoint}/create`" class="btn btn-svtp ms-auto">New Coupon</Link>
        </div>
        <div class="card p-3 p-md-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0 small">
                    <thead><tr><th>Code</th><th>Type / Value</th><th>Scope</th><th>Vendor</th><th>Validity</th><th>Usage</th><th>Active</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="coupon in coupons.data" :key="coupon.id">
                            <td><strong>{{ coupon.code }}</strong><div class="text-muted">{{ coupon.name }}</div></td>
                            <td>{{ coupon.discount_type }} · {{ coupon.discount_value }}</td>
                            <td>{{ coupon.scope }}</td>
                            <td>{{ coupon.vendor_profile?.business_name ?? '—' }}</td>
                            <td class="text-muted">{{ coupon.starts_at ?? '—' }} → {{ coupon.ends_at ?? '—' }}</td>
                            <td>{{ coupon.redemptions_count }}{{ coupon.usage_limit ? ` / ${coupon.usage_limit}` : '' }}</td>
                            <td><span class="badge" :class="coupon.is_active ? 'bg-success' : 'bg-secondary'">{{ coupon.is_active ? 'Active' : 'Off' }}</span></td>
                            <td class="text-end text-nowrap">
                                <Link :href="`${endpoint}/${coupon.id}/edit`" class="btn btn-sm btn-outline-secondary me-1">Edit</Link>
                                <button type="button" class="btn btn-sm btn-outline-secondary me-1" @click="toggle(coupon)">{{ coupon.is_active ? 'Deactivate' : 'Activate' }}</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="destroy(coupon)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!coupons.data?.length" class="text-muted small mb-0 mt-3">No coupons yet.</p>
        </div>
    </AdminLayout>
</template>
