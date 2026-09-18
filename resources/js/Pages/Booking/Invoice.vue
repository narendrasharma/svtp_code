<script setup>
import { appUrl } from '../../appUrl';
import AppLayout from '../../Layouts/AppLayout.vue';
import QrcodeVue from 'qrcode.vue';

const props = defineProps({ receipt: { type: Object, required: true } });

const booking = props.receipt.booking;
const downloadUrl = appUrl(`/bookings/${booking.id}/invoice/download`);

function money(value) {
    return `${booking.currency ?? '₹'}${Number(value ?? 0).toFixed(2)}`;
}
function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN') : '—';
}
function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
function printReceipt() {
    window.print();
}
</script>

<template>
    <AppLayout>
        <div class="container py-4 receipt-wrap">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 no-print">
                <h3 class="mb-0">Booking Receipt</h3>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-svtp" @click="printReceipt"><i class="bi bi-printer me-1"></i>Print</button>
                    <a :href="downloadUrl" class="btn btn-svtp">Download PDF</a>
                </div>
            </div>

            <div class="receipt card p-3 p-md-5">
                <div class="d-flex flex-wrap justify-content-between gap-3 border-bottom pb-3 mb-3">
                    <div>
                        <h4 class="mb-1">{{ receipt.site.name }}</h4>
                        <div v-if="receipt.site.tagline" class="text-muted small">{{ receipt.site.tagline }}</div>
                        <div v-if="receipt.site.email" class="small">{{ receipt.site.email }}</div>
                        <div v-if="receipt.site.phone" class="small">{{ receipt.site.phone }}</div>
                        <div v-if="receipt.site.address" class="small text-muted">{{ receipt.site.address }}</div>
                    </div>
                    <div class="text-md-end">
                        <div class="fw-bold fs-5">{{ booking.booking_reference_id }}</div>
                        <div class="small text-muted">Issued {{ formatDateTime(booking.created_at) }}</div>
                        <div class="mt-1"><span class="badge bg-secondary me-1">{{ booking.booking_status }}</span><span class="badge bg-secondary">{{ booking.payment_status }}</span></div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small">Billed to</h6>
                        <div class="fw-semibold">{{ receipt.customer.name }}</div>
                        <div v-if="receipt.customer.email" class="small">{{ receipt.customer.email }}</div>
                        <div v-if="receipt.customer.phone" class="small">{{ receipt.customer.phone }}</div>
                        <div v-if="receipt.customer.country" class="small text-muted">{{ receipt.customer.country }}</div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted text-uppercase small">Tour</h6>
                        <div class="fw-semibold">{{ receipt.tour?.title ?? '—' }}</div>
                        <div class="small">Travel date: {{ formatDate(booking.travel_date) }}</div>
                        <div class="small">Guests: {{ booking.total_adults }} adult(s)<span v-if="booking.total_children">, {{ booking.total_children }} child(ren)</span></div>
                        <div v-if="receipt.tour?.duration_days" class="small text-muted">{{ receipt.tour.duration_days }} day(s)<span v-if="receipt.tour.duration_nights"> / {{ receipt.tour.duration_nights }} night(s)</span></div>
                    </div>
                </div>

                <table class="table align-middle">
                    <thead><tr><th>Description</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <tr><td>Tour package ({{ booking.total_adults }} adult(s)<span v-if="booking.total_children">, {{ booking.total_children }} child(ren)</span>)</td><td class="text-end">{{ money(receipt.pricing.subtotal) }}</td></tr>
                        <tr v-for="(line, i) in receipt.addons ?? []" :key="i"><td>{{ line.name }} × {{ line.quantity }}</td><td class="text-end">{{ money(line.total_amount) }}</td></tr>
                        <tr v-if="Number(receipt.pricing.discount_amount)"><td>Discount{{ receipt.pricing.coupon_code ? ` (${receipt.pricing.coupon_code})` : '' }}</td><td class="text-end text-success">−{{ money(receipt.pricing.discount_amount) }}</td></tr>
                        <tr v-if="Number(receipt.pricing.tax_amount)"><td>Tax</td><td class="text-end">{{ money(receipt.pricing.tax_amount) }}</td></tr>
                        <tr class="fw-bold"><td>Original total</td><td class="text-end">{{ money(receipt.pricing.gross_amount) }}</td></tr>
                        <tr v-if="Number(receipt.refunds.total)"><td>Refunded</td><td class="text-end text-danger">−{{ money(receipt.refunds.total) }}</td></tr>
                        <tr v-if="Number(receipt.refunds.total)" class="fw-bold fs-5"><td>Net paid</td><td class="text-end">{{ money(receipt.refunds.net) }}</td></tr>
                    </tbody>
                </table>

                <div v-if="receipt.refunds.history?.length" class="mb-3">
                    <h6 class="text-muted text-uppercase small">Refund history</h6>
                    <ul class="list-unstyled small mb-0">
                        <li v-for="(refund, i) in receipt.refunds.history" :key="i" class="d-flex justify-content-between border-bottom py-1">
                            <span>{{ refund.reason }} · {{ formatDate(refund.processed_at) }}</span><span>−{{ money(refund.amount) }}</span>
                        </li>
                    </ul>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-3 mt-4">
                    <QrcodeVue :value="receipt.qr_code_string" :size="140" />
                    <p class="text-muted small mb-0">Show this QR code at check-in for verification.<br />Payment status: <strong>{{ booking.payment_status }}</strong></p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.receipt { border-radius: 12px; }
@media print {
    .no-print { display: none !important; }
    .receipt-wrap { max-width: 100% !important; padding: 0 !important; }
    .receipt { border: 0 !important; }
}
</style>
