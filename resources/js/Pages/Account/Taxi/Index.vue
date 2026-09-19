<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AccountNav from '../../../Components/AccountNav.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';
defineProps({ bookings: Object });
</script>
<template><AppLayout><div class="container py-4"><AccountNav active="taxi" /><h2>My taxi bookings</h2><div class="card table-responsive"><table class="table"><thead><tr><th>Booking</th><th>Pickup</th><th>Status</th><th>Total</th></tr></thead><tbody><tr v-for="booking in bookings.data" :key="booking.id"><td><Link :href="appUrl(`/account/taxi/changes/${booking.id}`)">{{ booking.reference }}</Link></td><td>{{ booking.pickup_at }}</td><td>{{ booking.status }}</td><td>{{ booking.currency }} {{ booking.total_amount }}</td></tr><tr v-if="!bookings.data.length"><td colspan="4">No taxi bookings linked to your account.</td></tr></tbody></table><Pagination :links="bookings.links" /></div></div></AppLayout></template>
