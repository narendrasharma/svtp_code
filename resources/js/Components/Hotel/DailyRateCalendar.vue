<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';

const props = defineProps({
    plans: { type: Array, default: () => [] },
    plan: { type: Object, default: null },
    planId: { type: Number, default: null },
    start: { type: String, default: '' },
    end: { type: String, default: '' },
    rows: { type: Array, default: () => [] },
    maxBulkDays: { type: Number, default: 365 },
    basePath: { type: String, required: true },
    plansPath: { type: String, required: true },
});

const filters = useForm({ rate_plan_id: props.planId ?? '', start: props.start ?? '', end: props.end ?? '' });
function load() { router.get(appUrl(props.basePath), { ...filters.data() }, { preserveState: true }); }

const drafts = ref({});
function draftFor(row) {
    if (!drafts.value[row.date]) {
        drafts.value[row.date] = {
            amount_override: row.amount_override, stop_sell: !!row.stop_sell,
            closed_to_arrival: !!row.closed_to_arrival, closed_to_departure: !!row.closed_to_departure,
            note: row.note ?? '',
        };
    }
    return drafts.value[row.date];
}
watch(() => props.rows, () => { drafts.value = {}; });

function saveRow(row) {
    const d = draftFor(row);
    router.post(appUrl(props.basePath), {
        hotel_rate_plan_id: props.planId, rate_date: row.date,
        amount_override: d.amount_override === '' ? null : d.amount_override,
        stop_sell: !!d.stop_sell, closed_to_arrival: !!d.closed_to_arrival,
        closed_to_departure: !!d.closed_to_departure, note: d.note || null,
    }, { preserveScroll: true });
}

function clearRow(row) {
    router.delete(appUrl(props.basePath), {
        data: { hotel_rate_plan_id: props.planId, rate_date: row.date }, preserveScroll: true,
    });
}

const bulk = useForm({
    start_date: props.start ?? '', end_date: props.end ?? '', amount_override: null,
    stop_sell: false, closed_to_arrival: false, closed_to_departure: false, note: '', clear: false,
    weekdays: [],
});

// Carbon day numbers (0 = Sunday … 6 = Saturday), matching HotelRateSeason.
// Display order is Mon → Sun for compact admin UX.
const WEEKDAY_CHIPS = [
    { label: 'Mon', value: 1 },
    { label: 'Tue', value: 2 },
    { label: 'Wed', value: 3 },
    { label: 'Thu', value: 4 },
    { label: 'Fri', value: 5 },
    { label: 'Sat', value: 6 },
    { label: 'Sun', value: 0 },
];

function toggleWeekday(value) {
    const idx = bulk.weekdays.indexOf(value);
    if (idx >= 0) {
        bulk.weekdays.splice(idx, 1);
    } else {
        bulk.weekdays.push(value);
    }
}

function isWeekdaySelected(value) {
    return bulk.weekdays.includes(value);
}

function applyBulk() {
    bulk.clear = false;
    bulk.transform((data) => ({ ...data, hotel_rate_plan_id: props.planId }));
    bulk.post(appUrl(`${props.basePath}/bulk`), { preserveScroll: true });
}

function clearBulk() {
    bulk.clear = true;
    bulk.transform((data) => ({ ...data, hotel_rate_plan_id: props.planId }));
    bulk.post(appUrl(`${props.basePath}/bulk`), { preserveScroll: true, onSuccess: () => { bulk.clear = false; } });
}
</script>
<template>
<div>
<div class="card p-3 mb-3"><div class="row g-2 align-items-end">
<div class="col-md-4"><label class="form-label">Rate plan</label><select v-model="filters.rate_plan_id" class="form-select" @change="load"><option value="">Choose plan</option><option v-for="p in plans" :key="p.id" :value="p.id">{{ p.property?.name }} · {{ p.room_type?.name }} · {{ p.name }}</option></select></div>
<div class="col-md-2"><label class="form-label">From</label><input v-model="filters.start" type="date" class="form-control" @change="load" /></div>
<div class="col-md-2"><label class="form-label">To</label><input v-model="filters.end" type="date" class="form-control" @change="load" /></div>
<div class="col-md-2"><button class="btn btn-outline-secondary w-100" @click="load">Load</button></div>
</div></div>

