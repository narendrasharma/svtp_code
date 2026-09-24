<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';
const props = defineProps({ properties: Object, filters: Object, statuses: Array, vendors: Array, types: Array, cities: Array });
const form = useForm({ search: props.filters?.search ?? '', status: props.filters?.status ?? '', property_type_id: props.filters?.property_type_id ?? '', city_id: props.filters?.city_id ?? '', vendor_id: props.filters?.vendor_id ?? '', featured: props.filters?.featured ?? '' });
const hasFilters = computed(() => Object.values(form.data()).some(value => value !== '' && value !== null));
function applyFilters() { form.get(appUrl('/admin/hotel/properties'), { preserveState: true, preserveScroll: true }); }
function clearFilters() { router.get(appUrl('/admin/hotel/properties'), {}, { preserveScroll: true }); }
function statusBadge(s) {
    return { draft: 'bg-secondary', pending_review: 'bg-warning', published: 'bg-success', inactive: 'bg-dark', rejected: 'bg-danger' }[s] ?? 'bg-secondary';
}
</script>
<template><AdminLayout><div class="container-fluid py-3">
<div class="d-flex flex-wrap align-items-center gap-2 my-3"><h2 class="me-auto mb-0">Hotel properties</h2><Link :href="appUrl('/admin/hotel/properties/create')" class="btn btn-svtp">New property</Link></div>
<form class="card p-3 mb-3" @submit.prevent="applyFilters"><div class="row g-2 align-items-end">
<div class="col-md-3"><label class="form-label small" for="property-search">Search</label><input id="property-search" v-model="form.search" class="form-control form-control-sm" placeholder="Property name" /></div>
<div class="col-md-2"><label class="form-label small">Status</label><select v-model="form.status" class="form-select form-select-sm"><option value="">All statuses</option><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select></div>
<div class="col-md-2"><label class="form-label small">Property type</label><select v-model="form.property_type_id" class="form-select form-select-sm"><option value="">All types</option><option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option></select></div>
<div class="col-md-2"><label class="form-label small">City / destination</label><select v-model="form.city_id" class="form-select form-select-sm"><option value="">All cities</option><option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option></select></div>
<div class="col-md-2"><label class="form-label small">Vendor</label><select v-model="form.vendor_id" class="form-select form-select-sm"><option value="">All ownership</option><option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option></select></div>
<div class="col-md-1"><button class="btn btn-sm btn-svtp w-100">Apply</button></div><div class="col-12"><button v-if="hasFilters" type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Reset filters</button></div>
</div></form>
<div class="card table-responsive"><table class="table mb-0"><thead><tr><th>Property</th><th>Type</th><th>Vendor</th><th>City</th><th>Status</th><th>Images</th></tr></thead><tbody>
<tr v-for="p in properties.data" :key="p.id">
<td><Link :href="appUrl(`/admin/hotel/properties/${p.id}`)">{{ p.name }}</Link><br /><span class="small text-muted">{{ p.slug }}</span><span v-if="p.is_featured" class="badge bg-info ms-1">featured</span></td>
<td>{{ p.property_type?.name }}</td><td>{{ p.vendor_profile?.business_name ?? 'Platform' }}</td><td>{{ p.city?.name ?? '—' }}</td>
<td><span class="badge" :class="statusBadge(p.status)">{{ p.status }}</span></td><td>{{ p.images_count }}</td>
</tr>
<tr v-if="!properties.data.length"><td colspan="6" class="text-center text-muted py-4">No properties match these filters.</td></tr>
</tbody></table><Pagination :links="properties.links" /></div>
</div></AdminLayout></template>
