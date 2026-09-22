<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({ definitions: Object, filters: Object, entities: Array, fieldTypes: Array, groups: Array, propertyTypes: Array });

const editing = ref(null);
const form = useForm({
    entity_type: 'property', name: '', key: '', field_type: 'text',
    group_name: '', help_text: '', placeholder: '',
    is_required: false, is_active: true, show_on_frontend: true, show_label: true,
    sort_order: 0, options: [], property_type_ids: [],
});
const optionDraft = ref({ value: '', label: '' });
const filterForm = useForm({ entity_type: props.filters?.entity_type ?? '', is_active: props.filters?.is_active ?? '', search: props.filters?.search ?? '' });

const isOptionType = computed(() => ['select', 'multiselect'].includes(form.field_type));
const showApplicability = computed(() => form.entity_type === 'property');

function normalizedOptions(list) {
    return (list ?? []).map(o => typeof o === 'string' ? { value: o, label: o } : { value: o.value ?? '', label: o.label ?? o.value ?? '' });
}

function edit(d) {
    editing.value = d.id;
    form.entity_type = d.entity_type;
    form.name = d.name;
    form.key = d.key;
    form.field_type = d.field_type;
    form.group_name = d.group_name ?? '';
    form.help_text = d.help_text ?? '';
    form.placeholder = d.placeholder ?? '';
    form.is_required = !!d.is_required;
    form.is_active = !!d.is_active;
    form.show_on_frontend = !!d.show_on_frontend;
    form.show_label = d.show_label ?? true;
    form.sort_order = d.sort_order ?? 0;
    form.options = normalizedOptions(d.options);
    form.property_type_ids = (d.property_types ?? []).map(t => t.id);
    optionDraft.value = { value: '', label: '' };
}

function cancel() { editing.value = null; form.reset(); optionDraft.value = { value: '', label: '' }; }

function addOption() {
    const value = optionDraft.value.value.trim();
    if (!value) return;
    form.options = [...form.options, { value, label: optionDraft.value.label.trim() || value }];
    optionDraft.value = { value: '', label: '' };
}

function removeOption(i) { form.options = form.options.filter((_, j) => j !== i); }

function save() {
    const payload = { ...form.data(), options: normalizedOptions(form.options) };
    if (!isOptionType.value) payload.options = null;
    if (!showApplicability.value) payload.property_type_ids = [];
    if (editing.value) {
        router.put(appUrl(`/admin/hotel/custom-fields/${editing.value}`), payload, { onSuccess: cancel });
    } else {
        router.post(appUrl('/admin/hotel/custom-fields'), payload, { onSuccess: cancel });
    }
}

function applyFilters() {
    router.get(appUrl('/admin/hotel/custom-fields'), { ...filterForm.data() }, { preserveState: true });
}

function typeScope(d) {
    if (d.entity_type !== 'property') return '—';
    if (!d.property_types?.length) return 'All types';
    return d.property_types.map(t => t.name).join(', ');
}
</script>
<template><AdminLayout><div class="container-fluid py-3">
<Link :href="appUrl('/admin/hotel/properties')">← All properties</Link>
<h2 class="my-3">Hotel custom fields</h2>
<p class="text-muted">Admin-defined informational fields for properties and room types. Vendors fill values on their own listings; values never affect pricing, inventory or availability. Deactivation preserves stored values.</p>

<form class="card p-3 mb-3" @submit.prevent="applyFilters"><div class="row g-2 align-items-end">
<div class="col-md-3"><label class="form-label" for="cf-f-entity">Entity</label><select id="cf-f-entity" v-model="filterForm.entity_type" class="form-select"><option value="">All</option><option v-for="e in entities" :key="e" :value="e">{{ e }}</option></select></div>
<div class="col-md-3"><label class="form-label" for="cf-f-active">Status</label><select id="cf-f-active" v-model="filterForm.is_active" class="form-select"><option value="">All</option><option value="1">Active</option><option value="0">Inactive</option></select></div>
<div class="col-md-4"><label class="form-label" for="cf-f-search">Search</label><input id="cf-f-search" v-model="filterForm.search" maxlength="80" placeholder="Name or key" class="form-control" /></div>
<div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
</div></form>

