<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    rateCard: { type: Object, default: null },
    vendors: { type: Array, default: () => [] },
    vehicleTypes: { type: Array, default: () => [] },
    tripTypes: { type: Array, default: () => [] },
    ruleCodes: { type: Array, default: () => [] },
    calculationTypes: { type: Array, default: () => [] },
    defaultCurrency: { type: String, default: 'INR' },
    fixedVendor: { type: Boolean, default: false },
    endpoint: { type: String, required: true },
    backUrl: { type: String, required: true },
});

const defaultCalculations = {
    base_fare: 'fixed', minimum_fare: 'fixed', distance_rate: 'per_km', extra_distance_rate: 'per_km',
    driver_allowance: 'fixed', night_charge: 'fixed', waiting_charge: 'per_hour',
    toll: 'included', parking: 'included', tax: 'percentage',
};

function normalizedRules() {
    const existing = new Map((props.rateCard?.rules ?? []).map(rule => [rule.code, rule]));
    return props.ruleCodes.map(({ value }) => {
        const rule = existing.get(value);
        return {
            enabled: Boolean(rule),
            code: value,
            calculation_type: rule?.calculation_type ?? defaultCalculations[value] ?? 'fixed',
            amount: rule?.amount ?? 0,
            included_quantity: rule?.included_quantity ?? '',
            unit: rule?.unit ?? '',
            configuration: {
                minimum_km: rule?.configuration?.minimum_km ?? '',
                minimum_km_basis: rule?.configuration?.minimum_km_basis ?? 'trip',
                starts_after_km: rule?.configuration?.starts_after_km ?? '',
                start_time: rule?.configuration?.start_time ?? '22:00',
                end_time: rule?.configuration?.end_time ?? '06:00',
                billing_increment_minutes: rule?.configuration?.billing_increment_minutes ?? 1,
            },
        };
    });
}

const form = useForm({
    vendor_profile_id: props.rateCard?.vendor_profile_id ?? '',
    vehicle_type_id: props.rateCard?.vehicle_type_id ?? '',
    name: props.rateCard?.name ?? '',
    trip_type: props.rateCard?.trip_type ?? props.tripTypes[0]?.value ?? 'one_way',
    currency: props.rateCard?.currency ?? props.defaultCurrency,
    is_active: props.rateCard ? Boolean(props.rateCard.is_active) : true,
    effective_from: props.rateCard?.effective_from?.slice(0, 16) ?? '',
    effective_until: props.rateCard?.effective_until?.slice(0, 16) ?? '',
    rules: normalizedRules(),
    packages: (props.rateCard?.rental_packages ?? []).map(item => ({ ...item, is_active: Boolean(item.is_active) })),
});

const isHourly = computed(() => form.trip_type === 'hourly');

function addPackage() {
    form.packages.push({
        name: '', included_hours: 4, included_km: 40, package_price: 0,
        extra_km_rate: 0, extra_hour_rate: 0, is_active: true, sort_order: form.packages.length,
    });
}

function removePackage(index) {
    form.packages.splice(index, 1);
}

function submit() {
    form.transform(data => ({
        ...data,
        vendor_profile_id: props.fixedVendor ? undefined : (data.vendor_profile_id || null),
        vehicle_type_id: data.vehicle_type_id || null,
        effective_from: data.effective_from || null,
        effective_until: data.effective_until || null,
        rules: data.rules.filter(rule => rule.enabled).map(({ enabled, ...rule }) => rule),
    }));

    const options = { preserveScroll: true, onFinish: () => form.transform(data => data) };
    props.rateCard ? form.put(props.endpoint, options) : form.post(props.endpoint, options);
}
</script>

