<script setup>
import { Link } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ properties: Object, filters: Object, statuses: Array, canCreate: Boolean });
function statusBadge(s) {
    return { draft: 'bg-secondary', pending_review: 'bg-warning', published: 'bg-success', inactive: 'bg-dark', rejected: 'bg-danger' }[s] ?? 'bg-secondary';
}
</script>
<template><VendorLayout><div class="container-fluid py-3">
<div class="d-flex flex-wrap align-items-center gap-2 my-3"><h2 class="me-auto mb-0">My properties</h2><Link v-if="canCreate" :href="appUrl('/vendor/hotel/properties/create')" class="btn btn-svtp">New property</Link></div>
<div class="card table-responsive"><table class="table mb-0"><thead><tr><th>Property</th><th>Type</th><th>Status</th><th>Images</th></tr></thead><tbody>
<tr v-for="p in properties.data" :key="p.id">
<td><Link :href="appUrl(`/vendor/hotel/properties/${p.id}`)">{{ p.name }}</Link><br /><span class="small text-muted">{{ p.slug }}</span></td>
<td>{{ p.property_type?.name }}</td>
<td><span class="badge" :class="statusBadge(p.status)">{{ p.status }}</span></td><td>{{ p.images_count }}</td>
</tr>
<tr v-if="!properties.data.length"><td colspan="4">No properties yet. Create your first listing to get started.</td></tr>
</tbody></table><Pagination :links="properties.links" /></div>
</div></VendorLayout></template>
