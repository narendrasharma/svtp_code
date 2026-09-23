<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import Container from '../../Components/Public/Layout/Container.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import MoneyDisplay from '../../Components/Public/UI/MoneyDisplay.vue';
import Badge from '../../Components/Public/UI/Badge.vue';

const props = defineProps({
    package: { type: Object, required: true },
    customer: { type: Object, default: null },
    addons: { type: Array, default: () => [] },
    availability: { type: Object, default: () => ({}) },
    selection: { type: Object, default: () => ({}) },
    quote: { type: Object, default: () => ({}) },
});

const { locale, t } = useLocalization();
const today = new Date().toISOString().slice(0, 10);
const quote = ref(props.quote);
const quoteLoading = ref(false);
const quoteError = ref('');
const couponDraft = ref('');
let quoteTimer = null;
let quoteRequest = 0;

const form = useForm({
    package_id: props.package.id,
    travel_date: props.selection.travel_date ?? '',
    total_adults: Number(props.selection.adults ?? 1),
    total_children: Number(props.selection.children ?? 0),
    customer_name: props.customer?.name ?? '',
    customer_email: props.customer?.email ?? '',
    customer_phone: props.customer?.phone ?? '',
    country: '',
    pickup_address: '',
    special_requests: '',
    coupon_code: '',
    addons: [],
});

const durationLabel = computed(() => {
    const days = Number(props.package.duration_days);
    const nights = Number(props.package.duration_nights);

    if (!Number.isFinite(days) || days < 1) return '';
    if (days === 1) return t('common.same_day', 'Same day');

    return `${days} ${days === 1 ? t('common.day', 'day') : t('common.days', 'days')}${nights > 0 ? ` · ${nights} ${nights === 1 ? t('common.night', 'night') : t('common.nights', 'nights')}` : ''}`;
});

const destinationLabel = computed(() => [props.package.destination, props.package.city].filter(Boolean).join(' · '));
const displayBreakdown = computed(() => quote.value?.display_breakdown ?? null);
const dateBookability = computed(() => quote.value?.availability ?? null);
const canSubmit = computed(() => Boolean(
    form.travel_date
    && displayBreakdown.value?.total_money
    && !quoteLoading.value
    && !quoteError.value
    && dateBookability.value?.bookable !== false
));

