<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import RatePlanForm from '../../../../Components/Hotel/RatePlanForm.vue';
import { appUrl } from '../../../../appUrl';
import { Link } from '@inertiajs/vue3';

const props = defineProps({ properties: Array, roomTypes: Array, propertyId: Number, roomTypeId: Number, propertyCurrency: String, plans: Array, mealPlans: Array, cancellationModes: Array, seasonTypes: Array });

const editingPlan = ref(null);
const seasonDrafts = ref({});

function seasonForm(planId) {
    if (!seasonDrafts.value[planId]) {
        seasonDrafts.value[planId] = { hotel_rate_plan_id: planId, name: '', start_date: '', end_date: '', adjustment_type: 'percentage', adjustment_value: '', priority: 0, weekdays: [], is_active: true };
    }
    return seasonDrafts.value[planId];
}

function saveSeason(planId) {
    const d = { ...seasonForm(planId) };
    d.applicable_weekdays = d.weekdays.length ? d.weekdays.map(Number) : null;
    delete d.weekdays;
    if (d.adjustment_value === '') d.adjustment_value = 0;
    router.post(appUrl('/admin/hotel/seasons'), d, { preserveScroll: true, onSuccess: () => { seasonDrafts.value[planId] = null; } });
}

function toggleSeason(id) { router.patch(appUrl(`/admin/hotel/seasons/${id}/toggle`), {}, { preserveScroll: true }); }
function removeSeason(id) { if (confirm('Remove this seasonal rule?')) router.delete(appUrl(`/admin/hotel/seasons/${id}`), { preserveScroll: true }); }
function load(params) { router.get(appUrl('/admin/hotel/rate-plans'), params, { preserveState: true }); }
const weekdays = [['Sun', 0], ['Mon', 1], ['Tue', 2], ['Wed', 3], ['Thu', 4], ['Fri', 5], ['Sat', 6]];
</script>
<template><AdminLayout><div class="container-fluid py-3">
<Link :href="appUrl('/admin/hotel/properties')">← All properties</Link>
<h2 class="my-3">Hotel rate plans</h2>
<p class="text-muted">Commercial pricing per room type. Inventory (sellable rooms) lives separately under Inventory.</p>

<div class="card p-3 mb-3"><div class="row g-2 align-items-end">
<div class="col-md-5"><label class="form-label">Property</label><select :value="propertyId ?? ''" class="form-select" @change="load({ property_id: $event.target.value || undefined })"><option value="">Choose property</option><option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }} ({{ p.currency ?? '—' }})</option></select></div>
<div class="col-md-5"><label class="form-label">Room type</label><select :value="roomTypeId ?? ''" class="form-select" @change="load({ property_id: propertyId, room_type_id: $event.target.value || undefined })"><option value="">Choose room type</option><option v-for="r in roomTypes" :key="r.id" :value="r.id">{{ r.name }}</option></select></div>
<div class="col-md-2"><Link :href="appUrl('/admin/hotel/daily-rates')" class="btn btn-outline-secondary w-100">Daily rates →</Link></div>
</div></div>

<div v-for="plan in plans" :key="plan.id" class="card p-3 mb-3">
<div class="d-flex flex-wrap gap-2 align-items-center">
<h5 class="mb-0">{{ plan.name }} <span class="font-monospace small text-muted">{{ plan.code }}</span></h5>
<span class="badge" :class="plan.is_active ? 'bg-success' : 'bg-secondary'">{{ plan.is_active ? 'Active' : 'Inactive' }}</span>
<span class="badge bg-info">{{ plan.meal_plan }}</span>
<span class="badge" :class="plan.cancellation_mode === 'flexible' ? 'bg-primary' : 'bg-warning text-dark'">{{ plan.cancellation_mode }}</span>
<span class="ms-auto d-flex gap-1">
<button class="btn btn-sm btn-outline-secondary" @click="editingPlan = editingPlan === plan.id ? null : plan.id">{{ editingPlan === plan.id ? 'Close' : 'Edit' }}</button>
<button class="btn btn-sm btn-outline-warning" @click="router.patch(appUrl(`/admin/hotel/rate-plans/${plan.id}/toggle`))">{{ plan.is_active ? 'Deactivate' : 'Activate' }}</button>
<Link :href="appUrl(`/admin/hotel/daily-rates?rate_plan_id=${plan.id}`)" class="btn btn-sm btn-outline-primary">Calendar</Link>
</span></div>
<p class="text-muted small mb-1">Base {{ propertyCurrency }} {{ plan.base_rate }} · incl. {{ plan.base_adults }}A + {{ plan.base_children }}C · extra A {{ plan.extra_adult_rate }} / C {{ plan.extra_child_rate }}<span v-if="plan.minimum_stay"> · min {{ plan.minimum_stay }}n</span><span v-if="plan.maximum_stay"> · max {{ plan.maximum_stay }}n</span><span v-if="plan.valid_from"> · from {{ plan.valid_from }}</span><span v-if="plan.valid_until"> · until {{ plan.valid_until }}</span></p>
<div v-if="editingPlan === plan.id" class="border-top pt-2 mt-2"><RatePlanForm :plan="plan" :meal-plans="mealPlans" :cancellation-modes="cancellationModes" :property-currency="propertyCurrency" base-path="/admin/hotel/rate-plans" /></div>

