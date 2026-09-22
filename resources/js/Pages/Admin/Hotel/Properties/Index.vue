<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ properties: Object, filters: Object, statuses: Array, vendors: Array, types: Array });
function statusBadge(s) {
    return { draft: 'bg-secondary', pending_review: 'bg-warning', published: 'bg-success', inactive: 'bg-dark', rejected: 'bg-danger' }[s] ?? 'bg-secondary';
}
</script>
<template><AdminLayout><div class="container-fluid py-3">
<div class="d-flex flex-wrap align-items-center gap-2 my-3"><h2 class="me-auto mb-0">Hotel properties</h2><Link :href="appUrl('/admin/hotel/properties/create')" class="btn btn-svtp">New property</Link></div>
<div class="card table-responsive"><table class="table mb-0"><thead><tr><th>Property</th><th>Type</th><th>Vendor</th><th>City</th><th>Status</th><th>Images</th></tr></thead><tbody>
<tr v-for="p in properties.data" :key="p.id">
<td><Link :href="appUrl(`/admin/hotel/properties/${p.id}`)">{{ p.name }}</Link><br /><span class="small text-muted">{{ p.slug }}</span><span v-if="p.is_featured" class="badge bg-info ms-1">featured</span></td>
<td>{{ p.property_type?.name }}</td><td>{{ p.vendor_profile?.business_name ?? 'Platform' }}</td><td>{{ p.city?.name ?? '—' }}</td>
<td><span class="badge" :class="statusBadge(p.status)">{{ p.status }}</span></td><td>{{ p.images_count }}</td>
</tr>
<tr v-if="!properties.data.length"><td colspan="6">No properties yet. Create the first one to preview the Hotels module.</td></tr>
</tbody></table><Pagination :links="properties.links" /></div>
</div></AdminLayout></template>
