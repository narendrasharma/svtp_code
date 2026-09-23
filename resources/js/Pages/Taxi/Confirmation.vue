<script setup>
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import MoneyDisplay from '../../Components/Public/UI/MoneyDisplay.vue';

defineProps({ booking: { type: Object, required: true }, seo: { type: Object, default: () => ({}) } });
const { t, locale } = useLocalization();

function formatDate(value) {
    if (!value) return '—';
    return new Intl.DateTimeFormat(locale.value || 'en', { dateStyle: 'full', timeStyle: 'short' }).format(new Date(value));
}

function label(value) { return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
</script>

<template>
    <PublicLayout main-class="public-taxi-page">
        <SeoHead :title="seo.title || t('common.taxi_confirmation', 'Taxi confirmation')" :noindex="true" private-page />
        <main class="public-container taxi-confirmation-shell">
            <section class="taxi-confirmation-card">
                <div class="taxi-confirmation-icon"><i class="bi bi-check2" aria-hidden="true"></i></div>
                <span class="public-eyebrow">{{ t('common.taxi_booking_received', 'Taxi booking received') }}</span>
                <h1 class="public-heading public-heading--1">{{ t('common.taxi_booking_confirmed', 'Your ride request is recorded') }}</h1>
                <p class="taxi-confirmation-lead">{{ t('common.taxi_confirmation_note', 'Keep this reference for your records. Driver assignment and payment follow the booking status shown below.') }}</p>
                <p v-if="booking.guest_recovery_available" class="taxi-confirmation-recovery"><i class="bi bi-envelope me-2" aria-hidden="true"></i>{{ t('common.taxi_guest_recovery_note', 'We sent a secure booking link to the email provided. Keep it to return to this booking later.') }}</p>
                <div class="taxi-confirmation-reference"><span>{{ t('common.booking_reference', 'Booking reference') }}</span><strong>{{ booking.reference }}</strong></div>
                <div class="taxi-confirmation-grid"><div><span>{{ t('common.pickup', 'Pickup') }}</span><strong>{{ booking.pickup_address }}</strong></div><div><span>{{ t('common.drop', 'Dropoff') }}</span><strong>{{ booking.drop_address }}</strong></div><div><span>{{ t('common.pickup_time', 'Pickup date and time') }}</span><strong>{{ formatDate(booking.pickup_at) }}</strong></div><div><span>{{ t('common.vehicle', 'Vehicle') }}</span><strong>{{ booking.vehicle?.name || '—' }}</strong></div><div><span>{{ t('common.passengers', 'Passengers') }}</span><strong>{{ booking.passenger_count }}</strong></div><div><span>{{ t('common.total', 'Total') }}</span><strong><MoneyDisplay :money="booking.total" /></strong></div></div>
                <div class="taxi-confirmation-status"><span><b>{{ t('common.booking_status', 'Booking status') }}</b>{{ label(booking.status) }}</span><span><b>{{ t('common.payment_status', 'Payment status') }}</b>{{ label(booking.payment_status) }}</span></div>
                <div class="d-flex flex-wrap gap-2 mt-4"><Link :href="appUrl('/taxi')" class="btn btn-svtp">{{ t('common.book_another_taxi', 'Book another taxi') }}</Link><Link :href="appUrl('/')" class="btn btn-outline-svtp">{{ t('common.back_home', 'Back home') }}</Link></div>
            </section>
        </main>
    </PublicLayout>
</template>

<style scoped>
.taxi-confirmation-shell { padding:5rem 0; }.taxi-confirmation-card { max-width:52rem; margin:auto; padding:2.5rem; background:#fffdf9; border:1px solid rgba(18,63,67,.12); border-radius:1.5rem; box-shadow:0 20px 55px rgba(27,48,49,.09); }.taxi-confirmation-icon { display:grid; place-items:center; width:3.5rem; height:3.5rem; margin-bottom:1.25rem; border-radius:50%; background:#e8f0ed; color:#123f43; font-size:1.7rem; }.taxi-confirmation-lead { max-width:40rem; color:#687477; }.taxi-confirmation-recovery { max-width:40rem; margin:1rem 0 0; padding:.85rem 1rem; border-radius:.85rem; background:#eef5f1; color:#36575a; }.taxi-confirmation-reference { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin:2rem 0 1rem; padding:1rem 1.1rem; border-radius:1rem; background:#123f43; color:#fff8ed; }.taxi-confirmation-reference span { color:#b7cbca; }.taxi-confirmation-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }.taxi-confirmation-grid div { display:grid; gap:.3rem; padding:1rem; border:1px solid #dce5e1; border-radius:.9rem; }.taxi-confirmation-grid span,.taxi-confirmation-status b { color:#748083; font-size:.78rem; font-weight:600; }.taxi-confirmation-status { display:flex; flex-wrap:wrap; gap:1rem; margin-top:1rem; padding-top:1rem; border-top:1px solid #dce5e1; }.taxi-confirmation-status span { display:grid; gap:.2rem; }.taxi-confirmation-status b { font-weight:400; }.taxi-confirmation-card .btn-svtp { background:#123f43; color:#fff8ed; }.taxi-confirmation-card .btn-outline-svtp { border-color:#123f43; color:#123f43; }
@media (max-width:600px) { .taxi-confirmation-shell { padding:2.5rem 0; }.taxi-confirmation-card { padding:1.25rem; }.taxi-confirmation-grid { grid-template-columns:1fr; }.taxi-confirmation-reference { align-items:flex-start; flex-direction:column; } }
</style>
