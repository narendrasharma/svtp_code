<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import MoneyDisplay from '../../Components/Public/UI/MoneyDisplay.vue';
import ErrorState from '../../Components/Public/States/ErrorState.vue';
import { mediaUrl } from '../../Components/Public/homepage';

const props = defineProps({
    property: { type: Object, required: true },
    selection: { type: Object, required: true },
    stay: { type: Object, required: true },
    customer: { type: Object, default: () => ({}) },
    requiresTerms: { type: Boolean, default: false },
    seo: { type: Object, default: () => ({}) },
});

const { locale, t } = useLocalization();
const quote = ref(props.selection);
const quoteLoading = ref(false);
const quoteError = ref('');
const bookingError = ref('');
const form = useForm({
    items: props.selection.items.map(({ room_type_id, rate_plan_id, quantity }) => ({ room_type_id, rate_plan_id, quantity })),
    check_in: props.stay.check_in,
    check_out: props.stay.check_out,
    adults: props.stay.adults,
    children: props.stay.children,
    guest_name: props.customer.name || '',
    guest_email: props.customer.email || '',
    guest_phone: props.customer.phone || '',
    special_requests: '',
    idempotency_key: (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + '-' + Math.random()),
    quote_fingerprint: props.selection.quote_fingerprint,
    terms_accepted: false,
});

const propertyLocation = computed(() => [props.property.city, props.property.destination].filter(Boolean).join(' · '));
const guestSummary = computed(() => {
    const guests = Number(props.stay.adults || 0) + Number(props.stay.children || 0);
    return guests + ' ' + (guests === 1 ? t('common.guest', 'guest') : t('common.guests', 'guests')) + ' · ' + props.stay.rooms + ' ' + (props.stay.rooms === 1 ? t('common.room', 'room') : t('common.rooms', 'rooms'));
});
const backHref = computed(() => {
    const params = new URLSearchParams({
        check_in: props.stay.check_in,
        check_out: props.stay.check_out,
        rooms: String(props.stay.rooms),
        adults: String(props.stay.adults),
        children: String(props.stay.children),
    });
    return appUrl('/hotels/' + props.property.slug + '?' + params.toString() + '#rooms');
});
const quoteIsAvailable = computed(() => Boolean(quote.value?.quote_fingerprint));
const canSubmit = computed(() => !form.processing && !quoteLoading.value && quoteIsAvailable.value && Boolean(form.quote_fingerprint) && (!props.requiresTerms || form.terms_accepted));

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value + 'T12:00:00'));
    } catch {
        return value;
    }
}

function fieldError(field) {
    return form.errors[field] || '';
}

async function loadQuote() {
    quoteLoading.value = true;
    quoteError.value = '';
    try {
        const response = await axios.get(appUrl('/hotels/' + props.property.slug + '/booking-quote'), {
            params: {
                check_in: props.stay.check_in,
                check_out: props.stay.check_out,
                adults: props.stay.adults,
                children: props.stay.children,
                items: form.items,
            },
        });
        quote.value = response.data;
        form.quote_fingerprint = response.data.quote_fingerprint;
    } catch (error) {
        quote.value = null;
        form.quote_fingerprint = '';
        quoteError.value = Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || t('common.quote_error', 'The stay could not be priced right now. Please try again.');
    } finally {
        quoteLoading.value = false;
    }
}

function submit() {
    bookingError.value = '';
    form.post(appUrl('/hotels/' + props.property.slug + '/book'), {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.quote_fingerprint || errors.availability || errors.items) {
                bookingError.value = errors.quote_fingerprint || errors.availability || errors.items;
                loadQuote();
                return;
            }
            bookingError.value = t('common.booking_error', 'We could not complete this booking. Please review the highlighted fields and try again.');
        },
    });
}

onMounted(loadQuote);
</script>

