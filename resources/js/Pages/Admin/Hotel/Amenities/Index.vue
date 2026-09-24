<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';
import IconPicker from '../../../../Components/Admin/IconPicker.vue';
defineProps({ amenities: Object, categories: Array });
const editing = ref(null);
const form = useForm({ name: '', slug: '', icon: '', category: '', is_active: true, sort_order: 0 });
function edit(a) { editing.value = a.id; form.name = a.name; form.slug = a.slug; form.icon = a.icon ?? ''; form.category = a.category ?? ''; form.is_active = !!a.is_active; form.sort_order = a.sort_order ?? 0; }
function save() {
    if (editing.value) form.put(appUrl(`/admin/hotel/amenities/${editing.value}`), { onSuccess: () => { editing.value = null; form.reset(); } });
    else form.post(appUrl('/admin/hotel/amenities'), { onSuccess: () => form.reset() });
}
</script>
<template><AdminLayout><div class="container-fluid py-3">
<h2 class="my-3">Hotel amenities</h2>
<div class="card table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Name</th><th>Category</th><th>Active</th><th>Order</th><th></th></tr></thead><tbody>
<tr v-for="a in amenities.data" :key="a.id"><td>{{ a.name }}</td><td>{{ a.category ?? '—' }}</td><td>{{ a.is_active ? 'Yes' : 'No' }}</td><td>{{ a.sort_order }}</td>
<td class="text-end"><button class="btn btn-sm btn-outline-secondary me-1" @click="edit(a)">Edit</button><button class="btn btn-sm btn-outline-warning" @click="router.patch(appUrl(`/admin/hotel/amenities/${a.id}/toggle`))">{{ a.is_active ? 'Deactivate' : 'Activate' }}</button></td></tr>
<tr v-if="!amenities.data.length"><td colspan="5">No amenities yet.</td></tr>
</tbody></table><Pagination :links="amenities.links" /></div>
<form class="card p-3" @submit.prevent="save"><h5>{{ editing ? 'Edit' : 'New' }} amenity</h5>
<div v-for="(error, key) in form.errors" :key="key" class="text-danger">{{ error }}</div>
<div class="row g-3">
<div class="col-md-4"><label class="form-label" for="am-name">Name *</label><input id="am-name" v-model="form.name" required maxlength="80" class="form-control" /></div>
<div class="col-md-4"><label class="form-label" for="am-slug">Slug (auto if blank)</label><input id="am-slug" v-model="form.slug" maxlength="100" class="form-control" /></div>
<div class="col-md-4"><label class="form-label" for="am-cat">Category</label><input id="am-cat" v-model="form.category" maxlength="40" list="amenity-categories" class="form-control" /><datalist id="amenity-categories"><option v-for="c in categories" :key="c" :value="c" /></datalist></div>
<div class="col-md-4"><IconPicker v-model="form.icon" /></div>
<div class="col-md-4"><label class="form-label" for="am-order">Order</label><input id="am-order" v-model="form.sort_order" type="number" min="0" class="form-control" /></div>
<div class="col-md-4"><label class="form-check mt-4"><input v-model="form.is_active" type="checkbox" class="form-check-input" /> Active</label></div>
</div>
<div class="d-flex gap-2 mt-3"><button class="btn btn-svtp" :disabled="form.processing">Save</button><button type="button" class="btn btn-outline-secondary" @click="editing = null; form.reset()">Cancel</button></div>
</form>
</div></AdminLayout></template>
