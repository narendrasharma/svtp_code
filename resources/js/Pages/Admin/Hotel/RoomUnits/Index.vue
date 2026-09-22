<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';
const props = defineProps({ property: Object, units: Object, roomTypes: Array, statuses: Array });
const form = useForm({ room_type_id: '', unit_name: '', floor: '', status: 'active', notes: '' });
function save() { form.post(appUrl(`/admin/hotel/properties/${props.property.id}/units`), { onSuccess: () => form.reset() }); }
function statusBadge(s) {
    return { active: 'bg-success', inactive: 'bg-dark', maintenance: 'bg-warning', out_of_service: 'bg-danger' }[s] ?? 'bg-secondary';
}
</script>
<template><AdminLayout><div class="container-fluid py-3">
<Link :href="appUrl(`/admin/hotel/properties/${property.id}`)">← {{ property.name }}</Link>
<h2 class="my-3">Room units · {{ property.name }}</h2>
<p class="text-muted">Optional physical rooms (101, Villa A…). Aggregate inventory works without units.</p>
<div class="card table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Unit</th><th>Room type</th><th>Floor</th><th>Status</th><th></th></tr></thead><tbody>
<tr v-for="u in units.data" :key="u.id"><td>{{ u.unit_name }}</td><td>{{ u.room_type?.name }}</td><td>{{ u.floor ?? '—' }}</td><td><span class="badge" :class="statusBadge(u.status)">{{ u.status }}</span></td>
<td class="text-end"><button class="btn btn-sm btn-outline-danger" @click="router.delete(appUrl(`/admin/hotel/units/${u.id}`))">Archive</button></td></tr>
<tr v-if="!units.data.length"><td colspan="5">No physical units tracked. Add rows only for individually managed rooms.</td></tr>
</tbody></table><Pagination :links="units.links" /></div>
<form class="card p-3" @submit.prevent="save"><h5>Add unit</h5>
<div v-for="(error, key) in form.errors" :key="key" class="text-danger">{{ error }}</div>
<div class="row g-3">
<div class="col-md-3"><label class="form-label" for="un-type">Room type *</label><select id="un-type" v-model="form.room_type_id" required class="form-select"><option value="">Choose</option><option v-for="r in roomTypes" :key="r.id" :value="r.id">{{ r.name }}</option></select></div>
<div class="col-md-3"><label class="form-label" for="un-name">Unit name/number *</label><input id="un-name" v-model="form.unit_name" required maxlength="80" placeholder="101" class="form-control" /></div>
<div class="col-md-2"><label class="form-label" for="un-floor">Floor</label><input id="un-floor" v-model="form.floor" maxlength="40" class="form-control" /></div>
<div class="col-md-2"><label class="form-label" for="un-status">Status</label><select id="un-status" v-model="form.status" class="form-select"><option v-for="s in statuses" :key="s" :value="s">{{ s }}</option></select></div>
<div class="col-md-2"><label class="form-label" for="un-notes">Notes</label><input id="un-notes" v-model="form.notes" maxlength="500" class="form-control" /></div>
</div>
<button class="btn btn-svtp mt-3" :disabled="form.processing">Add unit</button>
</form>
</div></AdminLayout></template>