<template>
    <PublicLayout main-class="hotel-booking-page">
        <SeoHead :title="seo.title" :description="seo.description" :noindex="true" />

        <main>
            <div class="public-container hotel-booking-container">
                <nav class="hotel-booking-breadcrumbs" aria-label="Breadcrumb">
                    <Link :href="backHref"><i class="bi bi-arrow-left" data-dir-icon="arrow" aria-hidden="true"></i>{{ t('common.back_to_property', 'Back to property') }}</Link>
                </nav>

                <header class="hotel-booking-header">
                    <div>
                        <span class="public-eyebrow">{{ t('common.secure_booking', 'Secure booking') }}</span>
                        <h1 class="public-heading public-heading--1">{{ t('common.complete_booking', 'Complete your booking') }}</h1>
                        <p>{{ property.name }}<span v-if="propertyLocation"> · {{ propertyLocation }}</span></p>
                    </div>
                    <div class="hotel-booking-steps" aria-label="Booking progress">
                        <span class="is-complete"><b>1</b>{{ t('common.booking_step_stay', 'Stay') }}</span>
                        <span class="is-current"><b>2</b>{{ t('common.booking_step_details', 'Details') }}</span>
                        <span><b>3</b>{{ t('common.booking_step_review', 'Review') }}</span>
                    </div>
                </header>

                <div v-if="quoteError && !quote" class="hotel-booking-alert" role="alert">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    <span>{{ quoteError }}</span>
                    <button type="button" class="public-button public-button--outline public-button--sm" @click="loadQuote">{{ t('common.retry', 'Try again') }}</button>
                </div>
                <ErrorState v-if="bookingError" :title="t('common.booking_error_title', 'Review your booking')" :description="bookingError" />

                <div class="hotel-booking-layout">
                    <section class="hotel-booking-main" aria-labelledby="guest-details-heading">
                        <div class="hotel-booking-card hotel-booking-stay-card">
                            <div class="hotel-booking-card__heading">
                                <div>
                                    <span class="public-eyebrow">{{ t('common.your_stay', 'Your stay') }}</span>
                                    <h2>{{ stay.rooms }} {{ stay.rooms === 1 ? t('common.room', 'room') : t('common.rooms', 'rooms') }}</h2>
                                </div>
                                <Link :href="backHref" class="public-button public-button--ghost public-button--sm">{{ t('common.change_room', 'Change room') }}</Link>
                            </div>
                            <div class="hotel-booking-recap">
                                <ImageWithFallback :src="mediaUrl(property.image)" :alt="property.name" aspect="square" kind="hotel" :label="property.name" loading="eager" />
                                <div class="hotel-booking-room-lines">
                                    <div v-for="item in selection.items" :key="item.room_type_id">
                                        <strong>{{ item.room_name }} × {{ item.quantity }}</strong>
                                        <span>{{ item.rate_name }}</span>
                                        <span v-if="item.cancellation_note">{{ item.cancellation_note }}</span>
                                    </div>
                                </div>
                            </div>
                            <dl class="hotel-booking-facts">
                                <div><dt>{{ t('common.check_in', 'Check-in') }}</dt><dd>{{ formatDate(stay.check_in) }}</dd></div>
                                <div><dt>{{ t('common.check_out', 'Check-out') }}</dt><dd>{{ formatDate(stay.check_out) }}</dd></div>
                                <div><dt>{{ t('common.guests', 'Guests') }}</dt><dd>{{ guestSummary }}</dd></div>
                            </dl>
                        </div>

                        <form class="hotel-booking-card hotel-booking-form" @submit.prevent="submit">
                            <div class="hotel-booking-card__heading">
                                <div>
                                    <span class="public-eyebrow">{{ t('common.contact_details', 'Contact details') }}</span>
                                    <h2 id="guest-details-heading">{{ t('common.guest_details', 'Guest details') }}</h2>
                                </div>
                                <span class="hotel-booking-card__note">{{ t('common.booking_contact_note', 'We will use these details for your reservation.') }}</span>
                            </div>

                            <div class="hotel-booking-form__grid">
                                <div class="public-field hotel-booking-form__field--wide">
                                    <label class="public-field__label" for="guest-name">{{ t('common.full_name', 'Full name') }}</label>
                                    <input id="guest-name" v-model="form.guest_name" class="public-field__control public-input" :class="{ 'is-invalid': fieldError('guest_name') }" type="text" autocomplete="name" required :aria-invalid="!!fieldError('guest_name')" aria-describedby="guest-name-error">
                                    <p v-if="fieldError('guest_name')" id="guest-name-error" class="hotel-booking-field-error" role="alert">{{ fieldError('guest_name') }}</p>
                                </div>
                                <div class="public-field">
                                    <label class="public-field__label" for="guest-email">{{ t('common.email', 'Email') }}</label>
                                    <input id="guest-email" v-model="form.guest_email" class="public-field__control public-input" :class="{ 'is-invalid': fieldError('guest_email') }" type="email" autocomplete="email" required :aria-invalid="!!fieldError('guest_email')" aria-describedby="guest-email-error">
                                    <p v-if="fieldError('guest_email')" id="guest-email-error" class="hotel-booking-field-error" role="alert">{{ fieldError('guest_email') }}</p>
                                </div>
                                <div class="public-field">
                                    <label class="public-field__label" for="guest-phone">{{ t('common.phone', 'Phone') }}</label>
                                    <input id="guest-phone" v-model="form.guest_phone" class="public-field__control public-input" :class="{ 'is-invalid': fieldError('guest_phone') }" type="tel" inputmode="tel" autocomplete="tel" required :aria-invalid="!!fieldError('guest_phone')" aria-describedby="guest-phone-error">
                                    <p v-if="fieldError('guest_phone')" id="guest-phone-error" class="hotel-booking-field-error" role="alert">{{ fieldError('guest_phone') }}</p>
                                </div>
                            </div>

                            <div class="public-field">
                                <label class="public-field__label" for="special-requests">{{ t('common.special_requests', 'Special requests') }} <span>({{ t('common.optional', 'optional') }})</span></label>
                                <textarea id="special-requests" v-model="form.special_requests" class="public-field__control public-textarea" rows="4" maxlength="2000"></textarea>
                                <p class="hotel-booking-help">{{ t('common.special_requests_note', 'Requests are shared with the property and are subject to availability.') }}</p>
                            </div>

                            <label v-if="requiresTerms" class="hotel-booking-policy-check">
                                <input v-model="form.terms_accepted" type="checkbox" :aria-invalid="!!fieldError('terms_accepted')">
                                <span>{{ t('common.agree_to_policy', 'I agree to the cancellation and stay policies shown here.') }}</span>
                            </label>
                            <p v-if="fieldError('terms_accepted')" class="hotel-booking-field-error" role="alert">{{ fieldError('terms_accepted') }}</p>

                            <div class="hotel-booking-form__actions">
                                <Link :href="backHref" class="public-button public-button--ghost">{{ t('common.change_dates', 'Change dates') }}</Link>
                                <button type="submit" class="public-button public-button--primary public-button--lg" :disabled="!canSubmit" :aria-busy="form.processing">
                                    <span v-if="form.processing"><i class="bi bi-arrow-repeat hotel-booking-spin" aria-hidden="true"></i>{{ t('common.confirming_booking', 'Confirming…') }}</span>
                                    <span v-else-if="quoteLoading">{{ t('common.rechecking_price', 'Rechecking price…') }}</span>
                                    <span v-else>{{ t('common.confirm_booking', 'Confirm booking') }}</span>
                                </button>
                            </div>
                        </form>
                    </section>

                    <aside class="hotel-booking-sidebar" aria-labelledby="booking-summary-heading">
                        <div class="hotel-booking-card hotel-booking-summary">
                            <span class="public-eyebrow">{{ t('common.booking_summary', 'Booking summary') }}</span>
                            <h2 id="booking-summary-heading">{{ property.name }}</h2>
                            <p v-if="propertyLocation" class="hotel-booking-summary__location"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ propertyLocation }}</p>
                            <div v-for="item in selection.items" :key="item.room_type_id" class="hotel-booking-summary__selection">
                                <strong>{{ item.room_name }} × {{ item.quantity }}</strong>
                                <span>{{ item.rate_name }}</span>
                                <MoneyDisplay v-if="quote?.items?.find((line) => line.room_type_id === item.room_type_id)?.display_total" :money="quote.items.find((line) => line.room_type_id === item.room_type_id).display_total" />
                            </div>
                            <div class="hotel-booking-summary__stay">
                                <div><span>{{ t('common.check_in', 'Check-in') }}</span><strong>{{ formatDate(stay.check_in) }}</strong></div>
                                <div><span>{{ t('common.check_out', 'Check-out') }}</span><strong>{{ formatDate(stay.check_out) }}</strong></div>
                                <div><span>{{ t('common.guests', 'Guests') }}</span><strong>{{ guestSummary }}</strong></div>
                            </div>
                            <div v-if="quote" class="hotel-booking-summary__price" aria-live="polite">
                                <p v-if="quote.nights_count">{{ quote.nights_count }} {{ quote.nights_count === 1 ? t('common.night', 'night') : t('common.nights', 'nights') }}</p>
                                <div class="hotel-booking-price-row"><span>{{ t('common.subtotal', 'Room subtotal') }}</span><MoneyDisplay :money="quote.display_subtotal" /></div>
                                <div v-if="quote.display_taxes?.display_amount !== '0.00'" class="hotel-booking-price-row"><span>{{ t('common.taxes', 'Taxes') }}</span><MoneyDisplay :money="quote.display_taxes" /></div>
                                <div v-if="quote.display_fees?.display_amount !== '0.00'" class="hotel-booking-price-row"><span>{{ t('common.fees', 'Fees') }}</span><MoneyDisplay :money="quote.display_fees" /></div>
                                <div class="hotel-booking-price-row hotel-booking-price-row--total"><strong>{{ t('common.total', 'Total') }}</strong><MoneyDisplay :money="quote.display_total" /></div>
                                <p v-if="quote.display_total?.conversion_applied" class="hotel-booking-summary__approx">{{ t('common.display_currency_approx', 'Shown in your display currency; the booking currency remains authoritative.') }}</p>
                            </div>
                            <div v-else class="hotel-booking-summary__loading" aria-live="polite">{{ t('common.rechecking_price', 'Rechecking price…') }}</div>
                            <div v-if="quoteError" class="hotel-booking-summary__warning" role="alert"><i class="bi bi-info-circle" aria-hidden="true"></i>{{ quoteError }}</div>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </PublicLayout>
</template>
