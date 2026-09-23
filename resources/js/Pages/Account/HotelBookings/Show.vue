<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';
import ImageWithFallback from '../../../Components/Public/Media/ImageWithFallback.vue';
import MoneyDisplay from '../../../Components/Public/UI/MoneyDisplay.vue';
import BookingStatus from '../../../Components/Public/Hotel/Bookings/BookingStatus.vue';
import BookingTimeline from '../../../Components/Public/Hotel/Bookings/BookingTimeline.vue';
import RefundHistory from '../../../Components/Public/Hotel/Bookings/RefundHistory.vue';
import ReviewCard from '../../../Components/Hotel/ReviewCard.vue';

const props = defineProps({
    booking: { type: Object, required: true },
    cancellationQuote: { type: Object, default: () => ({}) },
    reviewEligibility: { type: Object, default: () => ({}) },
    reviewsEnabled: { type: Boolean, default: false },
});

const { locale, t } = useLocalization();
const cancellationConfirmationOpen = ref(false);
const rescheduleOpen = ref(false);
const rescheduleQuote = ref(null);
const rescheduleLoading = ref(false);
const rescheduleError = ref('');

const cancelForm = useForm({ idempotency_key: '', reason_code: 'customer_request' });
const rescheduleForm = useForm({ check_in: props.booking.check_in, check_out: props.booking.check_out, quote_fingerprint: '', idempotency_key: '' });

const item = computed(() => props.booking.items?.[0] || {});
const canCancel = computed(() => props.booking.actions?.can_cancel && props.cancellationQuote?.eligible);
const canReschedule = computed(() => props.booking.actions?.can_reschedule);
const canReview = computed(() => props.reviewsEnabled && (props.reviewEligibility?.can_review || props.reviewEligibility?.has_review));

function formatDate(value) {
    if (!value) return '—';

    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' })
            .format(new Date(`${value}T12:00:00`));
    } catch {
        return value;
    }
}

function formatDateTime(value) {
    if (!value) return '—';

    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
    } catch {
        return value;
    }
}

function labelFor(value) {
    const labels = {
        non_refundable: t('common.non_refundable', 'Non-refundable'),
        flexible: t('common.flexible_cancellation', 'Flexible cancellation'),
        first_night: t('common.first_night_fee', 'First night fee'),
        percentage: t('common.percentage_fee', 'Percentage fee'),
        fixed: t('common.fixed_fee', 'Fixed fee'),
        full_amount: t('common.full_amount_fee', 'Full amount'),
    };

    return labels[value] || String(value || '').replaceAll('_', ' ');
}

function policySummary(policy) {
    if (!policy) return '';
    if (policy.summary) return policy.summary;
    if (policy.mode === 'non_refundable') return t('common.non_refundable_policy', 'This stay is non-refundable.');
    if (policy.free_until_hours) {
        return t('common.free_cancellation_until', `Free cancellation until ${policy.free_until_hours} hours before check-in.`)
            .replace(':hours', policy.free_until_hours);
    }

    return labelFor(policy.mode);
}

function cancelBooking() {
    cancelForm.idempotency_key = createIdempotencyKey('hotel-cancel');
    cancelForm.post(appUrl(`/account/hotel-bookings/${props.booking.id}/cancel`), {
        preserveScroll: true,
        onSuccess: () => { cancellationConfirmationOpen.value = false; },
    });
}

async function quoteReschedule() {
    rescheduleError.value = '';
    rescheduleQuote.value = null;

    if (!rescheduleForm.check_in || !rescheduleForm.check_out || rescheduleForm.check_out <= rescheduleForm.check_in) {
        rescheduleError.value = t('common.valid_reschedule_dates', 'Choose a check-out date after check-in.');
        return;
    }

    rescheduleLoading.value = true;

    try {
        rescheduleQuote.value = (await axios.post(
            appUrl(`/account/hotel-bookings/${props.booking.id}/reschedule-quote`),
            { check_in: rescheduleForm.check_in, check_out: rescheduleForm.check_out },
        )).data;
    } catch (error) {
        rescheduleError.value = error.response?.data?.message || t('common.reschedule_quote_error', 'These dates could not be quoted. Please try again.');
    } finally {
        rescheduleLoading.value = false;
    }
}

