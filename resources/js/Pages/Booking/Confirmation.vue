<script setup>
import { computed } from 'vue';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import Container from '../../Components/Public/Layout/Container.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import MoneyDisplay from '../../Components/Public/UI/MoneyDisplay.vue';
import Badge from '../../Components/Public/UI/Badge.vue';

const props = defineProps({
    booking: { type: Object, required: true },
    payUrl: { type: String, default: null },
});

const { locale, t } = useLocalization();
const packageData = computed(() => props.booking.package ?? {});
const displayMoney = computed(() => props.booking.display_money ?? {});
const durationLabel = computed(() => {
    const days = Number(packageData.value.duration_days);
    const nights = Number(packageData.value.duration_nights);

    if (!Number.isFinite(days) || days < 1) return '';
    if (days === 1) return t('common.same_day', 'Same day');

    return `${days} ${days === 1 ? t('common.day', 'day') : t('common.days', 'days')}${nights > 0 ? ` · ${nights} ${nights === 1 ? t('common.night', 'night') : t('common.nights', 'nights')}` : ''}`;
});
const destinationLabel = computed(() => [packageData.value.destination, packageData.value.city].filter(Boolean).join(' · '));
const paymentNote = computed(() => props.booking.payment_status === 'unpaid'
    ? t('common.tour_payment_unpaid_note', 'No online payment was taken for this booking.')
    : t('common.tour_payment_record_note', 'Payment status reflects the booking record.'));

