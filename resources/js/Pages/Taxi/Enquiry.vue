<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import MoneyDisplay from '../../Components/Public/UI/MoneyDisplay.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import DateField from '../../Components/Public/Search/DateField.vue';
import TimeField from '../../Components/Public/Search/TimeField.vue';
import QuantitySelector from '../../Components/Public/Search/QuantitySelector.vue';
import LocationField from '../../Components/Public/Search/LocationField.vue';
import { mediaUrl } from '../../Components/Public/homepage';

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
const pickupDate = ref('');
const pickupTime = ref('');
const editingTrip = ref(true);

const today = (() => {
    const date = new Date();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
})();

const form = useForm({
    trip_type: props.tripTypes[0]?.value || 'one_way', pickup_at: '', pickup_address: '', pickup_lat: null, pickup_lng: null,
    drop_address: '', drop_lat: null, drop_lng: null, passenger_count: 1, luggage_count: 0, vehicle_type_id: '',
    airport_direction: '', flight_number: '', airline: '', terminal: '', customer_name: props.customer?.name || '',
    customer_phone: props.customer?.phone || '', customer_email: props.customer?.email || '', special_instructions: '',
});

onMounted(() => {
    const query = new URLSearchParams(window.location.search);
    form.pickup_address = query.get('pickup') || '';
    form.drop_address = query.get('drop') || '';
    pickupDate.value = query.get('date') || '';
    pickupTime.value = query.get('time') || '';
    form.passenger_count = Math.max(1, Number(query.get('passengers')) || 1);
});

const canQuote = computed(() => Boolean(form.pickup_at && form.pickup_address && form.drop_address && form.passenger_count > 0));
const canBook = computed(() => Boolean(selectedOption.value && form.customer_name && form.customer_phone && !form.processing));

watch([pickupDate, pickupTime], ([date, time]) => {
    form.pickup_at = date && time ? `${date}T${time}` : '';
});

watch(() => [form.trip_type, form.pickup_at, form.pickup_address, form.pickup_lat, form.pickup_lng, form.drop_address, form.drop_lat, form.drop_lng, form.passenger_count, form.luggage_count, form.airport_direction], () => {
    quote.value = null;
    selectedOption.value = null;
    form.vehicle_type_id = '';
});