function confirmReschedule() {
    if (!rescheduleQuote.value?.eligible) return;

    rescheduleForm.quote_fingerprint = rescheduleQuote.value.quote_fingerprint;
    rescheduleForm.idempotency_key = createIdempotencyKey('hotel-reschedule');
    rescheduleForm.post(appUrl(`/account/hotel-bookings/${props.booking.id}/reschedule`), {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.quote_fingerprint) {
                rescheduleError.value = t('common.stale_reschedule_quote', 'The price or availability changed. Review the updated reschedule details.');
                rescheduleQuote.value = null;
            }
        },
    });
}

function createIdempotencyKey(prefix) {
    return `${prefix}-${props.booking.id}-${typeof crypto?.randomUUID === 'function' ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`}`;
}

function priceDifferenceLabel(quote) {
    const difference = Number(quote?.difference || 0);

    if (difference > 0) return t('common.additional_amount_due', 'Additional amount due');
    if (difference < 0) return t('common.lower_stay_total', 'Lower stay total');

    return t('common.no_price_change', 'No price change');
}

function priceDifferenceMoney(quote) {
    const difference = Number(quote?.difference || 0);

    if (difference > 0) return quote.display_money?.additional_payment_due;
    if (difference < 0) return quote.display_money?.refundable_difference;

    return quote.display_money?.difference;
}
</script>

<template>
    <PublicLayout main-class="hotel-customer-page">
        <SeoHead :title="`${t('common.booking_details', 'Booking details')} · ${booking.booking_number}`" noindex />

        <div class="hotel-customer-container">
            <nav class="hotel-customer-breadcrumbs" :aria-label="t('common.breadcrumb', 'Breadcrumb')">
                <Link :href="appUrl('/account/hotel-bookings')" class="public-button public-button--text public-button--sm">
                    <i class="bi bi-arrow-left" data-dir-icon="arrow" aria-hidden="true"></i>
                    {{ t('common.my_hotel_bookings', 'My hotel bookings') }}
                </Link>
            </nav>

            <header class="hotel-customer-detail-header">
                <div>
                    <span class="public-eyebrow">{{ t('common.booking_details', 'Booking details') }}</span>
                    <h1 class="public-heading public-heading--1">{{ booking.property_name }}</h1>
                    <p class="hotel-customer-detail-header__reference">
                        {{ t('common.booking_reference', 'Booking reference') }} <strong>{{ booking.booking_number }}</strong>
                        <span aria-hidden="true"> · </span>{{ formatDate(booking.check_in) }} → {{ formatDate(booking.check_out) }}
                    </p>
                </div>
                <div class="hotel-customer-detail-header__status">
                    <BookingStatus :status="booking.status" />
                    <small>{{ t('common.payment_status', 'Payment status') }}</small>
                    <BookingStatus :status="booking.payment_status" kind="payment" />
                </div>
            </header>

            <div class="hotel-customer-detail-layout">
                <main class="hotel-customer-detail-main">
                    <section class="hotel-customer-panel" aria-labelledby="stay-overview-heading">
                        <div class="hotel-customer-panel__heading">
                            <div>
                                <span class="public-eyebrow">{{ t('common.stay_details', 'Stay details') }}</span>
                                <h2 id="stay-overview-heading" class="public-heading public-heading--3">{{ t('common.your_stay', 'Your stay') }}</h2>
                            </div>
                            <i class="bi bi-map hotel-customer-panel__icon" aria-hidden="true"></i>
                        </div>
                        <div class="hotel-customer-stay-overview">
                            <ImageWithFallback :src="booking.property_image" :alt="booking.property_name" aspect="square" kind="hotel" :label="booking.property_name" />
                            <div>
                                <h2 class="public-heading public-heading--4">{{ booking.property_name }}</h2>
                                <p>{{ item.room_type || t('common.accommodation', 'Accommodation') }} · {{ item.rate_plan || t('common.rate_plan', 'Rate plan') }}</p>
                                <Link v-if="booking.property_slug" :href="appUrl(`/hotels/${booking.property_slug}`)" class="public-button public-button--text public-button--sm">
                                    {{ t('common.view_property', 'View property') }}
                                    <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                                </Link>
                            </div>
                        </div>
                        <dl class="hotel-customer-facts">
                            <div><dt>{{ t('common.check_in', 'Check-in') }}</dt><dd>{{ formatDate(booking.check_in) }}</dd></div>
                            <div><dt>{{ t('common.check_out', 'Check-out') }}</dt><dd>{{ formatDate(booking.check_out) }}</dd></div>
                            <div><dt>{{ t('common.nights', 'Nights') }}</dt><dd>{{ booking.nights }}</dd></div>
                            <div><dt>{{ t('common.rooms', 'Rooms') }}</dt><dd>{{ booking.rooms_count }}</dd></div>
                            <div><dt>{{ t('common.guests', 'Guests') }}</dt><dd>{{ booking.adults }} {{ t('common.adults', 'adults') }}<span v-if="booking.children"> · {{ booking.children }} {{ t('common.children', 'children') }}</span></dd></div>
                            <div><dt>{{ t('common.booking_currency', 'Booking currency') }}</dt><dd>{{ booking.currency }}</dd></div>
                        </dl>
                    </section>

                    <section class="hotel-customer-panel" aria-labelledby="room-summary-heading">
                        <div class="hotel-customer-panel__heading">
                            <div>
                                <span class="public-eyebrow">{{ t('common.booked_selection', 'Booked selection') }}</span>
                                <h2 id="room-summary-heading" class="public-heading public-heading--3">{{ t('common.room_and_rate', 'Room and rate') }}</h2>
                            </div>
                            <i class="bi bi-door-open hotel-customer-panel__icon" aria-hidden="true"></i>
                        </div>
                        <div class="hotel-customer-room-list">
                            <div v-for="(room, index) in booking.items" :key="`${room.room_type}-${index}`" class="hotel-customer-room">
                                <div>
                                    <h3>{{ room.quantity }} × {{ room.room_type }}</h3>
                                    <p>{{ room.rate_plan }}<span v-if="room.meal_plan"> · {{ room.meal_plan }}</span></p>
                                    <p v-if="room.cancellation_mode">{{ labelFor(room.cancellation_mode) }}</p>
                                </div>
                                <div class="hotel-customer-room__total"><MoneyDisplay :money="room.display_money?.total" /></div>
                            </div>
                        </div>
                        <p v-if="booking.cancellation_policy?.mode" class="hotel-customer-note">
                            <i class="bi bi-shield-check" aria-hidden="true"></i>
                            {{ policySummary(booking.cancellation_policy) }}
                        </p>
                    </section>

                    <section class="hotel-customer-panel hotel-customer-contact" aria-labelledby="guest-details-heading">
                        <div class="hotel-customer-panel__heading">
                            <div>
                                <span class="public-eyebrow">{{ t('common.guest_details', 'Guest details') }}</span>
                                <h2 id="guest-details-heading" class="public-heading public-heading--3">{{ t('common.contact_details', 'Contact details') }}</h2>
                            </div>
                            <i class="bi bi-person-vcard hotel-customer-panel__icon" aria-hidden="true"></i>
                        </div>
                        <p><strong>{{ booking.guest_name }}</strong></p>
                        <p>{{ booking.guest_email }}</p>
                        <p>{{ booking.guest_phone }}</p>
                        <p v-if="booking.special_requests" class="hotel-customer-contact__note">{{ booking.special_requests }}</p>
                    </section>

                    <section class="hotel-customer-panel" aria-labelledby="timeline-heading">
                        <div class="hotel-customer-panel__heading">
                            <div>
                                <span class="public-eyebrow">{{ t('common.booking_journey', 'Booking journey') }}</span>
                                <h2 id="timeline-heading" class="public-heading public-heading--3">{{ t('common.timeline', 'Timeline') }}</h2>
                            </div>
                            <i class="bi bi-clock-history hotel-customer-panel__icon" aria-hidden="true"></i>
                        </div>
                        <BookingTimeline :entries="booking.timeline" />
                    </section>

                    <section v-if="booking.changes?.length" class="hotel-customer-panel" aria-labelledby="change-history-heading">
                        <div class="hotel-customer-panel__heading">
                            <div>
                                <span class="public-eyebrow">{{ t('common.stay_changes', 'Stay changes') }}</span>
                                <h2 id="change-history-heading" class="public-heading public-heading--3">{{ t('common.reschedule_history', 'Reschedule history') }}</h2>
                            </div>
                            <i class="bi bi-calendar2-range hotel-customer-panel__icon" aria-hidden="true"></i>
                        </div>
                        <div class="hotel-customer-history__list">
                            <div v-for="(change, index) in booking.changes" :key="`${change.created_at}-${index}`" class="hotel-customer-history__row">
                                <div>
                                    <strong>{{ formatDate(change.old_check_in) }} → {{ formatDate(change.old_check_out) }}</strong>
                                    <span class="hotel-customer-history__meta">{{ t('common.changed_to', 'Changed to') }} {{ formatDate(change.new_check_in) }} → {{ formatDate(change.new_check_out) }}</span>
                                    <span v-if="change.created_at" class="hotel-customer-history__meta">{{ formatDateTime(change.created_at) }}</span>
                                </div>
                                <span v-if="Number(change.difference) > 0" class="hotel-customer-history__meta">+<MoneyDisplay :money="change.display_money?.difference" /></span>
                                <span v-else-if="Number(change.difference) < 0" class="hotel-customer-history__meta">−<MoneyDisplay :money="change.display_money?.difference" /></span>
                                <span v-else class="hotel-customer-history__meta">{{ t('common.no_price_change', 'No price change') }}</span>
                            </div>
                        </div>
                    </section>

                    <RefundHistory :refunds="booking.refunds" />

                    <section v-if="booking.cancellations?.length" class="hotel-customer-history" aria-labelledby="cancellation-history-heading">
                        <div class="hotel-customer-section-heading">
                            <div>
                                <span class="public-eyebrow">{{ t('common.booking_history', 'Booking history') }}</span>
                                <h2 id="cancellation-history-heading" class="public-heading public-heading--3">{{ t('common.cancellation_history', 'Cancellation history') }}</h2>
                            </div>
                            <i class="bi bi-slash-circle hotel-customer-section-heading__icon" aria-hidden="true"></i>
                        </div>
                        <div v-for="cancellation in booking.cancellations" :key="cancellation.cancelled_at" class="hotel-customer-history__row">
                            <div>
                                <strong>{{ t('common.cancelled', 'Cancelled') }}</strong>
                                <span class="hotel-customer-history__meta">{{ cancellation.cancelled_at ? formatDateTime(cancellation.cancelled_at) : '' }}</span>
                            </div>
                            <div class="hotel-customer-history__meta">
                                <span>{{ t('common.cancellation_fee', 'Cancellation fee') }}: </span><MoneyDisplay :money="cancellation.display_money?.fee" />
                            </div>
                        </div>
                    </section>

                    <section v-if="canReview" class="hotel-customer-panel" aria-labelledby="review-status-heading">
                        <div class="hotel-customer-panel__heading">
                            <div>
                                <span class="public-eyebrow">{{ t('common.after_your_stay', 'After your stay') }}</span>
                                <h2 id="review-status-heading" class="public-heading public-heading--3">{{ t('common.your_review', 'Your review') }}</h2>
                            </div>
                            <i class="bi bi-chat-square-heart hotel-customer-panel__icon" aria-hidden="true"></i>
                        </div>
                        <p v-if="reviewEligibility.can_review" class="hotel-customer-note">{{ t('common.review_invitation', 'Your completed stay is eligible for a verified review.') }}</p>
                        <p v-else class="hotel-customer-note">{{ reviewEligibility.status === 'pending' ? t('common.pending_review', 'Your review is pending moderation.') : t('common.review_published', 'Your review is published.') }}</p>
                        <ReviewCard v-if="booking.review && reviewEligibility.has_review" :review="booking.review" />
                        <Link :href="appUrl(`/account/hotel-bookings/${booking.id}/review`)" class="public-button public-button--outline public-button--sm">
                            {{ reviewEligibility.has_review ? t('common.view_review', 'View review') : t('common.write_review', 'Write review') }}
                            <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                        </Link>
                    </section>

                    <section v-if="rescheduleOpen && canReschedule" class="hotel-customer-panel" aria-labelledby="reschedule-heading">
                        <div class="hotel-customer-panel__heading">
                            <div>
                                <span class="public-eyebrow">{{ t('common.change_your_stay', 'Change your stay') }}</span>
                                <h2 id="reschedule-heading" class="public-heading public-heading--3">{{ t('common.reschedule', 'Reschedule') }}</h2>
                            </div>
                            <i class="bi bi-calendar2-range hotel-customer-panel__icon" aria-hidden="true"></i>
                        </div>
                        <form class="hotel-customer-form" @submit.prevent="quoteReschedule">
                            <div class="hotel-customer-form__grid">
                                <label class="hotel-customer-form__label" for="reschedule-check-in">
                                    {{ t('common.new_check_in', 'New check-in') }}
                                    <input id="reschedule-check-in" v-model="rescheduleForm.check_in" class="hotel-customer-form__control" type="date" required />
                                </label>
                                <label class="hotel-customer-form__label" for="reschedule-check-out">
                                    {{ t('common.new_check_out', 'New check-out') }}
                                    <input id="reschedule-check-out" v-model="rescheduleForm.check_out" class="hotel-customer-form__control" type="date" required />
                                </label>
                            </div>
                            <p v-if="rescheduleError" class="hotel-customer-form__error" role="alert">{{ rescheduleError }}</p>
                            <button type="submit" class="public-button public-button--outline" :disabled="rescheduleLoading || rescheduleForm.processing">
                                <span v-if="rescheduleLoading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                {{ rescheduleLoading ? t('common.getting_quote', 'Getting quote…') : t('common.check_new_dates', 'Check new dates') }}
                            </button>
                        </form>

                        <div v-if="rescheduleQuote" class="hotel-customer-quote" aria-live="polite">
                            <div class="hotel-customer-change-comparison">
                                <div class="hotel-customer-change-column">
                                    <h3>{{ t('common.current_stay', 'Current stay') }}</h3>
                                    <p>{{ formatDate(rescheduleQuote.old_dates?.[0]) }} → {{ formatDate(rescheduleQuote.old_dates?.[1]) }}</p>
                                    <p><MoneyDisplay :money="rescheduleQuote.display_money?.old_total" /></p>
                                </div>
                                <div class="hotel-customer-change-column hotel-customer-change-column--new">
                                    <h3>{{ t('common.new_stay', 'New stay') }}</h3>
                                    <p>{{ formatDate(rescheduleQuote.new_dates?.[0]) }} → {{ formatDate(rescheduleQuote.new_dates?.[1]) }}</p>
                                    <p><MoneyDisplay :money="rescheduleQuote.display_money?.new_total" /></p>
                                </div>
                            </div>
                            <div class="hotel-customer-quote__row">
                                <span>{{ priceDifferenceLabel(rescheduleQuote) }}</span>
                                <strong><MoneyDisplay v-if="Number(rescheduleQuote.difference) !== 0" :money="priceDifferenceMoney(rescheduleQuote)" /><span v-else>{{ t('common.no_price_change', 'No price change') }}</span></strong>
                            </div>
                            <p v-if="!rescheduleQuote.availability" class="hotel-customer-alert" role="alert">{{ t('common.stay_unavailable', 'This stay is no longer available.') }}</p>
                            <p v-else-if="rescheduleQuote.difference < 0" class="hotel-customer-quote__policy">{{ t('common.lower_total_note', 'The new stay total is lower. Any accounting treatment follows the existing booking process.') }}</p>
                            <button v-if="rescheduleQuote.eligible" type="button" class="public-button public-button--primary" :disabled="rescheduleForm.processing" @click="confirmReschedule">
                                {{ rescheduleForm.processing ? t('common.confirming_change', 'Confirming change…') : t('common.confirm_reschedule', 'Confirm reschedule') }}
                            </button>
                            <p v-else class="hotel-customer-alert" role="alert">{{ t('common.reschedule_unavailable', 'These dates cannot be used for this booking.') }}</p>
                        </div>
                    </section>
                </main>

                <aside class="hotel-customer-detail-sidebar" aria-label="Booking summary and actions">
                    <section class="hotel-customer-panel hotel-customer-price-card" aria-labelledby="price-summary-heading">
                        <span class="public-eyebrow">{{ t('common.price_summary', 'Price summary') }}</span>
                        <h2 id="price-summary-heading" class="public-heading public-heading--3">{{ t('common.total', 'Total') }}</h2>
                        <p class="hotel-customer-price-note">{{ t('common.authoritative_booking_amount', 'Your booking amount remains recorded in the booking currency.') }}</p>
                        <div class="hotel-customer-price-row"><span>{{ t('common.subtotal', 'Room subtotal') }}</span><MoneyDisplay :money="booking.display_money?.subtotal" /></div>
                        <div v-if="booking.display_money?.taxes?.display_amount !== '0.00'" class="hotel-customer-price-row"><span>{{ t('common.taxes', 'Taxes') }}</span><MoneyDisplay :money="booking.display_money.taxes" /></div>
                        <div v-if="booking.display_money?.fees?.display_amount !== '0.00'" class="hotel-customer-price-row"><span>{{ t('common.fees', 'Fees') }}</span><MoneyDisplay :money="booking.display_money.fees" /></div>
                        <div class="hotel-customer-price-row hotel-customer-price-row--total"><strong>{{ t('common.total', 'Total') }}</strong><MoneyDisplay :money="booking.display_money?.total" /></div>
                        <p v-if="booking.display_money?.total?.conversion_applied" class="hotel-customer-approx">{{ t('common.display_currency_approx', 'Shown in your display currency; the booking currency remains authoritative.') }}</p>

                        <div class="hotel-customer-action-stack">
                            <Link v-if="canReview" :href="appUrl(`/account/hotel-bookings/${booking.id}/review`)" class="public-button public-button--primary">
                                {{ reviewEligibility.has_review ? t('common.view_review', 'View review') : t('common.write_review', 'Write review') }}
                            </Link>
                            <button v-if="canReschedule" type="button" class="public-button public-button--outline" @click="rescheduleOpen = !rescheduleOpen">
                                {{ rescheduleOpen ? t('common.close_reschedule', 'Close reschedule') : t('common.reschedule', 'Reschedule') }}
                            </button>
                            <button v-if="canCancel" type="button" class="public-button public-button--outline hotel-customer-button--danger" @click="cancellationConfirmationOpen = true">
                                {{ t('common.cancel_booking', 'Cancel booking') }}
                            </button>
                        </div>

                        <div v-if="canCancel" class="hotel-customer-quote">
                            <div class="hotel-customer-quote__row"><span>{{ t('common.cancellation_fee', 'Cancellation fee') }}</span><strong><MoneyDisplay :money="cancellationQuote.display_money?.cancellation_fee" /></strong></div>
                            <div class="hotel-customer-quote__row"><span>{{ t('common.refundable_amount', 'Refundable amount') }}</span><strong><MoneyDisplay :money="cancellationQuote.display_money?.remaining_refundable" /></strong></div>
                            <p class="hotel-customer-quote__policy">{{ policySummary(cancellationQuote.policy_summary) }}</p>
                            <p v-if="cancellationQuote.cutoff_at" class="hotel-customer-quote__cutoff">{{ t('common.free_cancellation_cutoff', 'Free cancellation cutoff') }}: {{ formatDateTime(cancellationQuote.cutoff_at) }} · {{ cancellationQuote.timezone }}</p>
                        </div>

                        <div v-if="cancellationConfirmationOpen" class="hotel-customer-confirmation" role="group" aria-labelledby="cancel-confirmation-heading">
                            <strong id="cancel-confirmation-heading">{{ t('common.confirm_cancellation', 'Confirm cancellation') }}</strong>
                            <p class="hotel-customer-quote__policy">{{ booking.property_name }} · {{ formatDate(booking.check_in) }} → {{ formatDate(booking.check_out) }}</p>
                            <div class="hotel-customer-quote__row"><span>{{ t('common.cancellation_fee', 'Cancellation fee') }}</span><strong><MoneyDisplay :money="cancellationQuote.display_money?.cancellation_fee" /></strong></div>
                            <div class="hotel-customer-quote__row"><span>{{ t('common.expected_refundable_amount', 'Expected refundable amount') }}</span><strong><MoneyDisplay :money="cancellationQuote.display_money?.remaining_refundable" /></strong></div>
                            <p class="hotel-customer-quote__policy">{{ t('common.refund_record_note', 'This records the accounting status of your refund. It does not confirm a gateway or bank settlement.') }}</p>
                            <div class="hotel-customer-booking-card__actions">
                                <button type="button" class="public-button public-button--outline" :disabled="cancelForm.processing" @click="cancellationConfirmationOpen = false">{{ t('common.keep_booking', 'Keep booking') }}</button>
                                <button type="button" class="public-button public-button--primary hotel-customer-button--danger" :disabled="cancelForm.processing" @click="cancelBooking">{{ cancelForm.processing ? t('common.cancelling', 'Cancelling…') : t('common.confirm_cancel_booking', 'Cancel booking') }}</button>
                            </div>
                            <p v-if="cancelForm.errors.booking" class="hotel-customer-form__error" role="alert">{{ cancelForm.errors.booking }}</p>
                        </div>

                        <div v-if="booking.status === 'cancelled'" class="hotel-customer-alert" role="status">{{ t('common.booking_cancelled_notice', 'This booking is cancelled. Your booking history remains available below.') }}</div>
                    </section>
                </aside>
            </div>
        </div>
    </PublicLayout>
</template>
