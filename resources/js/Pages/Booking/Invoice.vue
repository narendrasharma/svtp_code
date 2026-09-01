<script setup>
import { appUrl } from '../../appUrl';
import AppLayout from '../../Layouts/AppLayout.vue';
import QrcodeVue from 'qrcode.vue';

defineProps({ booking: Object });
</script>

<template>
    <AppLayout>
        <div class="container py-4 text-center" style="max-width: 480px;">
            <h3>Booking Confirmed!</h3>
            <p>{{ booking.package.title }}</p>
            <QrcodeVue :value="booking.qr_code_string" :size="200" class="my-3" />
            <p class="text-muted small">Show this QR code at check-in for verification.</p>
            <a :href="`${appUrl('/bookings')}/${booking.id}/invoice/download`" class="btn btn-svtp">Download Invoice PDF</a>
        </div>
    </AppLayout>
</template>
