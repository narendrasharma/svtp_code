<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';
import ImageWithFallback from '../../../Components/Public/Media/ImageWithFallback.vue';
import MoneyDisplay from '../../../Components/Public/UI/MoneyDisplay.vue';
import BookingStatus from '../../../Components/Public/UI/BookingStatus.vue';
import Badge from '../../../Components/Public/UI/Badge.vue';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';

const props = defineProps({
    booking: { type: Object, required: true },
    pendingCancellation: { type: Object, default: null },
    refunds: { type: Array, default: () => [] },
    paymentSummary: { type: Object, default: () => ({}) },
    reviewEligibility: { type: Object, default: () => ({ can_review: false }) },
    review: { type: Object, default: null },
    actions: { type: Object, default: () => ({ can_cancel: false, can_review: false }) },
});

const { t, locale } = useLocalization();
const cancellationOpen = ref(false);
const cancelForm = useForm({ reason: '' });
const reviewForm = useForm({ rating: 5, comment: '' });

function formatDate(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value}T12:00:00`));
}

function formatDateTime(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat(locale.value || 'en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function durationLabel(packageData) {
    const days = Number(packageData?.duration_days);
    const nights = Number(packageData?.duration_nights);

    if (!Number.isFinite(days) || days < 1) return '';
    if (days === 1) return t('common.same_day', 'Same day');

    return `${days} ${days === 1 ? t('common.day', 'day') : t('common.days', 'days')}${nights > 0 ? ` · ${nights} ${nights === 1 ? t('common.night', 'night') : t('common.nights', 'nights')}` : ''}`;
}

function statusLabel(status) {
    return {
        pending: t('common.pending', 'Pending'),
        confirmed: t('common.confirmed', 'Confirmed'),
        completed: t('common.completed', 'Completed'),
        cancelled: t('common.cancelled', 'Cancelled'),
        unpaid: t('common.unpaid', 'Unpaid'),
        partially_paid: t('common.partially_paid', 'Partially paid'),
        paid: t('common.paid', 'Paid'),
        refunded: t('common.refunded', 'Refunded'),
    }[status] ?? status;
}

function cancellationStatusLabel(status) {
    return {
        pending: t('common.pending', 'Pending'),
        approved: t('common.approved', 'Approved'),
        rejected: t('common.rejected', 'Rejected'),
    }[status] ?? status;
}

function requestCancellation() {
    cancelForm.post(appUrl(`/account/bookings/${props.booking.id}/cancellation-requests`), {
        preserveScroll: true,
        onSuccess: () => {
            cancellationOpen.value = false;
            cancelForm.reset();
        },
    });
}

function submitReview() {
    reviewForm.post(appUrl(`/bookings/${props.booking.id}/review`), { preserveScroll: true });
}

function timelineLabel(entry) {
    if (entry.payment_from && entry.payment_from !== entry.payment_to) {
        return `${t('common.payment_status', 'Payment status')}: ${statusLabel(entry.payment_from)} → ${statusLabel(entry.payment_to)}`;
    }

    if (entry.from_status) {
        return `${t('common.booking_status', 'Booking status')}: ${statusLabel(entry.from_status)} → ${statusLabel(entry.to_status)}`;
    }

    return `${t('common.booking_status', 'Booking status')}: ${statusLabel(entry.to_status)}`;
}
</script>

<template>
    <AccountLayout>
        <SeoHead :title="`${t('common.booking_details', 'Booking details')} · ${booking.booking_reference_id}`" noindex private-page />

        <div class="tour-customer-page">
            <div class="container tour-customer-container tour-customer-detail">
                <Link :href="appUrl('/account/bookings')" class="tour-customer-back"><i class="bi bi-arrow-left" aria-hidden="true"></i>{{ t('common.my_tour_bookings', 'My Tour Bookings') }}</Link>

                <header class="tour-customer-detail__heading">
                    <div>
                        <p class="public-eyebrow">{{ t('common.booking_details', 'Booking details') }}</p>
                        <h1>{{ booking.booking_reference_id }}</h1>
                        <p>{{ t('common.tour_booking_record_intro', 'Your private record for this tour booking.') }}</p>
                    </div>
                    <div class="tour-customer-status-stack">
                        <BookingStatus :status="booking.booking_status" />
                        <BookingStatus :status="booking.payment_status" kind="payment" />
                    </div>
                </header>



                <div class="tour-customer-detail__grid">
                    <div class="tour-customer-detail__main">
                        <section class="tour-customer-panel tour-customer-tour-panel" aria-labelledby="tour-record-heading">
                            <div class="tour-customer-panel__eyebrow">{{ t('common.your_tour_details', 'Your tour details') }}</div>
                            <div class="tour-customer-tour-panel__recap">
                                <ImageWithFallback :src="booking.package?.cover_image" :alt="booking.package?.title || t('common.tour', 'Tour')" aspect="editorial" kind="tour" :label="booking.package?.title" loading="eager" />
                                <div>
                                    <h2 id="tour-record-heading">{{ booking.package?.title || t('common.tour', 'Tour') }}</h2>
                                    <p v-if="booking.package?.destination || booking.package?.city"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ [booking.package?.destination, booking.package?.city].filter(Boolean).join(' · ') }}</p>
                                    <p v-if="durationLabel(booking.package)"><i class="bi bi-clock" aria-hidden="true"></i>{{ durationLabel(booking.package) }}</p>
                                </div>
                            </div>
                            <dl class="tour-customer-detail-facts">
                                <div><dt>{{ t('common.travel_date', 'Travel date') }}</dt><dd>{{ formatDate(booking.travel_date) }}</dd></div>
                                <div><dt>{{ t('common.adults', 'Adults') }}</dt><dd>{{ booking.total_adults }}</dd></div>
                                <div><dt>{{ t('common.children', 'Children') }}</dt><dd>{{ booking.total_children }}</dd></div>
                                <div><dt>{{ t('common.booked_on', 'Booked on') }}</dt><dd>{{ formatDateTime(booking.created_at) }}</dd></div>
                            </dl>
                        </section>

                        <section class="tour-customer-panel" aria-labelledby="customer-record-heading">
                            <div class="tour-customer-panel__eyebrow">{{ t('common.customer_details', 'Customer details') }}</div>
                            <h2 id="customer-record-heading">{{ t('common.booking_contact', 'Booking contact') }}</h2>
                            <dl class="tour-customer-contact-grid">
                                <div><dt>{{ t('common.full_name', 'Full name') }}</dt><dd>{{ booking.customer.name || '—' }}</dd></div>
                                <div><dt>{{ t('common.phone', 'Phone') }}</dt><dd>{{ booking.customer.phone || '—' }}</dd></div>
                                <div v-if="booking.customer.email"><dt>{{ t('common.email', 'Email') }}</dt><dd>{{ booking.customer.email }}</dd></div>
                                <div v-if="booking.customer.country"><dt>{{ t('common.country', 'Country') }}</dt><dd>{{ booking.customer.country }}</dd></div>
                                <div v-if="booking.customer.pickup_address" class="tour-customer-contact-grid__wide"><dt>{{ t('common.pickup_request', 'Pickup request') }}</dt><dd>{{ booking.customer.pickup_address }}</dd></div>
                                <div v-if="booking.customer.special_requests" class="tour-customer-contact-grid__wide"><dt>{{ t('common.special_requests', 'Special requests') }}</dt><dd>{{ booking.customer.special_requests }}</dd></div>
                            </dl>
                        </section>

                        <section class="tour-customer-panel" aria-labelledby="timeline-heading">
                            <div class="tour-customer-panel__eyebrow">{{ t('common.booking_history', 'Booking history') }}</div>
                            <h2 id="timeline-heading">{{ t('common.booking_timeline', 'Booking timeline') }}</h2>
                            <ol v-if="booking.timeline?.length" class="tour-customer-timeline">
                                <li v-for="entry in booking.timeline" :key="entry.id">
                                    <span class="tour-customer-timeline__dot" aria-hidden="true"></span>
                                    <div><strong>{{ timelineLabel(entry) }}</strong><time :datetime="entry.created_at">{{ formatDateTime(entry.created_at) }}</time></div>
                                </li>
                            </ol>
                            <p v-else class="tour-customer-muted">{{ t('common.no_booking_history', 'No booking history is available yet.') }}</p>
                        </section>
                    </div>

                    <aside class="tour-customer-detail__sidebar">
                        <section class="tour-customer-panel tour-customer-price-panel" aria-labelledby="price-heading">
                            <div class="tour-customer-panel__eyebrow">{{ t('common.price_summary', 'Price summary') }}</div>
                            <h2 id="price-heading">{{ t('common.booking_total', 'Booking total') }}</h2>
                            <div class="tour-customer-price-rows">
                                <div><span>{{ t('common.tour_price', 'Tour price') }}</span><MoneyDisplay :money="booking.pricing.base_money" /></div>
                                <div v-if="booking.pricing.addons_money?.amount !== '0.00'"><span>{{ t('common.tour_extras', 'Tour extras') }}</span><MoneyDisplay :money="booking.pricing.addons_money" /></div>
                                <div v-if="booking.pricing.discount_money?.amount !== '0.00'" class="is-discount"><span>{{ t('common.discount', 'Discount') }}<template v-if="booking.coupon?.code"> ({{ booking.coupon.code }})</template></span><MoneyDisplay :money="booking.pricing.discount_money" /></div>
                                <div v-if="booking.pricing.tax_money?.amount !== '0.00'"><span>{{ t('common.taxes', 'Taxes') }}</span><MoneyDisplay :money="booking.pricing.tax_money" /></div>
                                <div><span>{{ t('common.tour_subtotal', 'Tour subtotal') }}</span><MoneyDisplay :money="booking.pricing.subtotal_money" /></div>
                                <div class="is-total"><strong>{{ t('common.total', 'Total') }}</strong><MoneyDisplay :money="booking.pricing.total_money" /></div>
                            </div>
                            <p v-if="booking.pricing.total_money?.conversion_applied" class="tour-customer-note">{{ t('common.display_currency_approx', 'Shown in your display currency; the booking currency remains authoritative.') }}</p>
                        </section>

                        <section v-if="booking.addons?.length" class="tour-customer-panel" aria-labelledby="addons-heading">
                            <div class="tour-customer-panel__eyebrow">{{ t('common.tour_extras', 'Tour extras') }}</div>
                            <h2 id="addons-heading">{{ t('common.booked_extras', 'Booked extras') }}</h2>
                            <ul class="tour-customer-lines">
                                <li v-for="addon in booking.addons" :key="`${addon.name}-${addon.quantity}`"><span>{{ addon.name }} × {{ addon.quantity }}</span><MoneyDisplay :money="addon.total_money" /></li>
                            </ul>
                        </section>

                        <section class="tour-customer-panel" aria-labelledby="payment-heading">
                            <div class="tour-customer-panel__eyebrow">{{ t('common.payment_status', 'Payment status') }}</div>
                            <h2 id="payment-heading">{{ booking.payment_status_label }}</h2>
                            <div class="tour-customer-lines">
                                <div><span>{{ t('common.paid_so_far', 'Paid so far') }}</span><MoneyDisplay :money="paymentSummary.paid_money" /></div>
                                <div><span>{{ t('common.outstanding_due', 'Outstanding due') }}</span><MoneyDisplay :money="paymentSummary.due_money" /></div>
                            </div>
                            <p class="tour-customer-note">{{ booking.payment_status === 'unpaid' ? t('common.tour_payment_unpaid_note', 'No online payment was taken for this booking.') : t('common.tour_payment_record_note', 'Payment status reflects the booking record.') }}</p>
                        </section>

                        <section v-if="refunds.length" class="tour-customer-panel" aria-labelledby="refund-heading">
                            <div class="tour-customer-panel__eyebrow">{{ t('common.financial_history', 'Financial history') }}</div>
                            <h2 id="refund-heading">{{ t('common.refund_history', 'Refund history') }}</h2>
                            <ul class="tour-customer-lines">
                                <li v-for="refund in refunds" :key="refund.id">
                                    <span>{{ refund.status }}<small v-if="refund.processed_at"> · {{ formatDateTime(refund.processed_at) }}</small></span>
                                    <MoneyDisplay :money="refund.money" />
                                </li>
                            </ul>
                            <p class="tour-customer-note">{{ t('common.refund_record_note', 'This records the accounting status of your refund. It does not confirm a gateway or bank settlement.') }}</p>
                        </section>

                        <section class="tour-customer-panel" aria-labelledby="actions-heading">
                            <div class="tour-customer-panel__eyebrow">{{ t('common.next_steps', 'Next steps') }}</div>
                            <h2 id="actions-heading">{{ t('common.manage_booking', 'Manage booking') }}</h2>
                            <div v-if="actions.can_cancel" class="tour-customer-action-block">
                                <button class="public-button public-button--outline public-button--danger" type="button" @click="cancellationOpen = true">{{ t('common.cancel_booking', 'Cancel booking') }}</button>
                                <p>{{ t('common.cancellation_request_note', 'Cancellation is a request for review. It does not change the booking immediately.') }}</p>
                            </div>
                            <div v-else-if="pendingCancellation" class="tour-customer-inline-status"><strong>{{ cancellationStatusLabel(pendingCancellation.status) }}</strong><span>{{ t('common.cancellation_pending_note', 'Our team will review your cancellation request and update this booking.') }}</span></div>
                            <p v-else class="tour-customer-muted">{{ t('common.cancellation_unavailable', 'Cancellation is not available for this booking online.') }}</p>
                            <p class="tour-customer-no-reschedule"><i class="bi bi-info-circle" aria-hidden="true"></i>{{ t('common.no_tour_reschedule_note', 'Tour date changes are not supported after booking.') }}</p>
                        </section>
                    </aside>
                </div>

                <section v-if="actions.can_review || review" class="tour-customer-panel tour-customer-review-panel" aria-labelledby="review-heading">
                    <div class="tour-customer-panel__eyebrow">{{ t('common.tour_review', 'Tour review') }}</div>
                    <h2 id="review-heading">{{ review ? t('common.your_review', 'Your review') : t('common.write_review', 'Write a review') }}</h2>
                    <div v-if="review" class="tour-customer-review-status">
                        <Badge :variant="review.status === 'approved' ? 'brand' : 'accent'">{{ review.status === 'approved' ? t('common.approved', 'Approved') : t('common.pending_review', 'Pending review') }}</Badge>
                        <span>{{ t('common.review_submitted_note', 'Your review was submitted and will appear publicly after moderation.') }}</span>
                        <div class="tour-customer-review-copy">
                            <strong>{{ review.rating }} ★</strong>
                            <p v-if="review.comment">{{ review.comment }}</p>
                        </div>
                    </div>
                    <form v-else class="tour-customer-review-form" @submit.prevent="submitReview">
                        <fieldset>
                            <legend>{{ t('common.rating', 'Rating') }}</legend>
                            <div class="tour-customer-rating-options">
                                <label v-for="rating in [1, 2, 3, 4, 5]" :key="rating" :class="{ 'is-selected': reviewForm.rating === rating }">
                                    <input v-model.number="reviewForm.rating" type="radio" name="rating" :value="rating">
                                    <span aria-hidden="true">{{ rating }} ★</span>
                                </label>
                            </div>
                        </fieldset>
                        <label class="public-field"><span class="public-field__label">{{ t('common.review_comment', 'Review') }} <small>({{ t('common.optional', 'optional') }})</small></span><textarea v-model="reviewForm.comment" class="public-textarea" rows="4" maxlength="1000" :placeholder="t('common.review_placeholder', 'How was your tour?')"></textarea></label>
                        <p v-if="reviewForm.errors.booking || reviewForm.errors.rating || reviewForm.errors.comment" class="tour-customer-field-error" role="alert">{{ reviewForm.errors.booking || reviewForm.errors.rating || reviewForm.errors.comment }}</p>
                        <button class="public-button public-button--primary" type="submit" :disabled="reviewForm.processing">{{ t('common.submit_review', 'Submit review') }}</button>
                    </form>
                </section>

                <div v-if="cancellationOpen" class="tour-customer-dialog-backdrop" role="presentation" @click.self="cancellationOpen = false">
                    <section class="tour-customer-dialog" role="dialog" aria-modal="true" aria-labelledby="cancel-heading">
                        <p class="public-eyebrow">{{ t('common.cancel_booking', 'Cancel booking') }}</p>
                        <h2 id="cancel-heading">{{ t('common.confirm_cancellation', 'Request cancellation?') }}</h2>
                        <p>{{ booking.package?.title }} · {{ formatDate(booking.travel_date) }}</p>
                        <label class="public-field"><span class="public-field__label">{{ t('common.cancellation_reason', 'Cancellation reason') }} <small>({{ t('common.optional', 'optional') }})</small></span><textarea v-model="cancelForm.reason" class="public-textarea" rows="4" maxlength="1000"></textarea></label>
                        <p v-if="cancelForm.errors.booking || cancelForm.errors.reason" class="tour-customer-field-error" role="alert">{{ cancelForm.errors.booking || cancelForm.errors.reason }}</p>
                        <div class="tour-customer-dialog__actions"><button class="public-button public-button--ghost" type="button" @click="cancellationOpen = false">{{ t('common.keep_booking', 'Keep booking') }}</button><button class="public-button public-button--danger" type="button" :disabled="cancelForm.processing" @click="requestCancellation">{{ t('common.send_cancellation_request', 'Send request') }}</button></div>
                    </section>
                </div>
            </div>
        </div>
    </AccountLayout>
</template>
