<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import MoneyDisplay from '../../Components/Public/UI/MoneyDisplay.vue';

const props = defineProps({
    booking: { type: Object, required: true },
    seo: { type: Object, default: () => ({}) },
});

const { locale, t } = useLocalization();
const item = computed(() => props.booking.items?.[0] || {});
const propertyHref = computed(() => {
    if (!props.booking.property_slug) return appUrl('/search/hotels');
    return appUrl('/hotels/' + props.booking.property_slug);
});

function formatDate(value) {
    if (!value) return '—';
    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value + 'T12:00:00'));
    } catch {
        return value;
    }
}

function labelFor(value) {
    const labels = {
        confirmed: t('common.confirmed', 'Confirmed'),
        pending: t('common.pending', 'Pending'),
        cancelled: t('common.cancelled', 'Cancelled'),
        unpaid: t('common.unpaid', 'Unpaid'),
        paid: t('common.paid', 'Paid'),
        partially_paid: t('common.partially_paid', 'Partially paid'),
        refunded: t('common.refunded', 'Refunded'),
        partially_refunded: t('common.partially_refunded', 'Partially refunded'),
        failed: t('common.failed', 'Failed'),
    };
    return labels[value] || String(value || '').replaceAll('_', ' ');
}
</script>

<template>
    <PublicLayout main-class="hotel-confirmation-page">
        <SeoHead :title="seo.title" :description="seo.description" :noindex="true" />

        <main>
            <div class="public-container hotel-confirmation-container">
                <section class="hotel-confirmation-hero" aria-labelledby="confirmation-heading">
                    <div class="hotel-confirmation-mark" aria-hidden="true"><i class="bi bi-check2"></i></div>
                    <span class="public-eyebrow">{{ t('common.booking_confirmed', 'Booking confirmed') }}</span>
                    <h1 id="confirmation-heading" class="public-heading public-heading--1">{{ t('common.your_stay_is_confirmed', 'Your stay is confirmed') }}</h1>
                    <p>{{ t('common.booking_confirmation_description', 'Your reservation has been recorded with the property.') }}</p>
                    <div class="hotel-confirmation-reference">
                        <span>{{ t('common.booking_reference', 'Booking reference') }}</span>
                        <strong>{{ booking.booking_number }}</strong>
                    </div>
                </section>

                <div class="hotel-confirmation-layout">
                    <section class="hotel-confirmation-main" aria-labelledby="confirmation-details-heading">
                        <div class="hotel-confirmation-card">
                            <div class="hotel-confirmation-card__heading">
                                <div>
                                    <span class="public-eyebrow">{{ t('common.your_stay', 'Your stay') }}</span>
                                    <h2 id="confirmation-details-heading">{{ booking.property_name }}</h2>
                                </div>
                                <span class="public-badge public-badge--brand">{{ labelFor(booking.status) }}</span>
                            </div>
                            <div class="hotel-confirmation-recap">
                                <div>
                                    <strong>{{ item.room_type }}</strong>
                                    <span>{{ item.rate_plan }}</span>
                                    <span v-if="item.meal_plan">{{ item.meal_plan }}</span>
                                </div>
                                <div class="hotel-confirmation-recap__policy" v-if="item.cancellation_mode">
                                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                                    <span>{{ item.cancellation_mode === 'non_refundable' ? t('common.non_refundable', 'Non-refundable') : t('common.cancellation_applies', 'Cancellation terms apply') }}</span>
                                </div>
                            </div>
                            <dl class="hotel-confirmation-facts">
                                <div><dt>{{ t('common.check_in', 'Check-in') }}</dt><dd>{{ formatDate(booking.check_in) }}</dd></div>
                                <div><dt>{{ t('common.check_out', 'Check-out') }}</dt><dd>{{ formatDate(booking.check_out) }}</dd></div>
                                <div><dt>{{ t('common.guests', 'Guests') }}</dt><dd>{{ booking.adults }} {{ t('common.adults', 'adults') }}<span v-if="booking.children"> · {{ booking.children }} {{ t('common.children', 'children') }}</span></dd></div>
                                <div><dt>{{ t('common.rooms', 'Rooms') }}</dt><dd>{{ booking.rooms_count }}</dd></div>
                            </dl>
                        </div>

                        <div class="hotel-confirmation-card hotel-confirmation-contact">
                            <span class="public-eyebrow">{{ t('common.contact_details', 'Contact details') }}</span>
                            <h2>{{ booking.guest_name }}</h2>
                            <p>{{ booking.guest_email }} · {{ booking.guest_phone }}</p>
                            <p v-if="booking.special_requests" class="hotel-confirmation-note">{{ booking.special_requests }}</p>
                        </div>
                    </section>

                    <aside class="hotel-confirmation-sidebar" aria-labelledby="confirmation-price-heading">
                        <div class="hotel-confirmation-card hotel-confirmation-price">
                            <span class="public-eyebrow">{{ t('common.payment_status', 'Payment status') }}</span>
                            <h2 id="confirmation-price-heading">{{ labelFor(booking.payment_status) }}</h2>
                            <p class="hotel-confirmation-price__note">{{ t('common.payment_status_note', 'No online payment was taken for this reservation.') }}</p>
                            <div class="hotel-booking-price-row"><span>{{ t('common.subtotal', 'Room subtotal') }}</span><MoneyDisplay :money="booking.display_money?.subtotal" /></div>
                            <div v-if="booking.display_money?.taxes?.display_amount !== '0.00'" class="hotel-booking-price-row"><span>{{ t('common.taxes', 'Taxes') }}</span><MoneyDisplay :money="booking.display_money.taxes" /></div>
                            <div v-if="booking.display_money?.fees?.display_amount !== '0.00'" class="hotel-booking-price-row"><span>{{ t('common.fees', 'Fees') }}</span><MoneyDisplay :money="booking.display_money.fees" /></div>
                            <div class="hotel-booking-price-row hotel-booking-price-row--total"><strong>{{ t('common.total', 'Total') }}</strong><MoneyDisplay :money="booking.display_money?.total" /></div>
                            <p v-if="booking.display_money?.total?.conversion_applied" class="hotel-booking-summary__approx">{{ t('common.display_currency_approx', 'Shown in your display currency; the booking currency remains authoritative.') }}</p>
                        </div>
                    </aside>
                </div>

                <nav class="hotel-confirmation-actions" aria-label="Booking actions">
                    <Link :href="appUrl('/account/hotel-bookings/' + booking.id)" class="public-button public-button--primary">{{ t('common.view_booking', 'View booking') }}</Link>
                    <Link :href="propertyHref" class="public-button public-button--outline">{{ t('common.back_to_property', 'Back to property') }}</Link>
                    <Link :href="appUrl('/search/hotels')" class="public-button public-button--ghost">{{ t('common.back_to_hotels', 'Back to hotels') }}</Link>
                </nav>
            </div>
        </main>
    </PublicLayout>
</template>
