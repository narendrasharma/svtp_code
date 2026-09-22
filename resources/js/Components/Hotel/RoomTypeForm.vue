<script setup>
import { useForm } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import CustomFieldInputs from './CustomFieldInputs.vue';

const props = defineProps({
    property: { type: Object, required: true },
    roomType: { type: Object, default: null },
    bedTypes: { type: Array, default: () => [] },
    amenities: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    inventoryModes: { type: Array, default: () => ['aggregate', 'units'] },
    sizeUnits: { type: Array, default: () => ['sqm', 'sqft'] },
    submitUrl: { type: String, required: true },
    updateUrl: { type: String, default: '' },
    customFields: { type: Array, default: () => [] },
});

const editing = !!props.roomType;
const form = useForm({
    name: props.roomType?.name ?? '',
    slug: props.roomType?.slug ?? '',
    short_description: props.roomType?.short_description ?? '',
    description: props.roomType?.description ?? '',
    max_adults: props.roomType?.max_adults ?? 2,
    max_children: props.roomType?.max_children ?? 0,
    max_occupancy: props.roomType?.max_occupancy ?? 2,
    base_adults: props.roomType?.base_adults ?? '',
    base_children: props.roomType?.base_children ?? '',
    size_value: props.roomType?.size_value ?? '',
    size_unit: props.roomType?.size_unit ?? '',
    inventory_mode: props.roomType?.inventory_mode ?? 'aggregate',
    total_units: props.roomType?.total_units ?? '',
    status: props.roomType?.status ?? 'draft',
    is_featured: !!props.roomType?.is_featured,
    sort_order: props.roomType?.sort_order ?? 0,
    beds: (props.roomType?.bed_types ?? []).map(b => ({ bed_type_id: b.id, quantity: b.pivot.quantity })),
    amenity_ids: (props.roomType?.amenities ?? []).map(a => a.id),
    custom_fields: Object.fromEntries((props.customFields ?? []).map(f => [f.id, f.value ?? (f.type === 'multiselect' ? [] : null)])),
});

function addBed() { form.beds.push({ bed_type_id: '', quantity: 1 }); }
function removeBed(i) { form.beds.splice(i, 1); }

function submit() {
    form.transform((data) => {
        const payload = { ...data };
        ['base_adults', 'base_children', 'size_value', 'total_units', 'sort_order'].forEach(k => {
            if (payload[k] === '') payload[k] = null;
        });
        if (payload.size_unit === '') payload.size_unit = null;
        if (!payload.slug) delete payload.slug;
        return payload;
    });
    if (editing && props.updateUrl) form.put(props.updateUrl);
    else form.post(props.submitUrl);
}
</script>
<template>
<form @submit.prevent="submit">
<div v-for="(error, key) in form.errors" :key="key" class="alert alert-danger py-1">{{ key }}: {{ error }}</div>

<div class="row g-3">
<div class="col-md-6"><label class="form-label" for="rt-name">Room type name *</label><input id="rt-name" v-model="form.name" required maxlength="150" class="form-control" /></div>
<div class="col-md-6"><label class="form-label" for="rt-slug">Slug (auto if blank, unique per property)</label><input id="rt-slug" v-model="form.slug" maxlength="180" pattern="[a-z0-9]+(-[a-z0-9]+)*" class="form-control" /></div>
<div class="col-md-4"><label class="form-label" for="rt-status">Status</label><select id="rt-status" v-model="form.status" class="form-select"><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select></div>
<div class="col-md-4"><label class="form-label" for="rt-order">Sort order</label><input id="rt-order" v-model="form.sort_order" type="number" min="0" class="form-control" /></div>
<div class="col-md-4"><label class="form-check mt-4"><input v-model="form.is_featured" type="checkbox" class="form-check-input" /> Featured</label></div>
<div class="col-12"><label class="form-label" for="rt-short">Short description</label><input id="rt-short" v-model="form.short_description" maxlength="500" class="form-control" /></div>
<div class="col-12"><label class="form-label" for="rt-desc">Full description</label><textarea id="rt-desc" v-model="form.description" rows="4" maxlength="10000" class="form-control" /></div>
</div>

<h5 class="mt-4 mb-3">Occupancy</h5>
<div class="row g-3">
<div class="col-md-3"><label class="form-label" for="rt-adults">Max adults *</label><input id="rt-adults" v-model="form.max_adults" type="number" min="1" max="20" required class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="rt-children">Max children</label><input id="rt-children" v-model="form.max_children" type="number" min="0" max="20" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="rt-occ">Max occupancy *</label><input id="rt-occ" v-model="form.max_occupancy" type="number" min="1" max="30" required class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="rt-base-a">Base adults</label><input id="rt-base-a" v-model="form.base_adults" type="number" min="1" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="rt-base-c">Base children</label><input id="rt-base-c" v-model="form.base_children" type="number" min="0" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="rt-size">Size value</label><input id="rt-size" v-model="form.size_value" type="number" step="0.01" min="0.01" class="form-control" /></div>
<div class="col-md-3"><label class="form-label" for="rt-sizeu">Size unit</label><select id="rt-sizeu" v-model="form.size_unit" class="form-select"><option value="">—</option><option v-for="u in sizeUnits" :key="u" :value="u">{{ u }}</option></select></div>
</div>

<h5 class="mt-4 mb-3">Bed configuration</h5>
<div v-for="(bed, i) in form.beds" :key="i" class="row g-2 mb-2">
<div class="col-7"><select v-model="bed.bed_type_id" class="form-select" required><option value="">Choose bed</option><option v-for="b in bedTypes" :key="b.id" :value="b.id">{{ b.name }}</option></select></div>
<div class="col-3"><input v-model="bed.quantity" type="number" min="1" max="30" required class="form-control" /></div>
<div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger" @click="removeBed(i)">Remove</button></div>
</div>
<button type="button" class="btn btn-sm btn-outline-secondary" @click="addBed">Add bed</button>

<h5 class="mt-4 mb-3">Inventory mode (structural, not daily availability)</h5>
<div class="row g-3">
<div class="col-md-6"><label class="form-label" for="rt-mode">Mode *</label><select id="rt-mode" v-model="form.inventory_mode" class="form-select"><option v-for="m in inventoryModes" :key="m" :value="m">{{ m }}</option></select>
<div class="form-text">Aggregate = total_units is authoritative. Units = active physical room units define capacity.</div></div>
<div v-if="form.inventory_mode === 'aggregate'" class="col-md-6"><label class="form-label" for="rt-total">Total units *</label><input id="rt-total" v-model="form.total_units" type="number" min="1" class="form-control" /></div>
</div>

<h5 class="mt-4 mb-3">Room amenities</h5>
<div class="row g-2">
<label v-for="a in amenities" :key="a.id" class="col-md-4 col-lg-3 form-check"><input v-model="form.amenity_ids" :value="a.id" type="checkbox" class="form-check-input" /> {{ a.name }} <span v-if="a.category" class="text-muted small">({{ a.category }})</span></label>
<p v-if="!amenities.length" class="text-muted">No room amenities defined yet.</p>
</div>

<div v-if="customFields.length" class="mt-4">
<h5 class="mb-3">Additional Room Details</h5>
<CustomFieldInputs :fields="customFields" v-model="form.custom_fields" prefix="rt-cf" />
</div>

<div class="d-flex gap-2 mt-4">
<button class="btn btn-svtp" :disabled="form.processing">{{ editing ? 'Save changes' : 'Create room type' }}</button>
<slot name="actions" />
</div>
</form>
</template>
