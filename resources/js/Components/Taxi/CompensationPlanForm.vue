<script setup>
import { appUrl } from '../../appUrl';
import { useForm, Link } from '@inertiajs/vue3';
const props = defineProps({ plan: Object, vendors: { type: Array, default: () => [] }, drivers: Array, vehicleTypes: Array, calculationTypes: Array, portal: String });
const endpoint = appUrl(`/${props.portal}/taxi/plans`);
const form = useForm({ name: props.plan?.name ?? '', vendor_profile_id: props.plan?.vendor_profile_id ?? '', driver_id: props.plan?.driver_id ?? '', vehicle_type_id: props.plan?.vehicle_type_id ?? '', currency: props.plan?.currency ?? 'INR', calculation_type: props.plan?.calculation_type ?? 'fixed', fixed_amount: props.plan?.fixed_amount ?? 0, percentage: props.plan?.percentage ?? 0, per_km_amount: props.plan?.per_km_amount ?? 0, per_hour_amount: props.plan?.per_hour_amount ?? 0, minimum_earning: props.plan?.minimum_earning ?? 0, allowance_passthrough: props.plan?.allowance_passthrough ?? false, is_active: props.plan?.is_active ?? true, effective_from: props.plan?.effective_from?.slice(0, 10) ?? '', effective_until: props.plan?.effective_until?.slice(0, 10) ?? '' });
function save() { props.plan ? form.put(appUrl(`/${props.portal}/taxi/plans/${props.plan.id}`)) : form.post(appUrl(`/${props.portal}/taxi/plans`)); }
</script>
<template>
 <div>
  <Link :href="endpoint">← Compensation plans</Link><h2 class="my-3">{{ plan ? 'Edit' : 'Create' }} compensation plan</h2>
  <form class="card p-3" @submit.prevent="save">
   <div v-for="(error, key) in form.errors" :key="key" class="alert alert-danger py-2">{{ error }}</div>
   <div class="row g-3">
    <div class="col-md-8"><label class="form-label" for="plan-name">Name</label><input id="plan-name" v-model="form.name" class="form-control" required maxlength="150" /></div>
    <div class="col-md-4"><label class="form-label" for="plan-currency">Currency</label><input id="plan-currency" v-model="form.currency" class="form-control" required minlength="3" maxlength="3" /></div>
    <div v-if="portal === 'admin'" class="col-md-6"><label class="form-label" for="plan-vendor">Vendor</label><select id="plan-vendor" v-model="form.vendor_profile_id" class="form-select"><option value="">Platform / selected driver's vendor</option><option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option></select></div>
    <div class="col-md-6"><label class="form-label" for="plan-driver">Driver</label><select id="plan-driver" v-model="form.driver_id" class="form-select"><option value="">Default for this scope</option><option v-for="driver in drivers" :key="driver.id" :value="driver.id">{{ driver.first_name }} {{ driver.last_name }}</option></select></div>
    <div class="col-md-6"><label class="form-label" for="plan-vehicle">Vehicle type</label><select id="plan-vehicle" v-model="form.vehicle_type_id" class="form-select"><option value="">All vehicle types</option><option v-for="type in vehicleTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select></div>
    <div class="col-md-6"><label class="form-label" for="plan-type">Calculation</label><select id="plan-type" v-model="form.calculation_type" class="form-select"><option v-for="type in calculationTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select></div>
    <div v-for="field in [['fixed_amount','Fixed amount'],['percentage','Percentage'],['per_km_amount','Per kilometer'],['per_hour_amount','Per hour'],['minimum_earning','Minimum earning']]" :key="field[0]" class="col-md-4"><label class="form-label" :for="field[0]">{{ field[1] }}</label><input :id="field[0]" v-model="form[field[0]]" type="number" min="0" step="0.01" class="form-control" /></div>
    <div class="col-md-6"><label class="form-label" for="effective-from">Effective from</label><input id="effective-from" v-model="form.effective_from" type="date" class="form-control" /></div>
    <div class="col-md-6"><label class="form-label" for="effective-until">Effective until</label><input id="effective-until" v-model="form.effective_until" type="date" class="form-control" /></div>
    <div class="col-12 d-flex gap-4"><label><input v-model="form.is_active" type="checkbox" /> Active</label><label><input v-model="form.allowance_passthrough" type="checkbox" /> Include driver allowance</label></div>
   </div><p class="text-muted small mt-3">Driver-specific plans take priority, followed by vendor defaults, then platform defaults. Changes apply to future earnings.</p>
   <button class="btn btn-svtp align-self-start" :disabled="form.processing">Save plan</button>
  </form>
 </div>
</template>
