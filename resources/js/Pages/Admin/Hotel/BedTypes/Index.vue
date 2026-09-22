<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ bedTypes: Object });
const editing = ref(null);
const form = useForm({ name: '', slug: '', is_active: true, sort_order: 0 });
function edit(b) { editing.value = b.id; form.name = b.name; form.slug = b.slug; form.is_active = !!b.is_active; form.sort_order = b.sort_order ?? 0; }
function save() {
    if (editing.value) form.put(appUrl(`/admin/hotel/bed-types/${editing.value}`), { onSuccess: () => { editing.value = null; form.reset(); } });
    else form.post(appUrl('/admin/hotel/bed-types'), { onSuccess: () => form.reset() });
}
</script>
<template><AdminLayout><div class="container-fluid py-3">
<h2 class="my-3">Bed types</h2>
<div class="card table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Name</th><th>Slug</th><th>Active</th><th>Order</th><th></th></tr></thead><tbody>
<tr v-for="b in bedTypes.data" :key="b.id"><td>{{ b.name }}</td><td>{{ b.slug }}</td><td>{{ b.is_active ? 'Yes' : 'No' }}</td><td>{{ b.sort_order }}</td>
<td class="text-end"><button class="btn btn-sm btn-outline-secondary me-1" @click="edit(b)">Edit</button><button class="btn btn-sm btn-outline-warning" @click="router.patch(appUrl(`/admin/hotel/bed-types/${b.id}/toggle`))">{{ b.is_active ? 'Deactivate' : 'Activate' }}</button></td></tr>
<tr v-if="!bedTypes.data.length"><td colspan="5">No bed types yet.</td></tr>
</tbody></table><Pagination :links="bedTypes.links" /></div>
<form class="card p-3" @submit.prevent="save"><h5>{{ editing ? 'Edit' : 'New' }} bed type</h5>
<div v-for="(error, key) in form.errors" :key="key" class="text-danger">{{ error }}</div>
<div class="row g-3">
<div class="col-md-4"><label class="form-label" for="bt-name">Name *</label><input id="bt-name" v-model="form.name" required maxlength="60" class="form-control" /></div>
<div class="col-md-4"><label class="form-label" for="bt-slug">Slug (auto if blank)</label><input id="bt-slug" v-model="form.slug" maxlength="80" class="form-control" /></div>
<div class="col-md-2"><label class="form-label" for="bt-order">Order</label><input id="bt-order" v-model="form.sort_order" type="number" min="0" class="form-control" /></div>
<div class="col-md-2"><label class="form-check mt-4"><input v-model="form.is_active" type="checkbox" class="form-check-input" /> Active</label></div>
</div>
<div class="d-flex gap-2 mt-3"><button class="btn btn-svtp" :disabled="form.processing">Save</button><button type="button" class="btn btn-outline-secondary" @click="editing = null; form.reset()">Cancel</button></div>
</form>
</div></AdminLayout></template>