<div class="card table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Label</th><th>Key</th><th>Entity</th><th>Type</th><th>Group</th><th>Applies to</th><th>Req</th><th>Frontend</th><th>Order</th><th>Values</th><th>Active</th><th></th></tr></thead><tbody>
<tr v-for="d in definitions.data" :key="d.id">
<td>{{ d.name }}</td><td class="font-monospace small">{{ d.key }}</td><td>{{ d.entity_type }}</td><td>{{ d.field_type }}</td>
<td>{{ d.group_name ?? '—' }}</td><td class="small">{{ typeScope(d) }}</td>
<td>{{ d.is_required ? 'Yes' : 'No' }}</td><td>{{ d.show_on_frontend ? 'Yes' : 'No' }}</td>
<td>{{ d.sort_order }}</td><td>{{ d.values_count }}</td><td>{{ d.is_active ? 'Yes' : 'No' }}</td>
<td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-secondary me-1" @click="edit(d)">Edit</button><button class="btn btn-sm btn-outline-warning" @click="router.patch(appUrl(`/admin/hotel/custom-fields/${d.id}/toggle`))">{{ d.is_active ? 'Deactivate' : 'Activate' }}</button></td></tr>
<tr v-if="!definitions.data.length"><td colspan="12">No custom fields yet.</td></tr>
</tbody></table><Pagination :links="definitions.links" /></div>

<form class="card p-3" @submit.prevent="save"><h5>{{ editing ? 'Edit' : 'New' }} custom field</h5>
<div v-for="(error, key) in form.errors" :key="key" class="text-danger">{{ key }}: {{ error }}</div>
<div class="row g-3">
<div class="col-md-3"><label class="form-label" for="cf-entity">Entity *</label><select id="cf-entity" v-model="form.entity_type" class="form-select"><option v-for="e in entities" :key="e" :value="e">{{ e }}</option></select></div>
<div class="col-md-3"><label class="form-label" for="cf-type">Field type *</label><select id="cf-type" v-model="form.field_type" class="form-select"><option v-for="t in fieldTypes" :key="t" :value="t">{{ t }}</option></select></div>
<div class="col-md-3"><label class="form-label" for="cf-name">Label *</label><input id="cf-name" v-model="form.name" required maxlength="100" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="cf-key">Key (auto if blank, locked once values exist)</label><input id="cf-key" v-model="form.key" maxlength="80" pattern="[a-z0-9]+(_[a-z0-9]+)*" placeholder="nearest_airport" class="form-control font-monospace" /></div>
<div class="col-md-3"><label class="form-label" for="cf-group">Group</label><input id="cf-group" v-model="form.group_name" maxlength="80" list="cf-groups" class="form-control" /><datalist id="cf-groups"><option v-for="g in groups" :key="g" :value="g" /></datalist></div>
<div class="col-md-3"><label class="form-label" for="cf-order">Order</label><input id="cf-order" v-model="form.sort_order" type="number" min="0" max="9999" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="cf-placeholder">Placeholder</label><input id="cf-placeholder" v-model="form.placeholder" maxlength="150" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="cf-help">Help text</label><input id="cf-help" v-model="form.help_text" maxlength="500" class="form-control" /></div>
<div class="col-12 d-flex flex-wrap gap-3">
<label class="form-check"><input v-model="form.is_required" type="checkbox" class="form-check-input" /> Required</label>
<label class="form-check"><input v-model="form.is_active" type="checkbox" class="form-check-input" /> Active</label>
<label class="form-check"><input v-model="form.show_on_frontend" type="checkbox" class="form-check-input" /> Show on frontend</label>
<label class="form-check"><input v-model="form.show_label" type="checkbox" class="form-check-input" /> Show label</label>
</div>
<div v-if="showApplicability" class="col-12"><span class="form-label d-block">Property types (none = all types)</span>
<label v-for="t in propertyTypes" :key="t.id" class="form-check form-check-inline"><input v-model="form.property_type_ids" :value="t.id" type="checkbox" class="form-check-input" /> {{ t.name }}</label>
</div>
<div v-if="isOptionType" class="col-12"><span class="form-label d-block">Options (at least one required)</span>
<div v-for="(o, i) in form.options" :key="i" class="d-flex gap-2 mb-1 align-items-center">
<span class="font-monospace small">{{ o.value }}</span><span class="text-muted small">→ {{ o.label }}</span>
<button type="button" class="btn btn-sm btn-outline-danger ms-2" @click="removeOption(i)">Remove</button>
</div>
<div class="d-flex gap-2 mt-2">
<input v-model="optionDraft.value" maxlength="80" placeholder="value" class="form-control" style="max-width: 220px" />
<input v-model="optionDraft.label" maxlength="100" placeholder="Label (defaults to value)" class="form-control" style="max-width: 280px" />
<button type="button" class="btn btn-outline-secondary" @click="addOption">Add option</button>
</div></div>
</div>
<div class="d-flex gap-2 mt-3"><button class="btn btn-svtp" :disabled="form.processing">Save</button><button type="button" class="btn btn-outline-secondary" @click="cancel">Cancel</button></div>
</form>
</div></AdminLayout></template>
