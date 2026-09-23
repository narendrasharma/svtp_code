<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';
import AccountNav from '../../../Components/AccountNav.vue';
import Pagination from '../../../Components/Pagination.vue';
import ImageWithFallback from '../../../Components/Public/Media/ImageWithFallback.vue';
import MoneyDisplay from '../../../Components/Public/UI/MoneyDisplay.vue';
import Badge from '../../../Components/Public/UI/Badge.vue';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';

const props = defineProps({
    bookings: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const { t, locale } = useLocalization();
const endpoint = appUrl('/account/bookings');
const filters = useForm({
    search: props.filters.search ?? '',
    scope: props.filters.scope ?? '',
});

const scopes = computed(() => [
    { value: '', label: t('common.all_tours', 'All tours') },
    { value: 'upcoming', label: t('common.upcoming', 'Upcoming') },
    { value: 'past', label: t('common.past', 'Past') },
]);

function applyFilters() {
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    filters.reset();
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}

function formatDate(value) {
    if (!value) return '—';

    return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value}T12:00:00`));
}

function durationLabel(packageData) {
    const days = Number(packageData?.duration_days);
    const nights = Number(packageData?.duration_nights);

    if (!Number.isFinite(days) || days < 1) return '';
    if (days === 1) return t('common.same_day', 'Same day');

    return `${days} ${days === 1 ? t('common.day', 'day') : t('common.days', 'days')}${nights > 0 ? ` · ${nights} ${nights === 1 ? t('common.night', 'night') : t('common.nights', 'nights')}` : ''}`;
}

function statusVariant(status) {
    return status === 'cancelled' ? 'neutral' : status === 'confirmed' || status === 'completed' ? 'brand' : 'accent';
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
</script>

<template>
    <AppLayout>
        <SeoHead :title="t('common.my_tour_bookings', 'My Tour Bookings')" noindex private-page />

        <main class="tour-customer-page">
            <div class="container tour-customer-container">
                <div class="tour-customer-heading">
                    <div>
                        <p class="public-eyebrow">{{ t('common.my_account', 'My account') }}</p>
                        <h1>{{ t('common.my_tour_bookings', 'My Tour Bookings') }}</h1>
                        <p>{{ t('common.my_tour_bookings_intro', 'Keep track of your tour plans, booking details, and next steps in one place.') }}</p>
                    </div>
                    <Link :href="appUrl('/packages')" class="public-button public-button--primary">
                        {{ t('common.explore_tours', 'Explore tours') }}
                    </Link>
                </div>

                <AccountNav active="bookings" />

                <form class="tour-customer-filters" @submit.prevent="applyFilters">
                    <label class="tour-customer-filter-field">
                        <span>{{ t('common.booking_reference', 'Booking reference') }}</span>
                        <input v-model="filters.search" class="public-input" type="search" :placeholder="t('common.booking_reference_placeholder', 'BK-…')">
                    </label>
                    <label class="tour-customer-filter-field">
                        <span>{{ t('common.trip_view', 'Trip view') }}</span>
                        <select v-model="filters.scope" class="public-input">
                            <option v-for="scope in scopes" :key="scope.value" :value="scope.value">{{ scope.label }}</option>
                        </select>
                    </label>
                    <div class="tour-customer-filter-actions">
                        <button class="public-button public-button--outline" type="submit" :disabled="filters.processing">{{ t('common.filter', 'Filter') }}</button>
                        <button class="public-button public-button--ghost" type="button" @click="clearFilters">{{ t('common.clear', 'Clear') }}</button>
                    </div>
                </form>

                <section v-if="bookings.data?.length" class="tour-customer-booking-grid" aria-live="polite">
                    <article v-for="booking in bookings.data" :key="booking.id" class="tour-customer-booking-card">
                        <div class="tour-customer-booking-card__media">
                            <ImageWithFallback :src="booking.package?.cover_image" :alt="booking.package?.title || t('common.tour', 'Tour')" aspect="editorial" kind="tour" :label="booking.package?.title" />
                            <span class="tour-customer-booking-card__reference">{{ booking.booking_reference_id }}</span>
                        </div>
                        <div class="tour-customer-booking-card__body">
                            <div class="tour-customer-booking-card__status-row">
                                <Badge :variant="statusVariant(booking.booking_status)">{{ statusLabel(booking.booking_status) }}</Badge>
                                <span class="tour-customer-booking-card__payment">{{ statusLabel(booking.payment_status) }}</span>
                            </div>
                            <h2>{{ booking.package?.title || t('common.tour', 'Tour') }}</h2>
                            <p v-if="booking.package?.destination || booking.package?.city" class="tour-customer-booking-card__location">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>{{ [booking.package?.destination, booking.package?.city].filter(Boolean).join(' · ') }}
                            </p>
                            <p v-if="durationLabel(booking.package)" class="tour-customer-booking-card__duration"><i class="bi bi-clock" aria-hidden="true"></i>{{ durationLabel(booking.package) }}</p>
                            <dl class="tour-customer-booking-card__facts">
                                <div><dt>{{ t('common.travel_date', 'Travel date') }}</dt><dd>{{ formatDate(booking.travel_date) }}</dd></div>
                                <div><dt>{{ t('common.party', 'Party') }}</dt><dd>{{ booking.total_adults }} {{ t('common.adults_short', 'adults') }}<span v-if="booking.total_children"> · {{ booking.total_children }} {{ t('common.children_short', 'children') }}</span></dd></div>
                                <div><dt>{{ t('common.total', 'Total') }}</dt><dd><MoneyDisplay :money="booking.total_money" /></dd></div>
                            </dl>
                            <Link :href="appUrl(`/account/bookings/${booking.id}`)" class="public-button public-button--outline tour-customer-booking-card__action">
                                {{ t('common.view_booking', 'View booking') }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                            </Link>
                        </div>
                    </article>
                </section>

                <section v-else class="tour-customer-empty">
                    <div class="tour-customer-empty__icon" aria-hidden="true"><i class="bi bi-compass"></i></div>
                    <p class="public-eyebrow">{{ t('common.your_next_journey', 'Your next journey') }}</p>
                    <h2>{{ t('common.no_tour_bookings', 'No Tour bookings yet') }}</h2>
                    <p>{{ t('common.no_tour_bookings_note', 'Your confirmed and upcoming tour plans will appear here.') }}</p>
                    <Link :href="appUrl('/packages')" class="public-button public-button--primary">{{ t('common.explore_tours', 'Explore tours') }}</Link>
                </section>

                <Pagination :links="bookings.links" />
            </div>
        </main>
    </AppLayout>
</template>
