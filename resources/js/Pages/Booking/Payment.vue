<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';

// NOTE: on mount, load the Razorpay checkout.js script and open Checkout
// using `order` (order_id, key, amount, currency) passed from the backend.
// On success callback, POST to /bookings/{booking.id}/confirm with the
// payment signature for server-side verification (see PaymentService).
//
// When `order` is null the booking is recorded but unpaid: the total below
// always comes from the stored Booking snapshot, never from the browser.
defineProps({ booking: Object, order: { type: Object, default: null } });
</script>

<template>
    <AppLayout>
        <SeoHead title="Complete Payment" noindex />
        <div class="container py-5 text-center">
            <h3>Booking Reference: {{ booking.booking_reference_id }}</h3>
            <p v-if="order">Redirecting you to secure payment...</p>
            <template v-else>
                <p class="fs-4 mt-3">Total due: <strong>₹{{ Number(booking.total_amount).toLocaleString('en-IN') }}</strong></p>
                <p class="text-muted">Your booking is recorded and payment is pending.<br />Our travel team will contact you to complete payment — nothing has been charged.</p>
            </template>
            <!-- Razorpay/Paytm checkout widget mounts here -->
        </div>
    </AppLayout>
</template>
