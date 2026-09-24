<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';

const props = defineProps({
    properties: { type: Array, default: () => [] },
    roomTypes: { type: Array, default: () => [] },
    propertyId: { type: Number, default: null },
    roomTypeId: { type: Number, default: null },
    start: { type: String, default: '' },
    end: { type: String, default: '' },
    structuralCapacity: { type: Number, default: null },
    inventoryMode: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
    maxBulkDays: { type: Number, default: 365 },
    basePath: { type: String, required: true },
});

const filters = useForm({
    property_id: props.propertyId ?? '',
    room_type_id: props.roomTypeId ?? '',
    start: props.start ?? '',
    end: props.end ?? '',
});

function load() {
    router.get(appUrl(props.basePath), { ...filters.data() }, { preserveState: true });
}

function pickProperty() {
    filters.room_type_id = '';
    load();
}

const drafts = ref({});
function draftFor(row) {
    if (!drafts.value[row.date]) {
        drafts.value[row.date] = {
            capacity_override: row.capacity_override,
            blocked_units: row.blocked_units,
            stop_sell: !!row.stop_sell,
            note: row.note ?? '',
        };
    }
    return drafts.value[row.date];
}
watch(() => props.rows, () => { drafts.value = {}; });

function saveRow(row) {
    const d = draftFor(row);
    router.post(appUrl(props.basePath), {
        room_type_id: props.roomTypeId,
        date: row.date,
        capacity_override: d.capacity_override === '' ? null : d.capacity_override,
        blocked_units: d.blocked_units === '' ? 0 : d.blocked_units,
        stop_sell: !!d.stop_sell,
        note: d.note || null,
    }, { preserveScroll: true });
}

function clearRow(row) {
    router.delete(appUrl(props.basePath), {
        data: { room_type_id: props.roomTypeId, date: row.date },
        preserveScroll: true,
    });
}

const bulk = useForm({
    room_type_id: props.roomTypeId ?? '',
    start_date: props.start ?? '', end_date: props.end ?? '',
    capacity_override: null, blocked_units: 0, stop_sell: false, note: '', clear: false,
});

watch(() => props.roomTypeId, (value) => { bulk.room_type_id = value ?? ''; });

function applyBulk() {
    bulk.clear = false;
    bulk.post(appUrl(`${props.basePath}/bulk`), { preserveScroll: true });
}

function clearBulk() {
    bulk.clear = true;
    bulk.post(appUrl(`${props.basePath}/bulk`), {
        preserveScroll: true,
        onSuccess: () => { bulk.clear = false; },
    });
}
</script>
<template>
<div>
<div class="card p-3 mb-3"><div class="row g-2 align-items-end">
<div class="col-md-3"><label class="form-label" for="inv-property">Property</label><select id="inv-property" v-model="filters.property_id" class="form-select" @change="pickProperty"><option value="">Choose property</option><option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }} ({{ p.status }})</option></select></div>
<div class="col-md-3"><label class="form-label" for="inv-room">Room type</label><select id="inv-room" v-model="filters.room_type_id" class="form-select" @change="load"><option value="">Choose room type</option><option v-for="r in roomTypes" :key="r.id" :value="r.id">{{ r.name }} ({{ r.status }})</option></select></div>
<div class="col-md-2"><label class="form-label" for="inv-start">From</label><input id="inv-start" v-model="filters.start" type="date" class="form-control" @change="load" /></div>
<div class="col-md-2"><label class="form-label" for="inv-end">To</label><input id="inv-end" v-model="filters.end" type="date" class="form-control" @change="load" /></div>
<div class="col-md-2"><button class="btn btn-outline-secondary w-100" @click="load">Load</button></div>
</div></div>

<div v-if="roomTypeId" class="alert alert-info">
Structural capacity: <strong>{{ structuralCapacity }}</strong>
<span v-if="inventoryMode === 'units'" class="text-muted">(live count of active physical units)</span>
<span v-else class="text-muted">(room type total_units)</span>
<span class="text-muted"> · Dates without a row use this default — no pre-generation needed.</span>
</div>

<div v-if="roomTypeId" class="card table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Date</th><th>Base</th><th>Override</th><th>Blocked</th><th>Stop sell</th><th>Available</th><th>Note</th><th></th></tr></thead><tbody>
<tr v-for="row in rows" :key="row.date">
<td class="text-nowrap">{{ row.date }}</td>
<td>{{ row.structural_capacity }}</td>
<td><input v-model="draftFor(row).capacity_override" type="number" min="0" :max="structuralCapacity" placeholder="—" class="form-control form-control-sm" style="width: 90px" /></td>
<td><input v-model="draftFor(row).blocked_units" type="number" min="0" :max="row.structural_capacity" class="form-control form-control-sm" style="width: 80px" /></td>
<td><input v-model="draftFor(row).stop_sell" type="checkbox" class="form-check-input" /></td>
<td><strong>{{ row.effective_available }}</strong></td>
<td><input v-model="draftFor(row).note" maxlength="500" placeholder="Internal only" class="form-control form-control-sm" /></td>
<td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-primary me-1" @click="saveRow(row)">Save</button><button class="btn btn-sm btn-outline-secondary" @click="clearRow(row)">Clear</button></td></tr>
<tr v-if="!rows.length"><td colspan="8" class="text-muted">No dates in range — narrow the range (max {{ maxBulkDays }} days).</td></tr>
</tbody></table></div>

<div v-if="roomTypeId" class="card p-3"><h5>Bulk date-range update</h5>
<div v-for="(error, key) in bulk.errors" :key="key" class="text-danger">{{ key }}: {{ error }}</div>
<div class="row g-2 align-items-end">
<div class="col-md-2"><label class="form-label" for="inv-b-start">From</label><input id="inv-b-start" v-model="bulk.start_date" type="date" class="form-control" /></div>
<div class="col-md-2"><label class="form-label" for="inv-b-end">To</label><input id="inv-b-end" v-model="bulk.end_date" type="date" class="form-control" /></div>
<div class="col-md-2"><label class="form-label" for="inv-b-cap">Override capacity</label><input id="inv-b-cap" v-model="bulk.capacity_override" type="number" min="0" :max="structuralCapacity" placeholder="—" class="form-control" /></div>
<div class="col-md-2"><label class="form-label" for="inv-b-block">Blocked units</label><input id="inv-b-block" v-model="bulk.blocked_units" type="number" min="0" :max="structuralCapacity" class="form-control" /></div>
<div class="col-md-2"><label class="form-check mt-4"><input v-model="bulk.stop_sell" type="checkbox" class="form-check-input" /> Stop sell</label></div>
<div class="col-md-2"><label class="form-label" for="inv-b-note">Note (internal)</label><input id="inv-b-note" v-model="bulk.note" maxlength="500" class="form-control" /></div>
</div>
<div class="d-flex gap-2 mt-3">
<button class="btn btn-svtp" :disabled="bulk.processing" @click="applyBulk">Apply to range</button>
<button class="btn btn-outline-warning" :disabled="bulk.processing" @click="clearBulk">Clear range</button>
<span class="form-text align-self-center">Empty override + 0 blocked + no stop-sell removes rows (sparse storage).</span>
</div></div>
</div>
</template>