function formatDate(value) {
    if (!value) return '—';

    const [date, time] = String(value).split('T');

    try {
        const displayDate = new Intl.DateTimeFormat(locale.value || 'en', { dateStyle: 'medium' }).format(new Date(`${date}T12:00:00`));

        return `${displayDate} · ${time || '—'}`;
    } catch {
        return String(value).replace('T', ' · ');
    }
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
        if (response.data.options?.length) editingTrip.value = false;
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
            <header class="taxi-booking-hero">
                <span class="taxi-hero-eyebrow"><i class="bi bi-taxi-front-fill" aria-hidden="true"></i>{{ t('common.taxi', 'Taxi') }}</span>
                <h1 class="public-heading public-heading--1">{{ t('common.plan_your_taxi', 'Plan your taxi ride') }}</h1>
                <p>{{ t('common.taxi_booking_intro', 'Add your route, compare genuine vehicle options, and confirm your details.') }}</p>
            </header>
            <div class="taxi-journey-steps" :aria-label="t('common.booking_progress', 'Booking progress')">
                <span :class="{ 'is-current': !quote }"><b>01</b>{{ t('common.route_details', 'Trip details') }}</span>
                <span :class="{ 'is-current': quote && !selectedOption }"><b>02</b>{{ t('common.choose_vehicle', 'Choose your ride') }}</span>
                <span :class="{ 'is-current': selectedOption }"><b>03</b>{{ t('common.customer_details', 'Booking details') }}</span>
            </div>
            <form class="taxi-booking-layout" @submit.prevent="submit">
                <section v-if="editingTrip || !quote" class="taxi-panel taxi-search-panel" aria-labelledby="taxi-trip-title">
                    <div class="taxi-panel__heading"><span class="taxi-step">01</span><div><h2 id="taxi-trip-title">{{ t('common.route_details', 'Trip details') }}</h2><p>{{ t('common.taxi_route_note', 'Enter pickup and dropoff addresses to see available fares.') }}</p></div></div>
                    <fieldset v-if="tripTypes.length > 1" class="taxi-trip-types">
                        <legend>{{ t('common.trip_type', 'Trip type') }}</legend>
                        <label v-for="type in tripTypes" :key="type.value" :class="{ 'is-active': form.trip_type === type.value }"><input v-model="form.trip_type" type="radio" name="trip_type" :value="type.value"><span>{{ type.label }}</span></label>
                    </fieldset>
                    <div class="taxi-route-fields">
                        <div class="taxi-field"><LocationField id="pickup_address" v-model="form.pickup_address" :label="t('common.pickup', 'Pickup')" placeholder="City, place or address" required @input="form.pickup_lat = null; form.pickup_lng = null" /><small v-if="errorFor('pickup_address')" class="taxi-error">{{ errorFor('pickup_address') }}</small></div>
                        <i class="bi bi-arrow-right taxi-route-arrow" aria-hidden="true"></i>
                        <div class="taxi-field"><LocationField id="drop_address" v-model="form.drop_address" :label="t('common.drop', 'Dropoff')" placeholder="City, place or address" required @input="form.drop_lat = null; form.drop_lng = null" /><small v-if="errorFor('drop_address')" class="taxi-error">{{ errorFor('drop_address') }}</small></div>
                    </div>
                    <div class="taxi-when-fields">
                        <DateField v-model="pickupDate" :label="t('common.pickup_date', 'Pickup date')" id="pickup_date" :min="today" :required="true" :error="errorFor('pickup_at')" />
                        <TimeField v-model="pickupTime" :label="t('common.pickup_time_field', 'Pickup time')" id="pickup_time" :required="true" />
                        <QuantitySelector v-model="form.passenger_count" :label="t('common.passengers', 'Passengers')" id="passenger_count" :min="1" :max="60" :decrease-label="t('common.decrease', 'Decrease')" :increase-label="t('common.increase', 'Increase')" />
                        <QuantitySelector v-model="form.luggage_count" :label="t('common.luggage', 'Luggage')" id="luggage_count" :min="0" :max="60" :decrease-label="t('common.decrease', 'Decrease')" :increase-label="t('common.increase', 'Increase')" />
                    </div>
                    <div v-if="form.trip_type === 'airport_transfer'" class="taxi-airport-fields"><div><label class="form-label" for="airport_direction">{{ t('common.airport_direction', 'Airport direction') }}</label><select id="airport_direction" v-model="form.airport_direction" class="form-select" required><option value="">{{ t('common.choose_one', 'Choose one') }}</option><option value="airport_pickup">{{ t('common.airport_pickup', 'Airport pickup') }}</option><option value="airport_drop">{{ t('common.airport_drop', 'Airport drop') }}</option></select></div><div><label class="form-label" for="flight_number">{{ t('common.flight_number', 'Flight number') }}</label><input id="flight_number" v-model="form.flight_number" class="form-control" maxlength="20"></div><div><label class="form-label" for="terminal">{{ t('common.terminal', 'Terminal') }}</label><input id="terminal" v-model="form.terminal" class="form-control" maxlength="40"></div></div>
                    <div class="taxi-search-action"><button type="button" class="public-button public-button--primary public-button--lg" :disabled="quoteLoading || !canQuote" @click="getQuote">{{ quoteLoading ? t('common.loading', 'Calculating…') : t('common.see_fares', 'View fares') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></button><span>{{ t('common.taxi_options_note', 'Options and fares come from the current taxi pricing records.') }}</span></div>
                    <div v-if="quoteError" class="taxi-alert" role="alert">{{ quoteError }}</div>
                </section>
                <section v-else class="taxi-trip-recap" aria-label="Trip summary">
                    <div><span class="public-eyebrow">{{ t('common.trip_summary', 'Trip summary') }}</span><strong>{{ form.pickup_address }} <i class="bi bi-arrow-right" aria-hidden="true"></i> {{ form.drop_address }}</strong><small>{{ formatDate(form.pickup_at) }} · {{ form.passenger_count }} {{ t('common.passengers', 'passengers') }}<span v-if="quote?.route?.available"> · {{ quote.route.distance_km }} km</span></small></div>
                    <button type="button" class="taxi-modify-button" @click="editingTrip = true">{{ t('common.modify_trip', 'Modify trip') }}</button>
                </section>
                <section v-if="quote?.options?.length && !editingTrip" class="taxi-results" aria-labelledby="taxi-options-title">
                    <div class="taxi-results-heading"><div><span class="public-eyebrow">02 / {{ t('common.choose_vehicle', 'Choose your ride') }}</span><h2 id="taxi-options-title">{{ t('common.choose_vehicle', 'Choose your ride') }}</h2><p>{{ t('common.representative_vehicle', 'Images represent vehicle categories; your assigned vehicle may differ.') }}</p></div><span v-if="quote?.route?.available" class="taxi-route-meta">{{ quote.route.distance_km }} km · {{ quote.route.duration_minutes }} min</span></div>
                    <div class="taxi-option-list"><button v-for="option in quote.options" :key="option.vehicle.id" type="button" class="taxi-option" :class="{ 'is-selected': selectedOption?.vehicle.id === option.vehicle.id }" :aria-pressed="selectedOption?.vehicle.id === option.vehicle.id" @click="choose(option)"><span class="taxi-option__media"><ImageWithFallback :src="mediaUrl(option.vehicle.image)" :alt="option.vehicle.name" aspect="editorial" kind="taxi" :label="option.vehicle.name" /></span><span class="taxi-option__body"><strong>{{ option.vehicle.name }}</strong><small><i class="bi bi-people" aria-hidden="true"></i> {{ option.vehicle.passenger_capacity }} {{ t('common.passengers', 'passengers') }}</small><small v-if="option.vehicle.luggage_capacity"><i class="bi bi-suitcase" aria-hidden="true"></i> {{ option.vehicle.luggage_capacity }} {{ t('common.luggage', 'luggage') }}</small><small v-if="option.vehicle.description" class="taxi-option__description">{{ option.vehicle.description }}</small></span><span class="taxi-option__footer"><span class="taxi-option__price"><MoneyDisplay :money="option.total" /><small>{{ t('common.total', 'Total') }}</small></span><span class="taxi-option__select">{{ selectedOption?.vehicle.id === option.vehicle.id ? t('common.selected', 'Selected') : t('common.select', 'Select ride') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></span></span></button></div>
                </section>
                <section v-if="selectedOption && !editingTrip" class="taxi-panel taxi-details-panel" aria-labelledby="taxi-details-title"><div class="taxi-panel__heading"><span class="taxi-step">03</span><div><h2 id="taxi-details-title">{{ t('common.customer_details', 'Booking details') }}</h2><p>{{ t('common.guest_booking_note', 'You can continue as a guest.') }}</p></div></div><div class="taxi-customer-fields"><div><label class="form-label" for="customer_name">{{ t('common.name', 'Name') }}</label><input id="customer_name" v-model="form.customer_name" class="form-control" maxlength="150" autocomplete="name" required><small v-if="errorFor('customer_name')" class="taxi-error">{{ errorFor('customer_name') }}</small></div><div><label class="form-label" for="customer_phone">{{ t('common.phone', 'Phone') }}</label><input id="customer_phone" v-model="form.customer_phone" class="form-control" maxlength="30" autocomplete="tel" inputmode="tel" required><small v-if="errorFor('customer_phone')" class="taxi-error">{{ errorFor('customer_phone') }}</small></div><div><label class="form-label" for="customer_email">{{ t('common.email', 'Email') }}</label><input id="customer_email" v-model="form.customer_email" type="email" class="form-control" maxlength="150" autocomplete="email"></div><div class="taxi-customer-fields__wide"><label class="form-label" for="special_instructions">{{ t('common.special_requests', 'Special requests') }}</label><textarea id="special_instructions" v-model="form.special_instructions" class="form-control" rows="3" maxlength="2000"></textarea></div></div><div class="taxi-booking-action"><div><span>{{ t('common.total', 'Total') }}</span><MoneyDisplay :money="selectedOption.total" /><small>{{ t('common.taxi_payment_note', 'No online payment is taken here.') }}</small></div><button type="submit" class="public-button public-button--primary public-button--lg" :disabled="!canBook">{{ form.processing ? t('common.loading', 'Working…') : t('common.book_taxi', 'Book taxi') }}</button></div></section>
            </form>
        </main>
    </PublicLayout>
</template>
