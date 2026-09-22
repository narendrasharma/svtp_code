<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ types: Object });
const editing = ref(null);
const form = useForm({ name: '', slug: '', description: '', icon: '', is_active: true, sort_order: 0 });
function edit(t) { editing.value = t.id; form.name = t.name; form.slug = t.slug; form.description = t.description ?? ''; form.icon = t.icon ?? ''; form.is_active = !!t.is_active; form.sort_order = t.sort_order ?? 0; }
function save() {
    if (editing.value) form.put(appUrl(`/admin/hotel/property-types/${editing.value}`), { onSuccess: () => { editing.value = null; form.reset(); } });
    else form.post(appUrl('/admin/hotel/property-types'), { onSuccess: () => form.reset() });
}
</script>
<template><AdminLayout><div class="container-fluid py-3">
<h2 class="my-3">Property types</h2>
<div class="card table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Name</th><th>Slug</th><th>Active</th><th>Order</th><th></th></tr></thead><tbody>
<tr v-for="t in types.data" :key="t.id"><td>{{ t.name }}</td><td>{{ t.slug }}</td><td>{{ t.is_active ? 'Yes' : 'No' }}</td><td>{{ t.sort_order }}</td>
<td class="text-end"><button class="btn btn-sm btn-outline-secondary me-1" @click="edit(t)">Edit</button><button class="btn btn-sm btn-outline-warning" @click="router.patch(appUrl(`/admin/hotel/property-types/${t.id}/toggle`))">{{ t.is_active ? 'Deactivate' : 'Activate' }}</button></td></tr>
<tr v-if="!types.data.length"><td colspan="5">No property types yet.</td></tr>
</tbody></table><Pagination :links="types.links" /></div>
<form class="card p-3" @submit.prevent="save"><h5>{{ editing ? 'Edit' : 'New' }} property type</h5>
<div v-for="(error, key) in form.errors" :key="key" class="text-danger">{{ error }}</div>
<div class="row g-3">
<div class="col-md-4"><label class="form-label" for="pt-name">Name *</label><input id="pt-name" v-model="form.name" required maxlength="80" class="form-control" /></div>
<div class="col-md-4"><label class="form-label" for="pt-slug">Slug (auto if blank)</label><input id="pt-slug" v-model="form.slug" maxlength="100" class="form-control" /></div>
<div class="col-md-2"><label class="form-label" for="pt-order">Order</label><input id="pt-order" v-model="form.sort_order" type="number" min="0" class="form-control" /></div>
<div class="col-md-2"><label class="form-check mt-4"><input v-model="form.is_active" type="checkbox" class="form-check-input" /> Active</label></div>
<div class="col-md-8"><label class="form-label" for="pt-desc">Description</label><input id="pt-desc" v-model="form.description" maxlength="500" class="form-control" /></div>
<div class="col-md-4"><label class="form-label" for="pt-icon">Icon</label><input id="pt-icon" v-model="form.icon" maxlength="60" class="form-control" /></div>
</div>
<div class="d-flex gap-2 mt-3"><button class="btn btn-svtp" :disabled="form.processing">Save</button><button type="button" class="btn btn-outline-secondary" @click="editing = null; form.reset()">Cancel</button></div>
</form>
</div></AdminLayout></template>
