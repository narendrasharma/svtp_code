<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import MoneyDisplay from '../../Components/Public/UI/MoneyDisplay.vue';

const props = defineProps({
    vehicleTypes: { type: Array, default: () => [] },
    tripTypes: { type: Array, default: () => [] },
    currency: { type: String, default: 'INR' },
    customer: { type: Object, default: null },
});

const { t, locale } = useLocalization();
const quote = ref(null);
const quoteLoading = ref(false);
const quoteError = ref('');
const selectedOption = ref(null);

const form = useForm({
    trip_type: props.tripTypes[0]?.value || 'one_way', pickup_at: '', pickup_address: '', pickup_lat: null, pickup_lng: null,
    drop_address: '', drop_lat: null, drop_lng: null, passenger_count: 1, luggage_count: 0, vehicle_type_id: '',
    airport_direction: '', flight_number: '', airline: '', terminal: '', customer_name: props.customer?.name || '',
    customer_phone: props.customer?.phone || '', customer_email: props.customer?.email || '', special_instructions: '',
});

const canQuote = computed(() => Boolean(form.pickup_at && form.pickup_address && form.drop_address && form.passenger_count > 0));
const canBook = computed(() => Boolean(selectedOption.value && form.customer_name && form.customer_phone && !form.processing));

watch(() => [form.trip_type, form.pickup_at, form.pickup_address, form.pickup_lat, form.pickup_lng, form.drop_address, form.drop_lat, form.drop_lng, form.passenger_count, form.luggage_count, form.airport_direction], () => {
    quote.value = null;
    selectedOption.value = null;
    form.vehicle_type_id = '';
});

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(locale.value || 'en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function errorFor(field) { return form.errors[field] || ''; }

async function getQuote() {
    if (!canQuote.value) return;
    quoteLoading.value = true; quoteError.value = ''; selectedOption.value = null; form.vehicle_type_id = '';
    try {
        const response = await axios.post(appUrl('/taxi/quote'), {
            trip_type: form.trip_type, pickup_at: form.pickup_at, pickup_address: form.pickup_address,
            pickup_lat: form.pickup_lat, pickup_lng: form.pickup_lng, drop_address: form.drop_address,
            drop_lat: form.drop_lat, drop_lng: form.drop_lng, passenger_count: form.passenger_count,
            luggage_count: form.luggage_count, airport_direction: form.airport_direction,
        });
        quote.value = response.data;
        if (!response.data.options?.length) quoteError.value = t('common.no_taxi_options', 'No suitable taxi options are available for these passengers.');
    } catch (error) {
        quote.value = null;
        quoteError.value = Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || t('common.taxi_quote_error', 'We could not price this route. Please review the trip details and try again.');
    } finally { quoteLoading.value = false; }
}

function choose(option) { selectedOption.value = option; form.vehicle_type_id = option.vehicle.id; }
function submit() { form.post(appUrl('/taxi/book'), { preserveScroll: true }); }
</script>

<template>
    <PublicLayout main-class="public-taxi-page">
        <SeoHead :title="t('common.taxi', 'Taxi')" :description="t('common.taxi_page_description', 'Plan a comfortable ride with a clear route and server-priced fare.')" :noindex="true" />
        <main class="public-container taxi-booking-shell">
            <header class="taxi-booking-hero"><div><span class="public-eyebrow">{{ t('common.route_first', 'Route first') }}</span><h1 class="public-heading public-heading--1">{{ t('common.plan_your_taxi', 'Plan your taxi ride') }}</h1><p>{{ t('common.taxi_booking_intro', 'Add your route, compare genuine vehicle options, and confirm your details.') }}</p></div><div class="taxi-booking-hero__mark" aria-hidden="true"><i class="bi bi-taxi-front-fill"></i></div></header>
            <form class="taxi-booking-layout" @submit.prevent="submit">
                <section class="taxi-booking-main">
                    <div class="taxi-panel">
                        <div class="taxi-panel__heading"><span class="taxi-step">01</span><div><h2>{{ t('common.route_details', 'Route details') }}</h2><p>{{ t('common.taxi_route_note', 'Addresses stay with the booking. Coordinates are used when supplied by the location service.') }}</p></div></div>
                        <div class="row g-3 mb-2" v-if="tripTypes.length > 1"><div class="col-md-5"><label class="form-label" for="trip_type">{{ t('common.trip_type', 'Trip type') }}</label><select id="trip_type" v-model="form.trip_type" class="form-select"><option v-for="type in tripTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select></div></div>
                        <div class="taxi-route-fields"><div class="taxi-field"><label for="pickup_address">{{ t('common.pickup', 'Pickup') }}</label><input id="pickup_address" v-model="form.pickup_address" type="text" maxlength="500" autocomplete="street-address" :aria-invalid="Boolean(errorFor('pickup_address'))" required /><small v-if="errorFor('pickup_address')" class="taxi-error">{{ errorFor('pickup_address') }}</small></div><div class="taxi-route-line" aria-hidden="true"><span></span></div><div class="taxi-field"><label for="drop_address">{{ t('common.drop', 'Dropoff') }}</label><input id="drop_address" v-model="form.drop_address" type="text" maxlength="500" autocomplete="street-address" :aria-invalid="Boolean(errorFor('drop_address'))" required /><small v-if="errorFor('drop_address')" class="taxi-error">{{ errorFor('drop_address') }}</small></div></div>
                        <div class="row g-3 mt-1"><div class="col-md-5"><label class="form-label" for="pickup_at">{{ t('common.pickup_time', 'Pickup date and time') }}</label><input id="pickup_at" v-model="form.pickup_at" type="datetime-local" class="form-control" required /><small v-if="errorFor('pickup_at')" class="taxi-error">{{ errorFor('pickup_at') }}</small></div><div class="col-md-3"><label class="form-label" for="passenger_count">{{ t('common.passengers', 'Passengers') }}</label><input id="passenger_count" v-model.number="form.passenger_count" type="number" min="1" max="60" class="form-control" required /></div><div class="col-md-4"><label class="form-label" for="luggage_count">{{ t('common.luggage', 'Luggage') }}</label><input id="luggage_count" v-model.number="form.luggage_count" type="number" min="0" max="60" class="form-control" /></div></div>
                        <div class="row g-3 mt-1" v-if="form.trip_type === 'airport_transfer'"><div class="col-md-4"><label class="form-label" for="airport_direction">{{ t('common.airport_direction', 'Airport direction') }}</label><select id="airport_direction" v-model="form.airport_direction" class="form-select" required><option value="">{{ t('common.choose_one', 'Choose one') }}</option><option value="airport_pickup">{{ t('common.airport_pickup', 'Airport pickup') }}</option><option value="airport_drop">{{ t('common.airport_drop', 'Airport drop') }}</option></select></div><div class="col-md-4"><label class="form-label" for="flight_number">{{ t('common.flight_number', 'Flight number') }}</label><input id="flight_number" v-model="form.flight_number" class="form-control" maxlength="20" /></div><div class="col-md-4"><label class="form-label" for="terminal">{{ t('common.terminal', 'Terminal') }}</label><input id="terminal" v-model="form.terminal" class="form-control" maxlength="40" /></div></div>
                        <button type="button" class="btn btn-svtp mt-4" :disabled="quoteLoading || !canQuote" @click="getQuote">{{ quoteLoading ? t('common.loading', 'Calculating…') : t('common.see_fares', 'See fares') }}</button><p v-if="quote?.route?.available" class="taxi-route-meta">{{ quote.route.distance_km }} km · {{ quote.route.duration_minutes }} min {{ t('common.server_route_estimate', 'server route estimate') }}</p>
                    </div>
                    <div class="taxi-panel" v-if="quote || quoteError"><div class="taxi-panel__heading"><span class="taxi-step">02</span><div><h2>{{ t('common.choose_vehicle', 'Choose a vehicle') }}</h2><p>{{ t('common.taxi_options_note', 'Options and fares come from the current taxi pricing records.') }}</p></div></div><div v-if="quoteError" class="taxi-alert" role="alert">{{ quoteError }}</div><div v-else class="taxi-option-list"><button v-for="option in quote.options" :key="option.vehicle.id" type="button" class="taxi-option" :class="{ 'is-selected': selectedOption?.vehicle.id === option.vehicle.id }" @click="choose(option)" :aria-pressed="selectedOption?.vehicle.id === option.vehicle.id"><span class="taxi-option__icon"><i :class="option.vehicle.icon || 'bi bi-car-front-fill'" aria-hidden="true"></i></span><span class="taxi-option__body"><strong>{{ option.vehicle.name }}</strong><small>{{ option.vehicle.passenger_capacity }} {{ t('common.passengers', 'passengers') }}<span v-if="option.vehicle.luggage_capacity"> · {{ option.vehicle.luggage_capacity }} {{ t('common.luggage', 'luggage') }}</span></small><small v-if="option.vehicle.description">{{ option.vehicle.description }}</small></span><span class="taxi-option__price"><MoneyDisplay :money="option.total" /><small>{{ t('common.total', 'Total') }}</small></span></button></div></div>
                    <div class="taxi-panel" v-if="selectedOption"><div class="taxi-panel__heading"><span class="taxi-step">03</span><div><h2>{{ t('common.customer_details', 'Customer details') }}</h2><p>{{ t('common.guest_booking_note', 'You can continue as a guest.') }}</p></div></div><div class="row g-3"><div class="col-md-4"><label class="form-label" for="customer_name">{{ t('common.name', 'Name') }}</label><input id="customer_name" v-model="form.customer_name" class="form-control" maxlength="150" autocomplete="name" required /><small v-if="errorFor('customer_name')" class="taxi-error">{{ errorFor('customer_name') }}</small></div><div class="col-md-4"><label class="form-label" for="customer_phone">{{ t('common.phone', 'Phone') }}</label><input id="customer_phone" v-model="form.customer_phone" class="form-control" maxlength="30" autocomplete="tel" inputmode="tel" required /><small v-if="errorFor('customer_phone')" class="taxi-error">{{ errorFor('customer_phone') }}</small></div><div class="col-md-4"><label class="form-label" for="customer_email">{{ t('common.email', 'Email') }}</label><input id="customer_email" v-model="form.customer_email" type="email" class="form-control" maxlength="150" autocomplete="email" /></div><div class="col-12"><label class="form-label" for="special_instructions">{{ t('common.special_requests', 'Special requests') }}</label><textarea id="special_instructions" v-model="form.special_instructions" class="form-control" rows="3" maxlength="2000"></textarea></div></div></div>
                </section>
                <aside class="taxi-booking-summary"><div class="taxi-summary-card"><span class="public-eyebrow">{{ t('common.trip_summary', 'Trip summary') }}</span><h2>{{ t('common.your_taxi', 'Your taxi') }}</h2><div class="taxi-summary-route"><span>{{ form.pickup_address || t('common.pickup', 'Pickup') }}</span><i class="bi bi-arrow-down" aria-hidden="true"></i><span>{{ form.drop_address || t('common.drop', 'Dropoff') }}</span></div><dl><div><dt>{{ t('common.pickup_time', 'Pickup time') }}</dt><dd>{{ formatDate(form.pickup_at) }}</dd></div><div><dt>{{ t('common.passengers', 'Passengers') }}</dt><dd>{{ form.passenger_count || 0 }}</dd></div><div v-if="selectedOption"><dt>{{ t('common.vehicle', 'Vehicle') }}</dt><dd>{{ selectedOption.vehicle.name }}</dd></div></dl><div class="taxi-summary-total" v-if="selectedOption"><span>{{ t('common.total', 'Total') }}</span><MoneyDisplay :money="selectedOption.total" /></div><button type="submit" class="btn btn-svtp w-100 mt-4" :disabled="!canBook">{{ form.processing ? t('common.loading', 'Working…') : t('common.book_taxi', 'Book taxi') }}</button><p class="taxi-summary-note">{{ t('common.taxi_payment_note', 'Payment status is recorded by the booking team. No online payment is taken here.') }}</p></div></aside>
            </form>
        </main>
    </PublicLayout>
</template>