function formatDate(value) {
    if (!value) return '—';

    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value}T12:00:00`));
    } catch {
        return value;
    }
}

function statusLabel(value) {
    const labels = {
        pending: t('common.pending', 'Pending'),
        confirmed: t('common.confirmed', 'Confirmed'),
        completed: t('common.completed', 'Completed'),
        cancelled: t('common.cancelled', 'Cancelled'),
        unpaid: t('common.unpaid', 'Unpaid'),
        paid: t('common.paid', 'Paid'),
        failed: t('common.failed', 'Failed'),
    };

    return labels[value] ?? value;
}
</script>

<template>
    <PublicLayout main-class="tour-confirmation-page" :show-floating-actions="false">
        <SeoHead :title="`${t('common.booking_reference', 'Booking reference')} ${booking.booking_reference_id}`" noindex private-page />

        <Container class="tour-confirmation-container">
            <section class="tour-confirmation-hero" aria-labelledby="confirmation-heading">
                <div class="tour-confirmation-mark" aria-hidden="true"><i class="bi bi-journal-check"></i></div>
                <p class="public-eyebrow">{{ t('common.booking_received', 'Booking received') }}</p>
                <h1 id="confirmation-heading">{{ t('common.tour_booking_received', 'Your tour request is recorded') }}</h1>
                <p>{{ t('common.tour_booking_confirmation_note', 'Keep this reference for your records. The tour team will review the request and follow up using the contact details below.') }}</p>
                <p class="tour-confirmation-secure-note"><i class="bi bi-shield-check" aria-hidden="true"></i>{{ t('common.secure_guest_booking_note', 'This secure page is your guest booking record. Save the confirmation link if you need to return to it.') }}</p>
                <div class="tour-confirmation-reference">
                    <span>{{ t('common.booking_reference', 'Booking reference') }}</span>
                    <strong>{{ booking.booking_reference_id }}</strong>
                </div>
            </section>

            <div class="tour-confirmation-layout">
                <div class="tour-confirmation-main">
                    <section class="tour-confirmation-card" aria-labelledby="tour-confirmation-details">
                        <div class="tour-confirmation-card__heading">
                            <div>
                                <p class="public-eyebrow">{{ t('common.your_tour_details', 'Your tour details') }}</p>
                                <h2 id="tour-confirmation-details">{{ t('common.journey_details', 'Journey details') }}</h2>
                            </div>
                            <div class="tour-confirmation-statuses">
                                <Badge variant="neutral">{{ t('common.booking_status', 'Booking status') }}: {{ statusLabel(booking.booking_status) }}</Badge>
                                <Badge variant="accent">{{ t('common.payment_status', 'Payment status') }}: {{ statusLabel(booking.payment_status) }}</Badge>
                            </div>
                        </div>

                        <div class="tour-confirmation-recap">
                            <ImageWithFallback :src="packageData.cover_image" :alt="packageData.title || 'Tour'" aspect="editorial" kind="tour" :label="packageData.title" loading="eager" />
                            <div>
                                <strong>{{ packageData.title || '—' }}</strong>
                                <span v-if="destinationLabel"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ destinationLabel }}</span>
                                <span v-if="durationLabel"><i class="bi bi-clock" aria-hidden="true"></i>{{ durationLabel }}</span>
                            </div>
                        </div>

                        <dl class="tour-confirmation-facts">
                            <div><dt>{{ t('common.travel_date', 'Travel date') }}</dt><dd>{{ formatDate(booking.travel_date) }}</dd></div>
                            <div><dt>{{ t('common.adults', 'Adults') }}</dt><dd>{{ booking.total_adults }}</dd></div>
                            <div><dt>{{ t('common.children', 'Children') }}</dt><dd>{{ booking.total_children }}</dd></div>
                        </dl>
                    </section>

                    <section class="tour-confirmation-card" aria-labelledby="customer-confirmation-details">
                        <div class="tour-confirmation-card__heading">
                            <div>
                                <p class="public-eyebrow">{{ t('common.contact_details', 'Contact details') }}</p>
                                <h2 id="customer-confirmation-details">{{ t('common.guest_details', 'Guest details') }}</h2>
                            </div>
                        </div>
                        <dl class="tour-confirmation-contact">
                            <div><dt>{{ t('common.full_name', 'Full name') }}</dt><dd>{{ booking.customer_name }}</dd></div>
                            <div><dt>{{ t('common.phone', 'Phone') }}</dt><dd>{{ booking.customer_phone }}</dd></div>
                            <div v-if="booking.customer_email"><dt>{{ t('common.email', 'Email') }}</dt><dd>{{ booking.customer_email }}</dd></div>
                            <div v-if="booking.country"><dt>{{ t('common.country', 'Country') }}</dt><dd>{{ booking.country }}</dd></div>
                            <div v-if="booking.pickup_address"><dt>{{ t('common.pickup_request', 'Pickup request') }}</dt><dd>{{ booking.pickup_address }}</dd></div>
                            <div v-if="booking.special_requests" class="tour-confirmation-contact__wide"><dt>{{ t('common.special_requests', 'Special requests') }}</dt><dd>{{ booking.special_requests }}</dd></div>
                        </dl>
                    </section>
                </div>

                <aside class="tour-confirmation-sidebar">
                    <section class="tour-confirmation-price" aria-labelledby="confirmation-price-heading">
                        <p class="public-eyebrow">{{ t('common.booking_summary', 'Booking summary') }}</p>
                        <h2 id="confirmation-price-heading">{{ t('common.price_summary', 'Price summary') }}</h2>
                        <div class="tour-confirmation-price__rows">
                            <div><span>{{ t('common.tour_price', 'Tour price') }}</span><MoneyDisplay :money="displayMoney.base_price" /></div>
                            <div v-if="displayMoney.addons_total?.amount !== '0.00'"><span>{{ t('common.tour_extras', 'Tour extras') }}</span><MoneyDisplay :money="displayMoney.addons_total" /></div>
                            <div v-for="line in booking.addons" :key="`${line.name}-${line.quantity}`" class="tour-confirmation-price__subrow"><span>{{ line.name }} × {{ line.quantity }}</span><MoneyDisplay :money="line.total_money" /></div>
                            <div><span>{{ t('common.tour_subtotal', 'Tour subtotal') }}</span><MoneyDisplay :money="displayMoney.subtotal" /></div>
                            <div v-if="displayMoney.discount_amount?.amount !== '0.00'" class="is-discount"><span>{{ t('common.discount', 'Discount') }}<template v-if="booking.coupon_code"> ({{ booking.coupon_code }})</template></span><MoneyDisplay :money="displayMoney.discount_amount" /></div>
                            <div v-if="displayMoney.tax_amount?.amount !== '0.00'"><span>{{ t('common.taxes', 'Taxes') }}</span><MoneyDisplay :money="displayMoney.tax_amount" /></div>
                            <div class="is-total"><strong>{{ t('common.total', 'Total') }}</strong><MoneyDisplay :money="displayMoney.total_amount" /></div>
                        </div>
                        <p v-if="displayMoney.total_amount?.conversion_applied" class="tour-confirmation-price__note">{{ t('common.display_currency_approx', 'Shown in your display currency; the booking currency remains authoritative.') }}</p>
                        <p class="tour-confirmation-price__note"><i class="bi bi-info-circle" aria-hidden="true"></i>{{ paymentNote }}</p>
                    </section>
                </aside>
            </div>

            <div class="tour-confirmation-actions">
                <a v-if="packageData.slug" :href="appUrl(`/packages/${packageData.slug}`)" class="public-button public-button--primary">{{ t('common.back_to_tour', 'Back to tour') }}</a>
                <a :href="appUrl('/packages')" class="public-button public-button--outline">{{ t('common.back_to_tours', 'Back to tours') }}</a>
            </div>
        </Container>
    </PublicLayout>
</template>
