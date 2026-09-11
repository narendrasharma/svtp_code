<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from "@/Components/SeoHead.vue";
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Terms & Conditions"
            noindex
        />
        <div class="container py-5" style="max-width: 800px;">
            <h1 class="brand-heading">Terms &amp; Conditions</h1>
            <p class="text-muted small">Placeholder content — replace with your actual terms before launch.</p>

            <h5 class="mt-4 text-svtp">Booking &amp; Payment</h5>
            <p>A confirmation advance is required to lock in your travel dates and vehicle. The balance is payable as per the schedule shared at the time of booking.</p>

            <h5 class="mt-4 text-svtp">Cancellation Policy</h5>
            <p>Cancellations made [X] days before travel are eligible for a partial refund of the advance; cancellations closer to the travel date may not be refundable. Exact terms depend on the package and vehicle booked.</p>

            <h5 class="mt-4 text-svtp">Itinerary Changes</h5>
            <p>Temple timings, festival crowd conditions, and weather may require last-minute route adjustments. We'll always aim to keep the spirit of your original plan.</p>

            <h5 class="mt-4 text-svtp">Liability</h5>
            <p>Shree Vrindavan Tour Packages arranges transport, guidance and bookings in good faith but is not liable for delays or closures outside our control (temple closures, road conditions, government restrictions, etc.).</p>
        </div>
    </AppLayout>
</template>
