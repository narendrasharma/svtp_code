<script setup>
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    plan: { type: Object, default: null },
    roomTypeId: { type: Number, default: null },
    propertyId: { type: Number, default: null },
    propertyCurrency: { type: String, default: '' },
    mealPlans: { type: Array, default: () => [] },
    cancellationModes: { type: Array, default: () => [] },
    basePath: { type: String, required: true },
    showPropertyRoom: { type: Boolean, default: false },
});

const editing = !!props.plan;
const form = reactive({
    property_id: props.plan?.property_id ?? props.propertyId ?? '',
    hotel_room_type_id: props.plan?.hotel_room_type_id ?? props.roomTypeId ?? '',
    name: props.plan?.name ?? '',
    code: props.plan?.code ?? '',
    description: props.plan?.description ?? '',
    meal_plan: props.plan?.meal_plan ?? 'room_only',
    cancellation_mode: props.plan?.cancellation_mode ?? 'flexible',
    cancellation_note: props.plan?.cancellation_note ?? '',
    base_adults: props.plan?.base_adults ?? 2,
    base_children: props.plan?.base_children ?? 0,
    base_rate: props.plan?.base_rate ?? '',
    extra_adult_rate: props.plan?.extra_adult_rate ?? 0,
    extra_child_rate: props.plan?.extra_child_rate ?? 0,
    minimum_stay: props.plan?.minimum_stay ?? '',
    maximum_stay: props.plan?.maximum_stay ?? '',
    is_active: props.plan ? !!props.plan.is_active : true,
    sort_order: props.plan?.sort_order ?? 0,
    valid_from: props.plan?.valid_from ?? '',
    valid_until: props.plan?.valid_until ?? '',
    processing: false,
    errors: {},
});

function payload() {
    const out = { ...form };
    delete out.processing; delete out.errors;
    ['property_id', 'hotel_room_type_id', 'minimum_stay', 'maximum_stay'].forEach(k => { if (out[k] === '') out[k] = null; });
    if (out.valid_from === '') out.valid_from = null;
    if (out.valid_until === '') out.valid_until = null;
    if (out.description === '') out.description = null;
    if (out.cancellation_note === '') out.cancellation_note = null;
    if (!props.showPropertyRoom) { delete out.property_id; }
    return out;
}

function save() {
    form.processing = true; form.errors = {};
    const done = () => { form.processing = false; };
    const fail = (e) => { form.errors = e.response?.data?.errors ?? { form: ['Save failed.'] }; form.processing = false; };
    if (editing) router.put(appUrl(`${props.basePath}/${props.plan.id}`), payload(), { preserveScroll: true, onSuccess: done, onError: fail });
    else router.post(appUrl(props.basePath), payload(), { preserveScroll: true, onSuccess: () => { form.processing = false; }, onError: fail });
}
</script>
<template>
<form @submit.prevent="save">
<div v-for="(error, key) in form.errors" :key="key" class="text-danger small">{{ key }}: {{ [].concat(error).join(' ') }}</div>
<div class="row g-2">
<div class="col-md-4"><label class="form-label small">Name *</label><input v-model="form.name" required maxlength="100" class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Code *</label><input v-model="form.code" required maxlength="60" pattern="[a-z0-9]+([-_][a-z0-9]+)*" placeholder="flex-room-only" class="form-control font-monospace" /></div>
<div class="col-md-2"><label class="form-label small">Meal plan *</label><select v-model="form.meal_plan" class="form-select"><option v-for="m in mealPlans" :key="m" :value="m">{{ m }}</option></select></div>
<div class="col-md-2"><label class="form-label small">Cancellation *</label><select v-model="form.cancellation_mode" class="form-select"><option v-for="c in cancellationModes" :key="c" :value="c">{{ c }}</option></select></div>
<div class="col-md-2"><label class="form-label small">Base rate ({{ propertyCurrency }}) *</label><input v-model="form.base_rate" type="number" min="0" step="0.01" required class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Base adults *</label><input v-model="form.base_adults" type="number" min="1" max="20" required class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Base children</label><input v-model="form.base_children" type="number" min="0" max="20" class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Extra adult/night</label><input v-model="form.extra_adult_rate" type="number" min="0" step="0.01" class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Extra child/night</label><input v-model="form.extra_child_rate" type="number" min="0" step="0.01" class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Min stay</label><input v-model="form.minimum_stay" type="number" min="1" max="365" placeholder="—" class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Max stay</label><input v-model="form.maximum_stay" type="number" min="1" max="365" placeholder="—" class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Valid from</label><input v-model="form.valid_from" type="date" class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Valid until</label><input v-model="form.valid_until" type="date" class="form-control" /></div>
<div class="col-md-2"><label class="form-label small">Order</label><input v-model="form.sort_order" type="number" min="0" class="form-control" /></div>
<div class="col-md-4"><label class="form-check mt-4"><input v-model="form.is_active" type="checkbox" class="form-check-input" /> Active</label></div>
<div class="col-md-6"><label class="form-label small">Description</label><input v-model="form.description" maxlength="500" class="form-control" /></div>
<div class="col-md-6"><label class="form-label small">Cancellation note</label><input v-model="form.cancellation_note" maxlength="500" placeholder="Free cancellation until 48h before check-in" class="form-control" /></div>
</div>
<div class="mt-2"><button class="btn btn-svtp btn-sm" :disabled="form.processing">{{ editing ? 'Save plan' : 'Create plan' }}</button>
<slot name="actions" :editing="editing" /></div>
</form>
</template>
