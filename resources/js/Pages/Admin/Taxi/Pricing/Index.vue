<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({ rateCards: { type: Object, required: true }, filters: { type: Object, default: () => ({}) }, tripTypes: { type: Array, default: () => [] }, vendors: { type: Array, default: () => [] } });
const filters = useForm({ trip_type: props.filters.trip_type ?? '', vendor_id: props.filters.vendor_id ?? '', platform_only: Boolean(props.filters.platform_only) });
function applyFilters() { filters.get(appUrl('/admin/taxi/pricing'), { preserveState: true }); }
function toggle(card) { router.patch(appUrl(`/admin/taxi/pricing/${card.id}/toggle`), {}, { preserveScroll: true }); }
function remove(card) { if (window.confirm(`Remove ${card.name}? Historical booking snapshots will remain.`)) router.delete(appUrl(`/admin/taxi/pricing/${card.id}`)); }
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center gap-3 mb-4"><div><h2 class="mb-1">Taxi pricing</h2><p class="text-muted mb-0">Platform and vendor rate cards with deterministic fallback.</p></div><Link :href="appUrl('/admin/taxi/pricing/create')" class="btn btn-svtp">New rate card</Link></div>
        <form class="card card-body mb-3" @submit.prevent="applyFilters"><div class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label small">Trip type</label><select v-model="filters.trip_type" class="form-select"><option value="">All</option><option v-for="type in tripTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select></div><div class="col-md-4"><label class="form-label small">Vendor</label><select v-model="filters.vendor_id" class="form-select"><option value="">All</option><option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option></select></div><div class="col-md-2"><label class="form-check"><input v-model="filters.platform_only" type="checkbox" class="form-check-input" /><span class="ms-2">Platform only</span></label></div><div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div></div></form>
        <div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Scope</th><th>Trip</th><th>Vehicle</th><th>Currency</th><th>Rules</th><th>Status</th><th></th></tr></thead><tbody><tr v-for="card in rateCards.data" :key="card.id"><td>{{ card.name }}</td><td>{{ card.vendor_profile?.business_name ?? 'Platform' }}</td><td class="text-capitalize">{{ String(card.trip_type).replaceAll('_', ' ') }}</td><td>{{ card.vehicle_type?.name ?? 'Default' }}</td><td>{{ card.currency }}</td><td>{{ card.rules_count }}<span v-if="card.rental_packages_count"> · {{ card.rental_packages_count }} packages</span></td><td><span class="badge" :class="card.is_active ? 'bg-success' : 'bg-secondary'">{{ card.is_active ? 'Active' : 'Inactive' }}</span></td><td class="text-end"><div class="btn-group btn-group-sm"><Link :href="appUrl(`/admin/taxi/pricing/${card.id}/edit`)" class="btn btn-outline-light">Edit</Link><button class="btn btn-outline-light" @click="toggle(card)">{{ card.is_active ? 'Deactivate' : 'Activate' }}</button><button class="btn btn-outline-danger" @click="remove(card)">Remove</button></div></td></tr><tr v-if="!rateCards.data.length"><td colspan="8" class="text-center text-muted py-4">No rate cards found.</td></tr></tbody></table></div><Pagination :links="rateCards.links" class="p-3" /></div>
    </AdminLayout>
</template>
