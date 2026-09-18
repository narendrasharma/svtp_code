<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    booking: Object,
    payUrl: String,
});

const payForm = useForm({});
const canPay = props.booking.booking_status === 'pending' && props.booking.payment_status === 'unpaid';

function pay() {
    payForm.post(props.payUrl);
}
function money(value) {
    return `₹${Number(value ?? 0).toLocaleString('en-IN')}`;
}
</script>

<template>
    <AppLayout>
        <SeoHead :title="`Booking ${booking.booking_reference_id}`" noindex />
        <div class="container py-5" style="max-width: 860px;">
            <div class="text-center mb-4">
                <i class="bi bi-check-circle-fill text-success" style="font-size: 3.5rem;"></i>
                <p class="section-eyebrow mt-3">Booking received</p>
                <h1 class="section-title">Thank You, {{ booking.customer_name }}!</h1>
                <p class="text-muted">Your tour booking is recorded. A confirmation reference is below — please save it.</p>
                <p class="fs-4 fw-bold text-svtp">{{ booking.booking_reference_id }}</p>
                <p>
                    <span class="badge bg-warning text-dark me-2">{{ booking.booking_status }}</span>
                    <span class="badge bg-secondary">{{ booking.payment_status }}</span>
                </p>
            </div>

            <div class="glass-card p-4 mb-4">
                <div class="row g-3 small">
                    <div class="col-md-6"><span class="text-muted d-block">Tour</span><strong>{{ booking.package?.title ?? '—' }}</strong></div>
                    <div class="col-md-6"><span class="text-muted d-block">Travel date</span><strong>{{ booking.travel_date ?? '—' }}</strong></div>
                    <div class="col-md-6"><span class="text-muted d-block">Travellers</span><strong>{{ booking.total_adults }} adult(s)<span v-if="booking.total_children">, {{ booking.total_children }} child(ren)</span></strong></div>
                    <div class="col-md-6"><span class="text-muted d-block">Contact</span><strong>{{ booking.customer_phone }}</strong><span v-if="booking.customer_email" class="d-block text-muted">{{ booking.customer_email }}</span></div>
                </div>
                <hr />
                <div class="small">
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Subtotal</span><span>{{ money(booking.subtotal) }}</span></div>
                    <div v-if="booking.addons?.length" class="mt-1">
                        <div v-for="(line, i) in booking.addons" :key="i" class="d-flex justify-content-between py-1 text-muted"><span>{{ line.name }} × {{ line.quantity }}</span><span>{{ money(line.total_amount) }}</span></div>
                    </div>
                    <div v-if="Number(booking.discount_amount)" class="d-flex justify-content-between py-1 text-success"><span>Discount{{ booking.coupon_code ? ` (${booking.coupon_code})` : '' }}</span><span>−{{ money(booking.discount_amount) }}</span></div>
                    <div v-if="Number(booking.tax_amount)" class="d-flex justify-content-between py-1"><span class="text-muted">Tax</span><span>{{ money(booking.tax_amount) }}</span></div>
                    <div class="d-flex justify-content-between py-1 fs-5"><strong>Total</strong><strong class="text-svtp">{{ money(booking.total_amount) }}</strong></div>
                </div>
            </div>

            <div class="text-center">
                <form v-if="canPay" @submit.prevent="pay">
                    <button class="btn btn-svtp btn-lg" :disabled="payForm.processing">{{ payForm.processing ? 'Loading payment…' : 'Continue to Payment' }}</button>
                    <p class="small text-muted mt-2 mb-0">No amount has been charged yet.</p>
                </form>
                <p v-else class="text-muted">Our travel team will contact you shortly about the next steps.</p>
                <div class="mt-3 d-flex gap-2 justify-content-center flex-wrap">
                    <a v-if="booking.package?.slug" :href="appUrl(`/packages/${booking.package.slug}`)" class="btn btn-outline-svtp">Back to Tour</a>
                    <a :href="appUrl('/packages')" class="btn btn-outline-svtp">Browse All Tours</a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