<template>
    <form class="card" @submit.prevent="submit">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h2 class="h4 mb-1">{{ rateCard ? 'Edit taxi rate card' : 'New taxi rate card' }}</h2>
                    <p class="text-muted mb-0">Rates are resolved server-side and snapshotted on every booking.</p>
                </div>
                <Link :href="backUrl" class="btn btn-outline-secondary">Back</Link>
            </div>

            <div v-if="Object.keys(form.errors).length" class="alert alert-danger">
                <div v-for="(message, field) in form.errors" :key="field">{{ message }}</div>
            </div>

            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Name</label><input v-model="form.name" class="form-control" maxlength="120" required /></div>
                <div v-if="!fixedVendor" class="col-md-6">
                    <label class="form-label">Scope</label>
                    <select v-model="form.vendor_profile_id" class="form-select"><option value="">Platform</option><option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option></select>
                </div>
                <div class="col-md-4"><label class="form-label">Trip type</label><select v-model="form.trip_type" class="form-select"><option v-for="type in tripTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select></div>
                <div class="col-md-4"><label class="form-label">Vehicle type</label><select v-model="form.vehicle_type_id" class="form-select"><option value="">All vehicle types</option><option v-for="type in vehicleTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select></div>
                <div class="col-md-4"><label class="form-label">Currency</label><input v-model="form.currency" class="form-control text-uppercase" maxlength="3" pattern="[A-Za-z]{3}" required /></div>
                <div class="col-md-4"><label class="form-label">Effective from</label><input v-model="form.effective_from" type="datetime-local" class="form-control" /></div>
                <div class="col-md-4"><label class="form-label">Effective until</label><input v-model="form.effective_until" type="datetime-local" class="form-control" /></div>
                <div class="col-md-4 d-flex align-items-end"><label class="form-check mb-2"><input v-model="form.is_active" type="checkbox" class="form-check-input" /><span class="form-check-label ms-2">Active</span></label></div>
            </div>

            <hr class="my-4" />
            <h3 class="h5">Pricing rules</h3>
            <p class="text-muted small">Enable only the rules this card owns. Rules never merge with another card.</p>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Enabled</th><th>Rule</th><th>Calculation</th><th>Amount / rate</th><th>Included quantity</th><th>Configuration</th></tr></thead>
                    <tbody>
                        <tr v-for="(rule, index) in form.rules" :key="rule.code">
                            <td><input v-model="rule.enabled" type="checkbox" class="form-check-input" /></td>
                            <td>{{ ruleCodes.find(item => item.value === rule.code)?.label ?? rule.code }}</td>
                            <td><select v-model="rule.calculation_type" class="form-select form-select-sm" :disabled="!rule.enabled"><option v-for="type in calculationTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select></td>
                            <td><input v-model="rule.amount" type="number" min="0" step="0.0001" class="form-control form-control-sm" :disabled="!rule.enabled" /></td>
                            <td><input v-model="rule.included_quantity" type="number" min="0" step="0.01" class="form-control form-control-sm" :disabled="!rule.enabled" placeholder="e.g. included km" /></td>
                            <td class="rule-config">
                                <template v-if="rule.code === 'distance_rate'">
                                    <input v-model="rule.configuration.minimum_km" type="number" min="0" step="0.01" class="form-control form-control-sm" placeholder="Minimum km" />
                                    <select v-model="rule.configuration.minimum_km_basis" class="form-select form-select-sm"><option value="trip">Per trip</option><option value="day">Per day</option></select>
                                </template>
                                <input v-else-if="rule.code === 'extra_distance_rate'" v-model="rule.configuration.starts_after_km" type="number" min="0" step="0.01" class="form-control form-control-sm" placeholder="Starts after km" />
                                <template v-else-if="rule.code === 'night_charge'">
                                    <input v-model="rule.configuration.start_time" type="time" class="form-control form-control-sm" />
                                    <input v-model="rule.configuration.end_time" type="time" class="form-control form-control-sm" />
                                </template>
                                <input v-else-if="rule.code === 'waiting_charge'" v-model="rule.configuration.billing_increment_minutes" type="number" min="1" class="form-control form-control-sm" placeholder="Billing increment minutes" />
                                <span v-else class="text-muted small">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <template v-if="isHourly">
                <hr class="my-4" />
                <div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h5 mb-0">Local / hourly packages</h3><button type="button" class="btn btn-sm btn-outline-primary" @click="addPackage">Add package</button></div>
                <div v-for="(item, index) in form.packages" :key="item.id ?? `new-${index}`" class="border rounded p-3 mb-3">
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label small">Name</label><input v-model="item.name" class="form-control" required /></div>
                        <div class="col-md-2"><label class="form-label small">Included hours</label><input v-model="item.included_hours" type="number" min="0.01" step="0.01" class="form-control" /></div>
                        <div class="col-md-2"><label class="form-label small">Included km</label><input v-model="item.included_km" type="number" min="0" step="0.01" class="form-control" /></div>
                        <div class="col-md-2"><label class="form-label small">Package price</label><input v-model="item.package_price" type="number" min="0" step="0.01" class="form-control" /></div>
                        <div class="col-md-2"><label class="form-label small">Extra km rate</label><input v-model="item.extra_km_rate" type="number" min="0" step="0.0001" class="form-control" /></div>
                        <div class="col-md-2"><label class="form-label small">Extra hour rate</label><input v-model="item.extra_hour_rate" type="number" min="0" step="0.0001" class="form-control" /></div>
                        <div class="col-md-2 d-flex align-items-end"><label class="form-check mb-2"><input v-model="item.is_active" type="checkbox" class="form-check-input" /><span class="ms-2">Active</span></label></div>
                        <div class="col-md-2 d-flex align-items-end"><button type="button" class="btn btn-outline-danger" @click="removePackage(index)">Remove</button></div>
                    </div>
                </div>
            </template>
        </div>
        <div class="card-footer text-end"><button class="btn btn-primary" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save rate card' }}</button></div>
    </form>
</template>

<style scoped>
.card { border-radius: 1rem; }
.rule-config { min-width: 190px; }
.rule-config > * + * { margin-top: .35rem; }
</style>
