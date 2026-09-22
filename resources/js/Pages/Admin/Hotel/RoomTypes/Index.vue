<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ property: Object, roomTypes: Array, statuses: Array });
function statusBadge(s) {
    return { draft: 'bg-secondary', active: 'bg-success', inactive: 'bg-dark' }[s] ?? 'bg-secondary';
}
</script>
<template><AdminLayout><div class="container-fluid py-3">
<Link :href="appUrl(`/admin/hotel/properties/${property.id}`)">← {{ property.name }}</Link>
<div class="d-flex flex-wrap align-items-center gap-2 my-3"><h2 class="me-auto mb-0">Room types · {{ property.name }}</h2><Link :href="appUrl(`/admin/hotel/properties/${property.id}/room-types/create`)" class="btn btn-svtp">New room type</Link></div>
<div class="card table-responsive"><table class="table mb-0"><thead><tr><th>Room type</th><th>Sleeps</th><th>Beds</th><th>Capacity</th><th>Status</th></tr></thead><tbody>
<tr v-for="r in roomTypes" :key="r.id">
<td><Link :href="appUrl(`/admin/hotel/room-types/${r.id}`)">{{ r.name }}</Link><br /><span class="small text-muted">{{ r.slug }}</span></td>
<td>{{ r.max_adults }} adults<span v-if="r.max_children"> + {{ r.max_children }} children</span> (max {{ r.max_occupancy }})</td>
<td>{{ r.bed_summary ?? '—' }}</td><td>{{ r.inventory_mode === 'units' ? `${r.units_count ?? 0} units` : `${r.total_units ?? 0} units` }}</td>
<td><span class="badge" :class="statusBadge(r.status)">{{ r.status }}</span></td>
</tr>
<tr v-if="!roomTypes.length"><td colspan="5">No room types yet. Add the sellable categories for this property.</td></tr>
</tbody></table></div>
</div></AdminLayout></template>