<div class="mt-2"><h6>Seasonal rules <span class="text-muted fw-normal">(winner: highest priority, then oldest)</span></h6>
<div v-for="s in plan.seasons" :key="s.id" class="d-flex flex-wrap gap-2 align-items-center border-bottom py-1 small">
<strong>{{ s.name }}</strong><span>{{ s.start_date }} → {{ s.end_date }}</span>
<span class="font-monospace">{{ s.adjustment_type }} {{ s.adjustment_value }}</span>
<span>priority {{ s.priority }}</span>
<span v-if="s.applicable_weekdays?.length" class="text-muted">days {{ s.applicable_weekdays.join(',') }}</span>
<span v-else class="text-muted">all days</span>
<span class="badge" :class="s.is_active ? 'bg-success' : 'bg-secondary'">{{ s.is_active ? 'On' : 'Off' }}</span>
<span class="ms-auto d-flex gap-1">
<button class="btn btn-sm btn-outline-warning" @click="toggleSeason(s.id)">{{ s.is_active ? 'Off' : 'On' }}</button>
<button class="btn btn-sm btn-outline-danger" @click="removeSeason(s.id)">Remove</button>
</span></div>
<div class="row g-2 mt-1 align-items-end">
<div class="col-md-2"><input v-model="seasonForm(plan.id).name" maxlength="100" placeholder="Season name *" class="form-control form-control-sm" /></div>
<div class="col-md-2"><input v-model="seasonForm(plan.id).start_date" type="date" class="form-control form-control-sm" /></div>
<div class="col-md-2"><input v-model="seasonForm(plan.id).end_date" type="date" class="form-control form-control-sm" /></div>
<div class="col-md-2"><select v-model="seasonForm(plan.id).adjustment_type" class="form-select form-select-sm"><option v-for="t in seasonTypes" :key="t" :value="t">{{ t }}</option></select></div>
<div class="col-md-1"><input v-model="seasonForm(plan.id).adjustment_value" type="number" step="0.01" placeholder="Value *" class="form-control form-control-sm" /></div>
<div class="col-md-1"><input v-model="seasonForm(plan.id).priority" type="number" class="form-control form-control-sm" title="Priority" /></div>
<div class="col-md-2"><button class="btn btn-sm btn-outline-primary" @click="saveSeason(plan.id)">Add rule</button></div>
<div class="col-12"><span class="small text-muted me-2">Weekdays (empty = all):</span><label v-for="[label, day] in weekdays" :key="day" class="form-check form-check-inline small"><input v-model="seasonForm(plan.id).weekdays" :value="day" type="checkbox" class="form-check-input" /> {{ label }}</label></div>
</div></div>
</div>
<p v-if="roomTypeId && !plans.length" class="text-muted">No rate plans yet for this room type.</p>

<div v-if="roomTypeId" class="card p-3"><h5>New rate plan <span class="text-muted small">(currency {{ propertyCurrency }})</span></h5>
<RatePlanForm :room-type-id="roomTypeId" :property-id="propertyId" :property-currency="propertyCurrency" :meal-plans="mealPlans" :cancellation-modes="cancellationModes" base-path="/admin/hotel/rate-plans" :show-property-room="true" /></div>
</div></AdminLayout></template>
