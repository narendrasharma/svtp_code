<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../../../appUrl';
import { useLocalization } from '../../../../i18n';
import ImageWithFallback from '../../Media/ImageWithFallback.vue';
import MoneyDisplay from '../../UI/MoneyDisplay.vue';
import BookingStatus from './BookingStatus.vue';

const props = defineProps({
    booking: { type: Object, required: true },
    reviewsEnabled: { type: Boolean, default: false },
});

const { locale, t } = useLocalization();

const detailHref = computed(() => appUrl(`/account/hotel-bookings/${props.booking.id}`));
const dateRange = computed(() => [formatDate(props.booking.check_in), formatDate(props.booking.check_out)].join(' → '));
const reviewAction = computed(() => {
    if (!props.reviewsEnabled || !props.booking.review?.can_review && !props.booking.review?.has_review) return null;

    return props.booking.review.has_review
        ? { label: t('common.view_review', 'View review'), icon: 'bi-chat-square-heart' }
        : { label: t('common.write_review', 'Write review'), icon: 'bi-pencil-square' };
});

function formatDate(value) {
    if (!value) return '—';

    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' })
            .format(new Date(`${value}T12:00:00`));
    } catch {
        return value;
    }
}
</script>

<template>
    <article class="hotel-customer-booking-card">
        <Link :href="detailHref" class="hotel-customer-booking-card__media" :aria-label="`${t('common.view_booking', 'View booking')}: ${booking.property_name}`">
            <ImageWithFallback :src="booking.property_image" :alt="booking.property_name" aspect="editorial" kind="hotel" :label="booking.property_name" />
        </Link>

        <div class="hotel-customer-booking-card__body">
            <div class="hotel-customer-booking-card__topline">
                <div>
                    <span class="public-eyebrow">{{ t('common.hotel_stay', 'Hotel stay') }}</span>
                    <h2 class="hotel-customer-booking-card__title"><Link :href="detailHref">{{ booking.property_name }}</Link></h2>
                    <p class="hotel-customer-booking-card__reference">{{ t('common.booking_reference', 'Booking reference') }} <strong>{{ booking.booking_number }}</strong></p>
                </div>
                <BookingStatus :status="booking.status" />
            </div>

            <div class="hotel-customer-booking-card__stay">
                <div>
                    <span class="hotel-customer-booking-card__label">{{ t('common.stay_dates', 'Stay dates') }}</span>
                    <strong>{{ dateRange }}</strong>
                </div>
                <div>
                    <span class="hotel-customer-booking-card__label">{{ t('common.room_type', 'Room type') }}</span>
                    <strong>{{ booking.room_type || t('common.accommodation', 'Accommodation') }}<span v-if="booking.room_quantity > 1"> · {{ booking.room_quantity }} {{ t('common.rooms', 'rooms') }}</span></strong>
                </div>
            </div>

            <div class="hotel-customer-booking-card__footer">
                <div class="hotel-customer-booking-card__payment">
                    <span class="hotel-customer-booking-card__label">{{ t('common.payment_status', 'Payment status') }}</span>
                    <BookingStatus :status="booking.payment_status" kind="payment" />
                </div>
                <div class="hotel-customer-booking-card__amount">
                    <span class="hotel-customer-booking-card__label">{{ t('common.total', 'Total') }}</span>
                    <MoneyDisplay :money="booking.display_money?.total" />
                </div>
                <div class="hotel-customer-booking-card__actions">
                    <Link :href="detailHref" class="public-button public-button--primary public-button--sm">
                        {{ t('common.view_booking', 'View booking') }}
                        <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                    </Link>
                    <Link v-if="reviewAction" :href="appUrl(`/account/hotel-bookings/${booking.id}/review`)" class="public-button public-button--ghost public-button--sm">
                        <i class="bi" :class="reviewAction.icon" aria-hidden="true"></i>
                        {{ reviewAction.label }}
                    </Link>
                </div>
            </div>
        </div>
    </article>
</template>
