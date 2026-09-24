<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({ properties: Array, propertyId: Number, propertyCurrency: String, rules: Array, chargeTypes: Array, calculations: Array });

const editing = ref(null);
const form = useForm({
    property_id: props.propertyId ?? '', name: '', charge_type: 'tax', calculation: 'percentage',
    value: '', included_in_price: false, is_active: true, sort_order: 0,
});

function edit(r) {
    editing.value = r.id;
    form.property_id = r.property_id; form.name = r.name; form.charge_type = r.charge_type;
    form.calculation = r.calculation; form.value = r.value;
    form.included_in_price = !!r.included_in_price; form.is_active = !!r.is_active; form.sort_order = r.sort_order ?? 0;
}

function cancel() { editing.value = null; form.reset(); form.property_id = props.propertyId ?? ''; }

function save() {
    if (editing.value) router.put(appUrl(`/admin/hotel/charges/${editing.value}`), { ...form.data() }, { preserveScroll: true, onSuccess: cancel });
    else router.post(appUrl('/admin/hotel/charges'), { ...form.data() }, { preserveScroll: true, onSuccess: cancel });
}

function load(propertyId) { router.get(appUrl('/admin/hotel/charges'), { property_id: propertyId || undefined }, { preserveState: true }); }
</script>
<template><AdminLayout><div class="container-fluid py-3">
<Link :href="appUrl('/admin/hotel/rate-plans')">← Rate plans</Link>
<h2 class="my-3">Hotel taxes &amp; fees</h2>
<p class="text-muted">Property-scoped charges in {{ propertyCurrency }}. Exclusive charges add to the quote; percentage-included rules are extracted for display only.</p>

<div class="card p-3 mb-3"><div class="row g-2 align-items-end">
<div class="col-md-6"><label class="form-label">Property</label><select :value="propertyId ?? ''" class="form-select" @change="load($event.target.value)"><option value="">Choose property</option><option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option></select></div>
</div></div>

<div class="card table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Name</th><th>Type</th><th>Calculation</th><th>Value</th><th>Included</th><th>Active</th><th>Order</th><th></th></tr></thead><tbody>
<tr v-for="r in rules" :key="r.id">
<td>{{ r.name }}</td><td>{{ r.charge_type }}</td><td>{{ r.calculation }}</td><td class="font-monospace">{{ r.value }}</td>
<td>{{ r.included_in_price ? 'Yes' : 'No' }}</td><td>{{ r.is_active ? 'Yes' : 'No' }}</td><td>{{ r.sort_order }}</td>
<td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-secondary me-1" @click="edit(r)">Edit</button><button class="btn btn-sm btn-outline-warning" @click="router.patch(appUrl(`/admin/hotel/charges/${r.id}/toggle`))">{{ r.is_active ? 'Deactivate' : 'Activate' }}</button></td></tr>
<tr v-if="!rules.length"><td colspan="8" class="text-muted">No charge rules yet.</td></tr>
</tbody></table></div>

<div v-if="propertyId" class="card p-3"><h5>{{ editing ? 'Edit' : 'New' }} charge rule ({{ propertyCurrency }})</h5><p class="form-text">Currency is inherited from this property’s hotel pricing currency.</p>
<div v-for="(error, key) in form.errors" :key="key" class="text-danger small">{{ key }}: {{ error }}</div>
<div class="row g-2">
<div class="col-md-3"><label class="form-label small">Name *</label><input v-model="form.name" required maxlength="100" placeholder="VAT" class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Type *</label><select v-model="form.charge_type" class="form-select"><option v-for="t in chargeTypes" :key="t" :value="t">{{ t }}</option></select></div>
<div class="col-md-3"><label class="form-label small">Calculation *</label><select v-model="form.calculation" class="form-select"><option v-for="c in calculations" :key="c" :value="c">{{ c }}</option></select></div>
<div class="col-md-2"><label class="form-label small">Value *</label><input v-model="form.value" type="number" min="0" step="0.01" required class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Order</label><input v-model="form.sort_order" type="number" min="0" class="form-control" /></div>
<div class="col-12 d-flex gap-3">
<label class="form-check"><input v-model="form.included_in_price" type="checkbox" class="form-check-input" /> Included in price (percentage only)</label>
<label class="form-check"><input v-model="form.is_active" type="checkbox" class="form-check-input" /> Active</label>
</div>
</div>
<div class="d-flex gap-2 mt-2"><button class="btn btn-svtp btn-sm" :disabled="form.processing" @click="save">Save</button><button class="btn btn-outline-secondary btn-sm" @click="cancel">Cancel</button></div>
</div>
</div></AdminLayout></template>
