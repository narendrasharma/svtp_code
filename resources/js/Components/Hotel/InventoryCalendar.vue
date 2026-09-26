<script setup>
import { computed, nextTick, ref, watch } from 'vue';
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

const view = ref('calendar');
const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const calendarMonths = computed(() => {
    const months = [];

    for (const row of props.rows) {
        const key = row.date.slice(0, 7);
        let month = months[months.length - 1];

        if (!month || month.key !== key) {
            const firstDay = new Date(`${key}-01T12:00:00`);
            month = {
                key,
                label: new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric' }).format(firstDay),
                leadingDays: (firstDay.getDay() + 6) % 7,
                days: [],
            };
            months.push(month);
        }

        month.days.push(row);
    }

    for (const month of months) {
        const [year, monthNumber] = month.key.split('-').map(Number);
        const rowsByDay = new Map(month.days.map((row) => [Number(row.date.slice(-2)), row]));
        const daysInMonth = new Date(year, monthNumber, 0).getDate();
        month.slots = Array.from({ length: daysInMonth }, (_, index) => ({
            number: index + 1,
            row: rowsByDay.get(index + 1) ?? null,
        }));
    }

    return months;
});

const selectedDay = ref(null);
const modalCloseButton = ref(null);
let openingButton = null;
const dayForm = useForm({
    room_type_id: props.roomTypeId ?? '',
    date: '',
    capacity_override: null,
    blocked_units: 0,
    stop_sell: false,
    note: '',
});

function openDay(row, event) {
    openingButton = event?.currentTarget ?? null;
    selectedDay.value = row;
    dayForm.clearErrors();
    dayForm.room_type_id = props.roomTypeId;
    dayForm.date = row.date;
    dayForm.capacity_override = row.capacity_override;
    dayForm.blocked_units = row.blocked_units;
    dayForm.stop_sell = !!row.stop_sell;
    dayForm.note = row.note ?? '';
    nextTick(() => modalCloseButton.value?.focus());
}

function closeDay() {
    if (dayForm.processing) return;
    selectedDay.value = null;
    nextTick(() => openingButton?.focus());
}

function handleModalKeydown(event) {
    if (event.key === 'Escape') {
        closeDay();
        return;
    }

    if (event.key !== 'Tab') return;

    const focusable = [...event.currentTarget.querySelectorAll('button:not([disabled]), input:not([disabled]), textarea:not([disabled])')];
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
}

function saveDay() {
    dayForm.transform((data) => ({
        ...data,
        capacity_override: data.capacity_override === '' ? null : data.capacity_override,
        blocked_units: data.blocked_units === '' ? 0 : data.blocked_units,
        note: data.note || null,
    })).post(appUrl(props.basePath), {
        preserveScroll: true,
        onSuccess: closeDay,
    });
}

function clearDay() {
    if (!selectedDay.value || dayForm.processing) return;
    router.delete(appUrl(props.basePath), {
        data: { room_type_id: props.roomTypeId, date: selectedDay.value.date },
        preserveScroll: true,
        onSuccess: closeDay,
    });
}

