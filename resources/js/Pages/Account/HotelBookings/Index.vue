<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AccountNav from '../../../Components/AccountNav.vue';
import Pagination from '../../../Components/Pagination.vue';
import ReviewBookingAction from '../../../Components/Hotel/ReviewBookingAction.vue';
import { appUrl } from '../../../appUrl';
defineProps({ bookings: Object, reviewsEnabled: Boolean });
</script>
<template>
    <AppLayout>
        <div class="container py-5">
            <h1>My Hotel Bookings</h1>
            <AccountNav active="hotels" />
            <div v-for="booking in bookings.data" :key="booking.id" class="card p-3 mb-2">
                <div class="d-flex flex-wrap justify-content-between gap-2"><strong>{{ booking.booking_number }}</strong><span>{{ booking.status }}</span></div>
                <div>{{ booking.property_name_snapshot }}</div>
                <small class="text-muted">{{ booking.check_in?.slice(0, 10) }} → {{ booking.check_out?.slice(0, 10) }} · {{ booking.currency }} {{ booking.total }}</small>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <Link class="btn btn-sm btn-outline-primary" :href="appUrl(`/account/hotel-bookings/${booking.id}`)">View booking</Link>
                    <ReviewBookingAction :booking-id="booking.id" :eligibility="booking.review" :enabled="reviewsEnabled" />
                </div>
            </div>
            <p v-if="!bookings.data.length" class="text-muted">No hotel bookings yet.</p>
            <Pagination :links="bookings.links" />
        </div>
    </AppLayout>
</template>
