<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';

// NOTE: on mount, load the Razorpay checkout.js script and open Checkout
// using `order` (order_id, key, amount, currency) passed from the backend.
// On success callback, POST to /bookings/{booking.id}/confirm with the
// payment signature for server-side verification (see PaymentService).
defineProps({ booking: Object, order: Object });
</script>

<template>
    <AppLayout>
        <div class="container py-5 text-center">
            <h3>Booking Reference: {{ booking.booking_reference_id }}</h3>
            <p>Redirecting you to secure payment...</p>
            <!-- Razorpay/Paytm checkout widget mounts here -->
        </div>
    </AppLayout>
</template>