function formatDate(value) {
    if (!value) return t('common.select_date', 'Select date');

    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value}T12:00:00`));
    } catch {
        return value;
    }
}

function fieldError(field) {
    return form.errors[field] ?? '';
}

function normalizeParty() {
    form.total_adults = Math.max(1, Math.min(30, Number(form.total_adults || 1)));
    form.total_children = Math.max(0, Math.min(30, Number(form.total_children || 0)));
}

function quotePayload() {
    normalizeParty();

    return {
        package_id: props.package.id,
        total_adults: form.total_adults,
        total_children: form.total_children,
        travel_date: form.travel_date || undefined,
        coupon_code: form.coupon_code || undefined,
        customer_email: form.customer_email || undefined,
        addons: form.addons,
    };
}

async function fetchQuote() {
    const requestId = ++quoteRequest;
    quoteLoading.value = true;
    quoteError.value = '';

    try {
        const response = await axios.post(appUrl('/booking/estimate'), quotePayload());

        if (requestId !== quoteRequest) return;
        quote.value = response.data;
    } catch (error) {
        if (requestId !== quoteRequest) return;
        quote.value = null;
        quoteError.value = error?.response?.data?.errors?.coupon_code?.[0]
            || error?.response?.data?.errors?.travel_date?.[0]
            || error?.response?.data?.message
            || t('common.quote_error', 'The tour could not be priced right now. Please try again.');
    } finally {
        if (requestId === quoteRequest) quoteLoading.value = false;
    }
}

function scheduleQuote() {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(fetchQuote, 260);
}

function addonChecked(addon) {
    return addon.is_required || form.addons.some((row) => Number(row.addon_id) === Number(addon.id));
}

function addonQuantity(addon) {
    return form.addons.find((row) => Number(row.addon_id) === Number(addon.id))?.quantity ?? 1;
}

function toggleAddon(addon) {
    if (addon.is_required) return;

    const index = form.addons.findIndex((row) => Number(row.addon_id) === Number(addon.id));

    if (index >= 0) {
        form.addons.splice(index, 1);
    } else {
        form.addons.push({ addon_id: addon.id, quantity: 1 });
    }
}

function setAddonQuantity(addon, value) {
    const row = form.addons.find((item) => Number(item.addon_id) === Number(addon.id));

    if (row) row.quantity = Math.max(1, Math.min(Number(addon.max_quantity || 30), Number(value || 1)));
}

function addonPricingLabel(addon) {
    if (addon.pricing_type === 'per_person') return t('common.per_person', 'per person');
    if (addon.pricing_type === 'per_quantity') return t('common.per_quantity', 'per quantity');

    return t('common.once', 'once');
}

function applyCoupon() {
    form.coupon_code = couponDraft.value.trim();
}

function submit() {
    if (!canSubmit.value) {
        if (!form.travel_date) quoteError.value = t('common.select_date_to_book', 'Select a travel date to continue.');
        return;
    }

    form.post(appUrl('/bookings'), { preserveScroll: true });
}

watch([
    () => form.total_adults,
    () => form.total_children,
    () => form.travel_date,
    () => form.coupon_code,
    () => form.addons,
], scheduleQuote, { deep: true, immediate: true });

onBeforeUnmount(() => clearTimeout(quoteTimer));
</script>

<template>
    <PublicLayout main-class="tour-booking-page" :show-floating-actions="false">
        <SeoHead :title="`${t('common.book_tour', 'Book tour')} · ${package.title}`" noindex />

        <Container class="tour-booking-container">
            <nav class="tour-booking-breadcrumbs" :aria-label="t('common.breadcrumb', 'Breadcrumb')">
                <Link :href="appUrl('/packages')">{{ t('common.back_to_tours', 'Back to tours') }}</Link>
                <span aria-hidden="true">/</span>
                <span>{{ t('common.book_tour', 'Book tour') }}</span>
            </nav>

            <header class="tour-booking-header">
                <div>
                    <p class="public-eyebrow">{{ t('common.plan_your_tour', 'Plan your tour') }}</p>
                    <h1>{{ t('common.complete_tour_booking', 'Complete your tour booking') }}</h1>
                    <p>{{ t('common.tour_booking_intro', 'Confirm your journey details, then share the contact information we need to follow up.') }}</p>
                </div>
                <div class="tour-booking-steps" aria-label="Booking progress">
                    <span class="is-current"><b>1</b>{{ t('common.details', 'Details') }}</span>
                    <span><b>2</b>{{ t('common.review_selection', 'Review') }}</span>
                    <span><b>3</b>{{ t('common.booking_confirmation', 'Confirmation') }}</span>
                </div>
            </header>

            <div v-if="quoteError" class="tour-booking-alert" role="alert">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span>{{ quoteError }}</span>
            </div>

            <form class="tour-booking-layout" @submit.prevent="submit">
                <div class="tour-booking-main">
                    <section class="tour-booking-card" aria-labelledby="journey-heading">
                        <div class="tour-booking-card__heading">
                            <div>
                                <p class="public-eyebrow">{{ t('common.your_tour_details', 'Your tour details') }}</p>
                                <h2 id="journey-heading">{{ t('common.journey_details', 'Shape the journey') }}</h2>
                            </div>
                            <span class="tour-booking-card__note">{{ t('common.server_price_note', 'Final pricing is recalculated securely when you continue.') }}</span>
                        </div>

                        <div class="tour-booking-recap">
                            <ImageWithFallback :src="package.cover_image" :alt="package.title" aspect="editorial" kind="tour" :label="package.title" loading="eager" />
                            <div>
                                <strong>{{ package.title }}</strong>
                                <span v-if="destinationLabel"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ destinationLabel }}</span>
                                <span v-if="durationLabel"><i class="bi bi-clock" aria-hidden="true"></i>{{ durationLabel }}</span>
                            </div>
                        </div>

                        <dl class="tour-booking-facts">
                            <div><dt>{{ t('common.travel_date', 'Travel date') }}</dt><dd>{{ formatDate(form.travel_date) }}</dd></div>
                            <div><dt>{{ t('common.adults', 'Adults') }}</dt><dd>{{ form.total_adults }}</dd></div>
                            <div><dt>{{ t('common.children', 'Children') }}</dt><dd>{{ form.total_children }}</dd></div>
                        </dl>

                        <div class="tour-booking-form-grid">
                            <label class="public-field">
                                <span class="public-field__label">{{ t('common.travel_date', 'Travel date') }} <b>*</b></span>
                                <input id="tour-travel-date" v-model="form.travel_date" class="public-input" type="date" :min="today" required :aria-invalid="Boolean(fieldError('travel_date'))" aria-describedby="tour-travel-date-error">
                                <span v-if="fieldError('travel_date')" id="tour-travel-date-error" class="tour-booking-field-error" role="alert">{{ fieldError('travel_date') }}</span>
                            </label>
                            <label class="public-field">
                                <span class="public-field__label">{{ t('common.adults', 'Adults') }} <b>*</b></span>
                                <input id="tour-adults" v-model.number="form.total_adults" class="public-input" type="number" min="1" max="30" required :aria-invalid="Boolean(fieldError('total_adults'))" aria-describedby="tour-adults-error">
                                <span v-if="fieldError('total_adults')" id="tour-adults-error" class="tour-booking-field-error" role="alert">{{ fieldError('total_adults') }}</span>
                            </label>
                            <label class="public-field">
                                <span class="public-field__label">{{ t('common.children', 'Children') }} <span>({{ t('common.optional', 'optional') }})</span></span>
                                <input id="tour-children" v-model.number="form.total_children" class="public-input" type="number" min="0" max="30" :aria-invalid="Boolean(fieldError('total_children'))" aria-describedby="tour-children-error">
                                <span v-if="fieldError('total_children')" id="tour-children-error" class="tour-booking-field-error" role="alert">{{ fieldError('total_children') }}</span>
                            </label>
                            <div v-if="dateBookability" class="tour-booking-date-state" :class="dateBookability.bookable ? 'is-available' : 'is-unavailable'" role="status">
                                <i :class="dateBookability.bookable ? 'bi bi-check-circle' : 'bi bi-info-circle'" aria-hidden="true"></i>
                                <span>{{ dateBookability.bookable ? t('common.available_for_date', 'Available for this date') : (dateBookability.reason || t('common.unavailable_for_date', 'This date is not available for this tour.')) }}</span>
                            </div>
                            <p v-else class="tour-booking-help">{{ t('common.no_seat_inventory_note', 'Your date is a booking context. This page does not show departure or seat inventory.') }}</p>
                        </div>
                    </section>

                    <section v-if="addons.length" class="tour-booking-card" aria-labelledby="extras-heading">
                        <div class="tour-booking-card__heading">
                            <div>
                                <p class="public-eyebrow">{{ t('common.optional', 'Optional') }}</p>
                                <h2 id="extras-heading">{{ t('common.tour_extras', 'Tour extras') }}</h2>
                            </div>
                            <span class="tour-booking-card__note">{{ t('common.extras_server_priced', 'Selected extras are priced by the tour service.') }}</span>
                        </div>
                        <div class="tour-booking-addon-list">
                            <div v-for="addon in addons" :key="addon.id" class="tour-booking-addon">
                                <label class="tour-booking-addon__choice">
                                    <input :checked="addonChecked(addon)" :disabled="addon.is_required" type="checkbox" @change="toggleAddon(addon)">
                                    <span>
                                        <strong>{{ addon.name }}</strong>
                                        <small>{{ addon.price_money?.display_formatted }} · {{ addonPricingLabel(addon) }}</small>
                                    </span>
                                </label>
                                <p v-if="addon.description">{{ addon.description }}</p>
                                <label v-if="addonChecked(addon) && addon.pricing_type === 'per_quantity'" class="tour-booking-addon__quantity">
                                    <span>{{ t('common.quantity', 'Quantity') }}</span>
                                    <input :value="addonQuantity(addon)" type="number" min="1" :max="addon.max_quantity || 30" class="public-input" @input="setAddonQuantity(addon, $event.target.value)">
                                </label>
                                <Badge v-if="addon.is_required" variant="neutral">{{ t('common.included_automatically', 'Included automatically') }}</Badge>
                            </div>
                        </div>
                        <span v-if="fieldError('addons')" class="tour-booking-field-error" role="alert">{{ fieldError('addons') }}</span>
                    </section>

                    <section class="tour-booking-card" aria-labelledby="contact-heading">
                        <div class="tour-booking-card__heading">
                            <div>
                                <p class="public-eyebrow">{{ t('common.contact_details', 'Contact details') }}</p>
                                <h2 id="contact-heading">{{ t('common.guest_details', 'Guest details') }}</h2>
                            </div>
                            <span class="tour-booking-card__note">{{ t('common.guest_booking_note', 'You can continue as a guest. We only ask for details needed for this booking.') }}</span>
                        </div>

                        <div class="tour-booking-form-grid">
                            <label class="public-field">
                                <span class="public-field__label">{{ t('common.full_name', 'Full name') }} <b>*</b></span>
                                <input id="tour-customer-name" v-model="form.customer_name" class="public-input" type="text" maxlength="255" autocomplete="name" required :aria-invalid="Boolean(fieldError('customer_name'))" aria-describedby="tour-customer-name-error">
                                <span v-if="fieldError('customer_name')" id="tour-customer-name-error" class="tour-booking-field-error" role="alert">{{ fieldError('customer_name') }}</span>
                            </label>
                            <label class="public-field">
                                <span class="public-field__label">{{ t('common.phone', 'Phone') }} <b>*</b></span>
                                <input id="tour-customer-phone" v-model="form.customer_phone" class="public-input" type="tel" maxlength="20" autocomplete="tel" inputmode="tel" required :aria-invalid="Boolean(fieldError('customer_phone'))" aria-describedby="tour-customer-phone-error">
                                <span v-if="fieldError('customer_phone')" id="tour-customer-phone-error" class="tour-booking-field-error" role="alert">{{ fieldError('customer_phone') }}</span>
                            </label>
                            <label class="public-field">
                                <span class="public-field__label">{{ t('common.email', 'Email') }} <span>({{ t('common.optional', 'optional') }})</span></span>
                                <input id="tour-customer-email" v-model="form.customer_email" class="public-input" type="email" maxlength="255" autocomplete="email" :aria-invalid="Boolean(fieldError('customer_email'))" aria-describedby="tour-customer-email-error">
                                <span v-if="fieldError('customer_email')" id="tour-customer-email-error" class="tour-booking-field-error" role="alert">{{ fieldError('customer_email') }}</span>
                            </label>
                            <label class="public-field">
                                <span class="public-field__label">{{ t('common.country', 'Country') }} <span>({{ t('common.optional', 'optional') }})</span></span>
                                <input id="tour-country" v-model="form.country" class="public-input" type="text" maxlength="100" autocomplete="country-name" :aria-invalid="Boolean(fieldError('country'))" aria-describedby="tour-country-error">
                                <span v-if="fieldError('country')" id="tour-country-error" class="tour-booking-field-error" role="alert">{{ fieldError('country') }}</span>
                            </label>
                            <label class="public-field public-field--wide">
                                <span class="public-field__label">{{ t('common.pickup_request', 'Pickup request') }} <span>({{ t('common.optional', 'optional') }})</span></span>
                                <input id="tour-pickup" v-model="form.pickup_address" class="public-input" type="text" maxlength="255" :placeholder="t('common.pickup_placeholder', 'Share a hotel, station or meeting point if relevant')" :aria-invalid="Boolean(fieldError('pickup_address'))" aria-describedby="tour-pickup-error">
                                <span v-if="fieldError('pickup_address')" id="tour-pickup-error" class="tour-booking-field-error" role="alert">{{ fieldError('pickup_address') }}</span>
                            </label>
                            <label class="public-field public-field--wide">
                                <span class="public-field__label">{{ t('common.special_requests', 'Special requests') }} <span>({{ t('common.optional', 'optional') }})</span></span>
                                <textarea id="tour-special-requests" v-model="form.special_requests" class="public-textarea" rows="4" maxlength="1000" :placeholder="t('common.special_requests_tour_placeholder', 'Anything the tour team should know?')" :aria-invalid="Boolean(fieldError('special_requests'))" aria-describedby="tour-special-requests-error"></textarea>
                                <span v-if="fieldError('special_requests')" id="tour-special-requests-error" class="tour-booking-field-error" role="alert">{{ fieldError('special_requests') }}</span>
                            </label>
                        </div>
                    </section>
                </div>

                <aside class="tour-booking-sidebar" aria-labelledby="tour-price-heading">
                    <section class="tour-booking-summary">
                        <p class="public-eyebrow">{{ t('common.review_selection', 'Review selection') }}</p>
                        <h2 id="tour-price-heading">{{ t('common.booking_summary', 'Booking summary') }}</h2>
                        <p v-if="destinationLabel" class="tour-booking-summary__location"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ destinationLabel }}</p>

                        <div class="tour-booking-summary__selection">
                            <div><span>{{ t('common.travel_date', 'Travel date') }}</span><strong>{{ formatDate(form.travel_date) }}</strong></div>
                            <div><span>{{ t('common.adults', 'Adults') }}</span><strong>{{ form.total_adults }}</strong></div>
                            <div><span>{{ t('common.children', 'Children') }}</span><strong>{{ form.total_children }}</strong></div>
                        </div>

                        <div class="tour-booking-coupon">
                            <label for="tour-coupon" class="public-field__label">{{ t('common.promo_code', 'Promo code') }} <span>({{ t('common.optional', 'optional') }})</span></label>
                            <div class="tour-booking-coupon__row">
                                <input id="tour-coupon" v-model="couponDraft" class="public-input" type="text" maxlength="50" autocomplete="off" @keyup.enter="applyCoupon">
                                <button type="button" class="public-button public-button--outline" @click="applyCoupon">{{ t('common.apply', 'Apply') }}</button>
                            </div>
                        </div>

                        <div class="tour-booking-price" aria-live="polite">
                            <div v-if="quoteLoading" class="tour-booking-price__loading"><span></span><span></span><span></span></div>
                            <template v-else-if="displayBreakdown">
                                <div class="tour-booking-price-row"><span>{{ t('common.adults', 'Adults') }} × {{ displayBreakdown.adults.quantity }}</span><MoneyDisplay :money="displayBreakdown.adults.total_money" /></div>
                                <div v-if="displayBreakdown.children.quantity" class="tour-booking-price-row"><span>{{ t('common.children', 'Children') }} × {{ displayBreakdown.children.quantity }}</span><MoneyDisplay :money="displayBreakdown.children.total_money" /></div>
                                <div v-for="line in displayBreakdown.addons" :key="`${line.name}-${line.quantity}`" class="tour-booking-price-row"><span>{{ line.name }} × {{ line.quantity }}</span><MoneyDisplay :money="line.total_money" /></div>
                                <div class="tour-booking-price-row"><span>{{ t('common.tour_subtotal', 'Tour subtotal') }}</span><MoneyDisplay :money="displayBreakdown.subtotal_money" /></div>
                                <div v-if="displayBreakdown.discount_money?.amount !== '0.00'" class="tour-booking-price-row tour-booking-price-row--discount"><span>{{ t('common.discount', 'Discount') }}</span><MoneyDisplay :money="displayBreakdown.discount_money" /></div>
                                <div v-if="displayBreakdown.tax_money?.amount !== '0.00'" class="tour-booking-price-row"><span>{{ t('common.taxes', 'Taxes') }}</span><MoneyDisplay :money="displayBreakdown.tax_money" /></div>
                                <div class="tour-booking-price-row tour-booking-price-row--total"><strong>{{ t('common.total', 'Total') }}</strong><MoneyDisplay :money="displayBreakdown.total_money" /></div>
                                <p v-if="displayBreakdown.total_money?.conversion_applied" class="tour-booking-summary__approx">{{ t('common.display_currency_approx', 'Shown in your display currency; the booking currency remains authoritative.') }}</p>
                            </template>
                            <p v-else class="tour-booking-summary__empty">{{ t('common.select_date_to_book', 'Select a travel date to check the booking total.') }}</p>
                        </div>

                        <button type="submit" class="public-button public-button--primary public-button--lg tour-booking-submit" :disabled="!canSubmit || form.processing" :aria-busy="form.processing">
                            <i v-if="form.processing" class="bi bi-arrow-repeat tour-booking-spin" aria-hidden="true"></i>
                            {{ form.processing ? t('common.confirming_booking', 'Confirming…') : t('common.confirm_booking', 'Confirm booking') }}
                        </button>
                        <p class="tour-booking-summary__trust"><i class="bi bi-shield-check" aria-hidden="true"></i>{{ t('common.server_booking_note', 'The tour team will review this booking request. No online payment is taken here.') }}</p>
                    </section>
                </aside>
            </form>
        </Container>
    </PublicLayout>
</template>