<div v-if="plan" class="alert alert-info">Base rate: <strong>{{ plan.currency }} {{ plan.base_rate }}</strong><span class="text-muted"> · Dates without a row inherit base + seasonal rules — no pre-generation needed.</span></div>

<div v-if="planId" class="card table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Date</th><th>Base</th><th>Effective</th><th>Override</th><th>Season</th><th>Stop</th><th>CTA</th><th>CTD</th><th>Note</th><th></th></tr></thead><tbody>
<tr v-for="row in rows" :key="row.date">
<td class="text-nowrap">{{ row.date }}</td><td>{{ row.base_rate }}</td>
<td><strong>{{ row.effective_rate }}</strong></td>
<td><input v-model="draftFor(row).amount_override" type="number" min="0" step="0.01" placeholder="—" class="form-control form-control-sm" style="width: 100px" /></td>
<td>{{ row.has_season ? 'Yes' : '—' }}</td>
<td><input v-model="draftFor(row).stop_sell" type="checkbox" class="form-check-input" title="Stop sell" /></td>
<td><input v-model="draftFor(row).closed_to_arrival" type="checkbox" class="form-check-input" title="Closed to arrival" /></td>
<td><input v-model="draftFor(row).closed_to_departure" type="checkbox" class="form-check-input" title="Closed to departure" /></td>
<td><input v-model="draftFor(row).note" maxlength="500" placeholder="Internal" class="form-control form-control-sm" /></td>
<td class="text-end text-nowrap"><button class="btn btn-sm btn-outline-primary me-1" @click="saveRow(row)">Save</button><button class="btn btn-sm btn-outline-secondary" @click="clearRow(row)">Clear</button></td></tr>
<tr v-if="!rows.length"><td colspan="10" class="text-muted">No dates in range (max {{ maxBulkDays }} days).</td></tr>
</tbody></table></div>

<div v-if="planId" class="card p-3"><h5>Bulk date-range update</h5>
<div v-for="(error, key) in bulk.errors" :key="key" class="text-danger">{{ key }}: {{ error }}</div>
<div class="row g-2 align-items-end">
<div class="col-md-2"><label class="form-label">From</label><input v-model="bulk.start_date" type="date" class="form-control" /></div>
<div class="col-md-2"><label class="form-label">To</label><input v-model="bulk.end_date" type="date" class="form-control" /></div>
<div class="col-md-2"><label class="form-label">Set nightly price</label><input v-model="bulk.amount_override" type="number" min="0" step="0.01" placeholder="—" class="form-control" /></div>
<div class="col-md-2"><label class="form-label">Note</label><input v-model="bulk.note" maxlength="500" class="form-control" /></div>
<div class="col-md-4 d-flex gap-3 align-items-end">
<label class="form-check"><input v-model="bulk.stop_sell" type="checkbox" class="form-check-input" /> Stop sell</label>
<label class="form-check"><input v-model="bulk.closed_to_arrival" type="checkbox" class="form-check-input" /> CTA</label>
<label class="form-check"><input v-model="bulk.closed_to_departure" type="checkbox" class="form-check-input" /> CTD</label>
</div>
</div>
<div class="mt-3">
<label class="form-label d-block">Apply on days</label>
<div class="d-flex flex-wrap gap-1" role="group" aria-label="Apply on days">
<button
v-for="day in WEEKDAY_CHIPS"
:key="day.value"
type="button"
class="btn btn-sm"
:class="isWeekdaySelected(day.value) ? 'btn-primary' : 'btn-outline-secondary'"
:aria-pressed="isWeekdaySelected(day.value)"
@click="toggleWeekday(day.value)"
>{{ day.label }}</button>
</div>
<div class="form-text">Leave all days unselected to apply to every date in the range.</div>
</div>
<div class="d-flex gap-2 mt-3">
<button class="btn btn-svtp" :disabled="bulk.processing" @click="applyBulk">Apply to range</button>
<button class="btn btn-outline-warning" :disabled="bulk.processing" @click="clearBulk">Clear range</button>
</div></div>
</div>
</template>
