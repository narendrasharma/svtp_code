<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import RatePlanForm from '../../../../Components/Hotel/RatePlanForm.vue';
import { appUrl } from '../../../../appUrl';

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
    router.post(appUrl('/vendor/hotel/seasons'), d, { preserveScroll: true, onSuccess: () => { seasonDrafts.value[planId] = null; } });
}

function load(params) { router.get(appUrl('/vendor/hotel/rate-plans'), params, { preserveState: true }); }
const weekdays = [['Sun', 0], ['Mon', 1], ['Tue', 2], ['Wed', 3], ['Thu', 4], ['Fri', 5], ['Sat', 6]];
</script>
<template><VendorLayout><div class="container-fluid py-3">
<Link :href="appUrl('/vendor/hotel/properties')">← My properties</Link>
<h2 class="my-3">My rate plans</h2>
<p class="text-muted">Commercial pricing for your own room types.</p>

<div class="card p-3 mb-3"><div class="row g-2 align-items-end">
<div class="col-md-5"><label class="form-label">Property</label><select :value="propertyId ?? ''" class="form-select" @change="load({ property_id: $event.target.value || undefined })"><option value="">Choose property</option><option v-for="p in properties" :key="p.id" :value="p.id">{{ p.name }}</option></select></div>
<div class="col-md-5"><label class="form-label">Room type</label><select :value="roomTypeId ?? ''" class="form-select" @change="load({ property_id: propertyId, room_type_id: $event.target.value || undefined })"><option value="">Choose room type</option><option v-for="r in roomTypes" :key="r.id" :value="r.id">{{ r.name }}</option></select></div>
<div class="col-md-2"><Link :href="appUrl('/vendor/hotel/daily-rates')" class="btn btn-outline-secondary w-100">Daily rates →</Link></div>
</div></div>

<div v-for="plan in plans" :key="plan.id" class="card p-3 mb-3">
<div class="d-flex flex-wrap gap-2 align-items-center">
<h5 class="mb-0">{{ plan.name }} <span class="font-monospace small text-muted">{{ plan.code }}</span></h5>
<span class="badge" :class="plan.is_active ? 'bg-success' : 'bg-secondary'">{{ plan.is_active ? 'Active' : 'Inactive' }}</span>
<span class="ms-auto d-flex gap-1">
<button class="btn btn-sm btn-outline-secondary" @click="editingPlan = editingPlan === plan.id ? null : plan.id">{{ editingPlan === plan.id ? 'Close' : 'Edit' }}</button>
<button class="btn btn-sm btn-outline-warning" @click="router.patch(appUrl(`/vendor/hotel/rate-plans/${plan.id}/toggle`))">{{ plan.is_active ? 'Deactivate' : 'Activate' }}</button>
<Link :href="appUrl(`/vendor/hotel/daily-rates?rate_plan_id=${plan.id}`)" class="btn btn-sm btn-outline-primary">Calendar</Link>
</span></div>
<p class="text-muted small mb-1">Base {{ propertyCurrency }} {{ plan.base_rate }} · {{ plan.meal_plan }} · {{ plan.cancellation_mode }}</p>
<div v-if="editingPlan === plan.id" class="border-top pt-2 mt-2"><RatePlanForm :plan="plan" :meal-plans="mealPlans" :cancellation-modes="cancellationModes" :property-currency="propertyCurrency" base-path="/vendor/hotel/rate-plans" /></div>

<div class="mt-2"><h6>Seasonal rules</h6>
<div v-for="s in plan.seasons" :key="s.id" class="d-flex flex-wrap gap-2 align-items-center border-bottom py-1 small">
<strong>{{ s.name }}</strong><span>{{ s.start_date }} → {{ s.end_date }}</span>
<span class="font-monospace">{{ s.adjustment_type }} {{ s.adjustment_value }}</span>
<span>priority {{ s.priority }}</span>
<span class="ms-auto d-flex gap-1">
<button class="btn btn-sm btn-outline-warning" @click="router.patch(appUrl(`/vendor/hotel/seasons/${s.id}/toggle`))">{{ s.is_active ? 'Off' : 'On' }}</button>
<button class="btn btn-sm btn-outline-danger" @click="router.delete(appUrl(`/vendor/hotel/seasons/${s.id}`))">Remove</button>
</span></div>
<div class="row g-2 mt-1 align-items-end">
<div class="col-md-3"><input v-model="seasonForm(plan.id).name" maxlength="100" placeholder="Season name *" class="form-control form-control-sm" /></div>
<div class="col-md-2"><input v-model="seasonForm(plan.id).start_date" type="date" class="form-control form-control-sm" /></div>
<div class="col-md-2"><input v-model="seasonForm(plan.id).end_date" type="date" class="form-control form-control-sm" /></div>
<div class="col-md-2"><select v-model="seasonForm(plan.id).adjustment_type" class="form-select form-select-sm"><option v-for="t in seasonTypes" :key="t" :value="t">{{ t }}</option></select></div>
<div class="col-md-1"><input v-model="seasonForm(plan.id).adjustment_value" type="number" step="0.01" placeholder="Value *" class="form-control form-control-sm" /></div>
<div class="col-md-2"><button class="btn btn-sm btn-outline-primary" @click="saveSeason(plan.id)">Add rule</button></div>
<div class="col-12"><span class="small text-muted me-2">Weekdays (empty = all):</span><label v-for="[label, day] in weekdays" :key="day" class="form-check form-check-inline small"><input v-model="seasonForm(plan.id).weekdays" :value="day" type="checkbox" class="form-check-input" /> {{ label }}</label></div>
</div></div>
</div>

<div v-if="roomTypeId" class="card p-3"><h5>New rate plan <span class="text-muted small">(currency {{ propertyCurrency }})</span></h5>
<RatePlanForm :room-type-id="roomTypeId" :property-currency="propertyCurrency" :meal-plans="mealPlans" :cancellation-modes="cancellationModes" base-path="/vendor/hotel/rate-plans" /></div>
</div></VendorLayout></template>