function dateLabel(date) {
    return new Intl.DateTimeFormat('en', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
        .format(new Date(`${date}T12:00:00`));
}

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

<div v-if="roomTypeId" class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h3 class="h5 mb-1">Daily availability</h3>
        <p class="text-muted small mb-0">Select a date to view its details and update inventory.</p>
    </div>
    <div class="btn-group" role="group" aria-label="Inventory view">
        <button type="button" class="btn btn-sm" :class="view === 'calendar' ? 'btn-primary' : 'btn-outline-primary'" :aria-pressed="view === 'calendar'" @click="view = 'calendar'"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i> Calendar</button>
        <button type="button" class="btn btn-sm" :class="view === 'table' ? 'btn-primary' : 'btn-outline-primary'" :aria-pressed="view === 'table'" @click="view = 'table'"><i class="bi bi-table me-1" aria-hidden="true"></i> Table</button>
    </div>
</div>

<div v-if="roomTypeId && view === 'calendar'" class="inventory-calendar mb-3">
    <div v-for="month in calendarMonths" :key="month.key" class="card inventory-month mb-3">
        <div class="inventory-month-heading"><h4 class="h5 mb-0">{{ month.label }}</h4><span class="small text-muted">{{ month.days.length }} dates in range</span></div>
        <div class="inventory-grid-wrap">
            <div class="inventory-grid">
                <div v-for="weekday in weekdays" :key="weekday" class="inventory-weekday">{{ weekday }}</div>
                <div v-for="blank in month.leadingDays" :key="`blank-${blank}`" class="inventory-empty" aria-hidden="true"></div>
                <template v-for="slot in month.slots" :key="slot.number">
                    <button v-if="slot.row" type="button" class="inventory-day" :class="{ 'inventory-day--closed': slot.row.stop_sell || slot.row.effective_available === 0, 'inventory-day--adjusted': slot.row.capacity_override !== null || slot.row.blocked_units > 0 }" :aria-label="`${dateLabel(slot.row.date)}: ${slot.row.effective_available} available, ${slot.row.blocked_units} blocked${slot.row.stop_sell ? ', stop sell' : ''}`" @click="openDay(slot.row, $event)">
                        <span class="inventory-day-top"><span class="inventory-day-number">{{ slot.number }}</span><span v-if="slot.row.stop_sell" class="inventory-day-stop">Stop sell</span></span>
                        <span class="inventory-day-available">{{ slot.row.effective_available }} <small>available</small></span>
                        <span class="inventory-day-meta">{{ slot.row.blocked_units }} blocked<span v-if="slot.row.capacity_override !== null"> · {{ slot.row.capacity_override }} override</span></span>
                    </button>
                    <div v-else class="inventory-day inventory-day--outside" aria-hidden="true"><span class="inventory-day-number">{{ slot.number }}</span></div>
                </template>
            </div>
        </div>
    </div>
    <p v-if="!calendarMonths.length" class="card p-3 text-muted">No dates in range — narrow the range (max {{ maxBulkDays }} days).</p>
</div>

<div v-if="roomTypeId && view === 'table'" class="card table-responsive mb-3"><table class="table mb-0"><thead><tr><th>Date</th><th>Base</th><th>Override</th><th>Blocked</th><th>Stop sell</th><th>Available</th><th>Note</th><th></th></tr></thead><tbody>
<tr v-for="row in rows" :key="row.date">
<td class="text-nowrap"><button type="button" class="btn btn-link btn-sm p-0" @click="openDay(row, $event)">{{ row.date }}</button></td>
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

<Teleport to="body">
    <div v-if="selectedDay" class="inventory-modal-backdrop" @click.self="closeDay" @keydown="handleModalKeydown">
        <section class="inventory-modal card" role="dialog" aria-modal="true" aria-labelledby="inventory-day-title" tabindex="-1">
            <div class="inventory-modal-header">
                <div><p class="small text-muted mb-1">Room inventory</p><h3 id="inventory-day-title" class="h4 mb-0">{{ dateLabel(selectedDay.date) }}</h3></div>
                <button ref="modalCloseButton" type="button" class="btn-close" aria-label="Close day details" :disabled="dayForm.processing" @click="closeDay"></button>
            </div>
            <form @submit.prevent="saveDay">
                <div class="inventory-modal-body">
                    <div class="inventory-day-summary">
                        <div><span>Base capacity</span><strong>{{ selectedDay.structural_capacity }}</strong></div>
                        <div><span>Blocked</span><strong>{{ selectedDay.blocked_units }}</strong></div>
                        <div><span>Available now</span><strong>{{ selectedDay.effective_available }}</strong></div>
                    </div>
                    <p v-if="selectedDay.stop_sell" class="alert alert-warning py-2 small">Stop sell is active. This date cannot be booked.</p>
                    <p v-if="dayForm.hasErrors" class="text-danger small mb-2">Please correct the highlighted inventory values.</p>
                    <div class="row g-3">
                        <div class="col-sm-6"><label class="form-label" for="modal-capacity">Override capacity</label><input id="modal-capacity" v-model="dayForm.capacity_override" type="number" min="0" :max="selectedDay.structural_capacity" placeholder="Use base capacity" class="form-control" :class="{ 'is-invalid': dayForm.errors.capacity_override }" /><div v-if="dayForm.errors.capacity_override" class="invalid-feedback">{{ dayForm.errors.capacity_override }}</div></div>
                        <div class="col-sm-6"><label class="form-label" for="modal-blocked">Blocked units</label><input id="modal-blocked" v-model="dayForm.blocked_units" type="number" min="0" :max="dayForm.capacity_override === '' || dayForm.capacity_override === null ? selectedDay.structural_capacity : dayForm.capacity_override" class="form-control" :class="{ 'is-invalid': dayForm.errors.blocked_units }" /><div v-if="dayForm.errors.blocked_units" class="invalid-feedback">{{ dayForm.errors.blocked_units }}</div></div>
                        <div class="col-12"><label class="form-check"><input v-model="dayForm.stop_sell" type="checkbox" class="form-check-input" /><span class="form-check-label">Stop sell for this date</span></label></div>
                        <div class="col-12"><label class="form-label" for="modal-note">Internal note</label><textarea id="modal-note" v-model="dayForm.note" maxlength="500" rows="2" class="form-control" :class="{ 'is-invalid': dayForm.errors.note }" placeholder="Optional note for your team"></textarea><div v-if="dayForm.errors.note" class="invalid-feedback">{{ dayForm.errors.note }}</div></div>
                    </div>
                    <p class="small text-muted mb-0 mt-3">Available now reflects saved inventory and existing reservations. Save to see your changes applied.</p>
                </div>
                <div class="inventory-modal-footer"><button type="button" class="btn btn-outline-secondary me-auto" :disabled="dayForm.processing" @click="clearDay">Clear date</button><button type="button" class="btn btn-light" :disabled="dayForm.processing" @click="closeDay">Cancel</button><button type="submit" class="btn btn-svtp" :disabled="dayForm.processing">{{ dayForm.processing ? 'Saving…' : 'Save changes' }}</button></div>
            </form>
        </section>
    </div>
</Teleport>
</div>
</template>

<style scoped>
.inventory-month { overflow: hidden; border-color: #e9e2d8; box-shadow: 0 8px 24px rgba(34, 43, 71, .05); }
.inventory-month-heading { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.25rem; background: #faf8f3; border-bottom: 1px solid #e9e2d8; }
.inventory-grid-wrap { overflow-x: auto; }
.inventory-grid { display: grid; grid-template-columns: repeat(7, minmax(105px, 1fr)); min-width: 735px; }
.inventory-weekday { padding: .65rem .75rem; color: #706e79; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; background: #fff; border-bottom: 1px solid #eee8df; }
.inventory-empty { min-height: 108px; background: #faf9f6; border-right: 1px solid #f0ece5; border-bottom: 1px solid #f0ece5; }
.inventory-day { display: flex; flex-direction: column; align-items: stretch; gap: .35rem; min-height: 108px; padding: .7rem .75rem; text-align: left; color: #26304b; background: #fff; border: 0; border-right: 1px solid #f0ece5; border-bottom: 1px solid #f0ece5; transition: background-color .15s ease, box-shadow .15s ease; }
button.inventory-day:hover, button.inventory-day:focus-visible { position: relative; z-index: 1; background: #f1f5ff; box-shadow: inset 0 0 0 2px #5275c7; outline: none; }
.inventory-day--adjusted { background: #fffaf0; }
.inventory-day--closed { background: #fff3f0; }
.inventory-day--outside { color: #aaa9ae; background: #faf9f6; }
.inventory-day-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .25rem; }
.inventory-day-number { font-weight: 700; }
.inventory-day-stop { color: #aa4934; font-size: .68rem; font-weight: 700; text-align: right; }
.inventory-day-available { margin-top: auto; font-size: 1.15rem; font-weight: 700; line-height: 1.1; }
.inventory-day-available small { color: #5e6875; font-size: .72rem; font-weight: 500; }
.inventory-day-meta { color: #69727e; font-size: .7rem; line-height: 1.3; }
.inventory-modal-backdrop { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(21, 29, 47, .58); }
.inventory-modal { width: min(100%, 570px); max-height: calc(100vh - 2rem); overflow-y: auto; box-shadow: 0 24px 60px rgba(18, 25, 40, .25); }
.inventory-modal-header, .inventory-modal-footer { display: flex; align-items: center; gap: .75rem; padding: 1rem 1.25rem; }
.inventory-modal-header { justify-content: space-between; border-bottom: 1px solid #eee8df; }
.inventory-modal-body { padding: 1.25rem; }
.inventory-modal-footer { justify-content: flex-end; border-top: 1px solid #eee8df; }
.inventory-day-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: .65rem; margin-bottom: 1.25rem; }
.inventory-day-summary > div { display: flex; flex-direction: column; gap: .25rem; padding: .8rem; background: #f8f6f2; border-radius: .6rem; }
.inventory-day-summary span { color: #647083; font-size: .75rem; }
.inventory-day-summary strong { color: #26304b; font-size: 1.25rem; }
@media (max-width: 576px) { .inventory-month-heading { padding: .8rem 1rem; } .inventory-day-summary { gap: .4rem; } .inventory-day-summary > div { padding: .55rem; } .inventory-modal-header, .inventory-modal-footer, .inventory-modal-body { padding: 1rem; } }
</style>
