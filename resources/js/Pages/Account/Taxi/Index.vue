<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AccountNav from '../../../Components/AccountNav.vue';
import Pagination from '../../../Components/Pagination.vue';
import SeoHead from '../../../Components/SeoHead.vue';
import MoneyDisplay from '../../../Components/Public/UI/MoneyDisplay.vue';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';

const props = defineProps({ bookings: { type: Object, required: true }, filters: { type: Object, default: () => ({}) } });
const { t, locale } = useLocalization();

function date(value) { return value ? new Intl.DateTimeFormat(locale.value || 'en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—'; }
function label(value) { return t(`common.${value}`, String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())); }
function href(scope) { return appUrl(`/account/taxi/bookings?scope=${scope}`); }
</script>

<template>
    <AppLayout><SeoHead :title="t('common.my_taxi_bookings', 'My Taxi Bookings')" :noindex="true" private-page />
        <div class="public-container taxi-customer-shell"><AccountNav active="taxi" />
            <header class="taxi-customer-header"><div><span class="public-eyebrow">{{ t('common.my_account', 'My account') }}</span><h1 class="public-heading public-heading--2">{{ t('common.my_taxi_bookings', 'My Taxi Bookings') }}</h1><p>{{ t('common.taxi_bookings_intro', 'Your routes, ride details, and booking status in one place.') }}</p></div><Link :href="appUrl('/taxi')" class="btn btn-svtp">{{ t('common.book_taxi', 'Book taxi') }}</Link></header>
            <nav class="taxi-customer-filters" :aria-label="t('common.booking_filters', 'Booking filters')"><Link :href="href('all')" :class="{ active: filters.scope === 'all' }">{{ t('common.all_bookings', 'All') }}</Link><Link :href="href('upcoming')" :class="{ active: filters.scope === 'upcoming' }">{{ t('common.upcoming', 'Upcoming') }}</Link><Link :href="href('past')" :class="{ active: filters.scope === 'past' }">{{ t('common.past', 'Past') }}</Link><Link :href="href('cancelled')" :class="{ active: filters.scope === 'cancelled' }">{{ t('common.cancelled', 'Cancelled') }}</Link></nav>
            <div v-if="bookings.data?.length" class="taxi-customer-list"><article v-for="booking in bookings.data" :key="booking.id" class="taxi-customer-card"><div class="taxi-customer-card__route"><span>{{ booking.pickup_address }}</span><i class="bi bi-arrow-down" aria-hidden="true"></i><span>{{ booking.drop_address }}</span></div><div class="taxi-customer-card__facts"><div><small>{{ t('common.pickup_time', 'Pickup date and time') }}</small><strong>{{ date(booking.pickup_at) }}</strong></div><div><small>{{ t('common.vehicle', 'Vehicle') }}</small><strong>{{ booking.vehicle?.name || '—' }}</strong></div><div><small>{{ t('common.passengers', 'Passengers') }}</small><strong>{{ booking.passenger_count }}</strong></div><div><small>{{ t('common.booking_status', 'Booking status') }}</small><strong>{{ label(booking.status) }}</strong></div><div><small>{{ t('common.total', 'Total') }}</small><strong><MoneyDisplay :money="booking.total" /></strong></div></div><footer><span class="taxi-reference">{{ booking.reference }}</span><span class="taxi-status-pair"><b>{{ label(booking.status) }}</b><em>{{ label(booking.payment_status) }}</em></span><Link :href="appUrl(`/account/taxi/changes/${booking.id}`)" class="btn btn-outline-svtp">{{ t('common.view_booking', 'View booking') }}</Link></footer></article></div>
            <div v-else class="taxi-customer-empty"><i class="bi bi-signpost-2" aria-hidden="true"></i><h2>{{ t('common.no_taxi_bookings', 'No Taxi bookings yet') }}</h2><p>{{ t('common.no_taxi_bookings_note', 'Your upcoming and completed rides will appear here.') }}</p><Link :href="appUrl('/taxi')" class="btn btn-svtp">{{ t('common.book_taxi', 'Book taxi') }}</Link></div>
            <Pagination v-if="bookings.links" :links="bookings.links" />
        </div>
    </AppLayout>
</template>
