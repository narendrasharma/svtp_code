<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';
import Pagination from '../../../Components/Pagination.vue';
import EmptyState from '../../../Components/Public/States/EmptyState.vue';
import BookingCard from '../../../Components/Public/Hotel/Bookings/BookingCard.vue';

const props = defineProps({
    bookings: { type: Object, required: true },
    filters: { type: Object, default: () => ({ scope: 'all' }) },
    reviewsEnabled: { type: Boolean, default: false },
});

const { t } = useLocalization();

const scopes = computed(() => [
    { key: 'all', label: t('common.all_stays', 'All stays') },
    { key: 'upcoming', label: t('common.upcoming', 'Upcoming') },
    { key: 'current', label: t('common.current_stay', 'Current stay') },
    { key: 'past', label: t('common.past', 'Past') },
    { key: 'cancelled', label: t('common.cancelled', 'Cancelled') },
]);

const currentScope = computed(() => props.filters?.scope || 'all');

function scopeHref(scope) {
    return appUrl(scope === 'all' ? '/account/hotel-bookings' : `/account/hotel-bookings?scope=${scope}`);
}
</script>

<template>
    <AccountLayout>
        <SeoHead :title="t('common.my_hotel_bookings', 'My hotel bookings')" noindex private-page />

        <div class="hotel-customer-container">


            <header class="hotel-customer-header">
                <div>
                    <span class="public-eyebrow">{{ t('common.your_trips', 'Your trips') }}</span>
                    <h1 class="public-heading public-heading--1">{{ t('common.my_hotel_bookings', 'My hotel bookings') }}</h1>
                    <p>{{ t('common.my_hotel_bookings_description', 'Keep your stays, payment status and next steps together in one calm place.') }}</p>
                </div>
                <div class="hotel-customer-header__mark" aria-hidden="true"><i class="bi bi-buildings"></i></div>
            </header>

            <nav class="hotel-customer-filters" :aria-label="t('common.booking_filters', 'Booking filters')">
                <Link
                    v-for="scope in scopes"
                    :key="scope.key"
                    :href="scopeHref(scope.key)"
                    class="hotel-customer-filter"
                    :class="{ 'is-active': currentScope === scope.key }"
                    :aria-current="currentScope === scope.key ? 'page' : undefined"
                    preserve-scroll
                >
                    {{ scope.label }}
                </Link>
            </nav>

            <div v-if="bookings.data?.length" class="hotel-customer-booking-list">
                <BookingCard v-for="booking in bookings.data" :key="booking.id" :booking="booking" :reviews-enabled="reviewsEnabled" />
            </div>

            <EmptyState
                v-else
                class="hotel-customer-empty"
                :title="currentScope === 'all' ? t('common.no_bookings_yet', 'No hotel bookings yet') : t('common.no_matching_bookings', 'No bookings in this view')"
                :description="currentScope === 'all' ? t('common.no_bookings_yet_description', 'When you book a stay, its confirmation, dates and after-stay actions will appear here.') : t('common.no_matching_bookings_description', 'Try another booking view or explore a new stay.')"
            >
                <Link :href="appUrl('/search/hotels')" class="public-button public-button--primary">
                    {{ t('common.explore_hotels', 'Explore hotels') }}
                    <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                </Link>
            </EmptyState>

            <div class="hotel-customer-pagination">
                <Pagination :links="bookings.links" />
            </div>
        </div>
    </AccountLayout>
</template>
