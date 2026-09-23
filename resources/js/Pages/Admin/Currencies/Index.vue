<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    currencies: { type: Array, default: () => [] },
    fxBase: { type: String, default: 'USD' },
    rates: { type: Array, default: () => [] },
    maxRateAgeHours: { type: Number, default: 24 },
    provider: { type: String, default: 'manual' },
});

function setDefault(currency) {
    router.patch(appUrl(`/admin/currencies/${currency.id}/default`), {}, { preserveScroll: true });
}

function toggle(currency) {
    router.patch(appUrl(`/admin/currencies/${currency.id}/toggle`), {}, { preserveScroll: true });
}

function removeCurrency(currency) {
    if (window.confirm(`Remove ${currency.code}? Historical records using it remain readable.`)) {
        router.delete(appUrl(`/admin/currencies/${currency.id}`), { preserveScroll: true });
    }
}

function removeRate(rate) {
    if (window.confirm(`Remove ${props.fxBase} → ${rate.quote_currency_code}? Displays fall back to authoritative amounts.`)) {
        router.delete(appUrl(`/admin/exchange-rates/${rate.id}`), { preserveScroll: true });
    }
}

function isStale(rate) {
    if (!rate.fetched_at) return true;
    const ageHours = (Date.now() - new Date(rate.fetched_at).getTime()) / 3600000;
    return ageHours > props.maxRateAgeHours;
}

const rateForm = useForm({ quote_currency_code: '', rate: '' });
function submitRate() {
    rateForm.post(appUrl('/admin/exchange-rates'), {
        preserveScroll: true,
        onSuccess: () => rateForm.reset(),
    });
}

const settingsForm = useForm({ fx_base: props.fxBase, max_rate_age_hours: props.maxRateAgeHours });
function submitSettings() {
    settingsForm.post(appUrl('/admin/exchange-rates/settings'), { preserveScroll: true });
}

const showRates = ref(true);
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div>
                <div class="text-muted small mb-1">Admin / System</div>
                <h2 class="mb-1">Currencies</h2>
                <p class="text-muted mb-0">Display currencies and manual exchange rates. Authoritative Hotel/Tour/Taxi prices are never rewritten here.</p>
            </div>
            <Link :href="appUrl('/admin/currencies/create')" class="btn btn-warning"><i class="bi bi-plus-lg me-1"></i>New Currency</Link>
        </div>

        <div class="card mt-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Currency</th>
                            <th>Code</th>
                            <th>Decimals</th>
                            <th>Status</th>
                            <th>Order</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="currency in currencies" :key="currency.id">
                            <td><strong>{{ currency.symbol }} {{ currency.name }}</strong></td>
                            <td><code>{{ currency.code }}</code></td>
                            <td>{{ currency.decimal_digits }}</td>
                            <td>
                                <span v-if="currency.is_default_display" class="badge text-bg-warning me-1">Default display</span>
                                <span class="badge" :class="currency.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ currency.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ currency.sort_order }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <Link :href="appUrl(`/admin/currencies/${currency.id}/edit`)" class="btn btn-outline-secondary">Edit</Link>
                                    <button v-if="!currency.is_default_display" type="button" class="btn btn-outline-primary" @click="setDefault(currency)">Set default</button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        :disabled="currency.is_default_display && currency.is_active"
                                        @click="toggle(currency)"
                                    >
                                        {{ currency.is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-danger"
                                        :disabled="currency.is_default_display"
                                        @click="removeCurrency(currency)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!currencies.length">
                            <td colspan="6" class="text-center text-muted py-4">No currencies configured.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-currency-exchange"></i>
                    <strong>Exchange Rates</strong>
                    <span class="badge text-bg-secondary">base {{ fxBase }}</span>
                    <span class="badge text-bg-info">provider: {{ provider }}</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="showRates = !showRates">
                    {{ showRates ? 'Hide' : 'Show' }}
                </button>
            </div>
            <div v-if="showRates" class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Pair</th>
                                <th>Rate</th>
                                <th>Source</th>
                                <th>Updated</th>
                                <th>Freshness</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="rate in rates" :key="rate.id">
                                <td><code>{{ rate.base_currency_code }} → {{ rate.quote_currency_code }}</code></td>
                                <td>{{ rate.rate }}</td>
                                <td><span class="badge text-bg-light text-dark border">{{ rate.source }}</span></td>
                                <td class="small">{{ rate.fetched_at ?? '—' }}</td>
                                <td>
                                    <span class="badge" :class="isStale(rate) ? 'text-bg-warning' : 'text-bg-success'">
                                        {{ isStale(rate) ? 'Stale' : 'Fresh' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="removeRate(rate)">Remove</button>
                                </td>
                            </tr>
                            <tr v-if="!rates.length">
                                <td colspan="6" class="text-center text-muted py-3">No rates yet — displays safely show authoritative amounts until you add manual rates.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <hr class="my-4" />

                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6>Set manual rate <small class="text-muted">(base {{ fxBase }})</small></h6>
                        <form class="row g-2 align-items-end" @submit.prevent="submitRate">
                            <div class="col-4">
                                <label class="form-label fw-semibold">Currency</label>
                                <input v-model="rateForm.quote_currency_code" type="text" class="form-control" maxlength="3" placeholder="INR" />
                                <div v-if="rateForm.errors.quote_currency_code" class="text-danger small mt-1">{{ rateForm.errors.quote_currency_code }}</div>
                            </div>
                            <div class="col-5">
                                <label class="form-label fw-semibold">1 {{ fxBase }} = ?</label>
                                <input v-model="rateForm.rate" type="number" step="any" min="0" class="form-control" placeholder="83.50" />
                                <div v-if="rateForm.errors.rate" class="text-danger small mt-1">{{ rateForm.errors.rate }}</div>
                            </div>
                            <div class="col-3">
                                <button type="submit" class="btn btn-primary w-100" :disabled="rateForm.processing">Save rate</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-6">
                        <h6>FX settings</h6>
                        <form class="row g-2 align-items-end" @submit.prevent="submitSettings">
                            <div class="col-4">
                                <label class="form-label fw-semibold">FX base</label>
                                <input v-model="settingsForm.fx_base" type="text" class="form-control" maxlength="3" />
                                <div v-if="settingsForm.errors.fx_base" class="text-danger small mt-1">{{ settingsForm.errors.fx_base }}</div>
                            </div>
                            <div class="col-5">
                                <label class="form-label fw-semibold">Max rate age (hours)</label>
                                <input v-model.number="settingsForm.max_rate_age_hours" type="number" min="1" max="720" class="form-control" />
                                <div v-if="settingsForm.errors.max_rate_age_hours" class="text-danger small mt-1">{{ settingsForm.errors.max_rate_age_hours }}</div>
                            </div>
                            <div class="col-3">
                                <button type="submit" class="btn btn-outline-secondary w-100" :disabled="settingsForm.processing">Save</button>
                            </div>
                        </form>
                        <div class="form-text mt-2">Stale rates still display with a stale flag — bookings are never blocked by display FX.</div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
