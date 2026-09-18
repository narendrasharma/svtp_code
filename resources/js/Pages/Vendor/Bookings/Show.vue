<script setup>
import { Link } from '@inertiajs/vue3';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    booking: { type: Object, required: true },
});

const endpoint = appUrl('/vendor/bookings');

function statusBadge(status) {
    return {
        pending: 'bg-warning text-dark',
        confirmed: 'bg-info text-dark',
        completed: 'bg-success',
        cancelled: 'bg-secondary',
    }[status] ?? 'bg-secondary';
}
function paymentBadge(status) {
    return {
        unpaid: 'bg-warning text-dark',
        partially_paid: 'bg-info text-dark',
        paid: 'bg-success',
        refunded: 'bg-secondary',
    }[status] ?? 'bg-secondary';
}
function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN') : '—';
}
function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
function money(value) {
    return `₹${Number(value ?? 0).toFixed(2)}`;
}
function cancellationBadge(status) {
    return {
        pending: 'bg-warning text-dark',
        approved: 'bg-success',
        rejected: 'bg-secondary',
    }[status] ?? 'bg-secondary';
}
</script>

<template>
    <VendorLayout>
        <div class="mb-4">
            <Link :href="endpoint" class="small text-muted text-decoration-none no-print"><i class="bi bi-arrow-left me-1"></i>All Bookings</Link>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                <h2 class="mb-0">{{ booking.booking_reference_id }}</h2>
                <span class="badge" :class="statusBadge(booking.booking_status)">{{ booking.booking_status }}</span>
                <span class="badge" :class="paymentBadge(booking.payment_status)">{{ booking.payment_status }}</span>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto no-print" @click="window.print()"><i class="bi bi-printer me-1"></i>Print sheet</button>
            </div>
            <p class="text-muted mb-0 mt-1">Booked {{ formatDateTime(booking.created_at) }} via {{ booking.source }}</p>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Tour Details</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Tour package</dt><dd class="col-sm-8">{{ booking.tour?.title ?? '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Travel date</dt><dd class="col-sm-8">{{ formatDate(booking.travel_date) }}</dd>
                        <dt class="col-sm-4 text-muted">Guests</dt><dd class="col-sm-8">{{ booking.total_adults }} adult(s)<span v-if="booking.total_children">, {{ booking.total_children }} child(ren)</span></dd>
                        <dt class="col-sm-4 text-muted">Pickup address</dt><dd class="col-sm-8">{{ booking.pickup_address || '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Special requests</dt><dd class="col-sm-8">{{ booking.special_requests || '—' }}</dd>
                    </dl>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Customer</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Name</dt><dd class="col-sm-8">{{ booking.customer?.name || 'Guest' }}</dd>
                        <dt class="col-sm-4 text-muted">Phone</dt><dd class="col-sm-8">{{ booking.customer?.phone || '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Email</dt><dd class="col-sm-8">{{ booking.customer?.email || '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Country</dt><dd class="col-sm-8">{{ booking.country || '—' }}</dd>
                    </dl>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Selected Extras</h5>
                    <div v-if="booking.addons?.length" class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>Extra</th><th class="text-end">Qty</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                <tr v-for="(line, i) in booking.addons" :key="i"><td>{{ line.name }}</td><td class="text-end">{{ line.quantity }}</td><td class="text-end">{{ money(line.total_amount) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-muted small mb-0">No extras selected.</p>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Pricing &amp; Earnings</h5>
                    <p class="text-muted small">Historical snapshot — rate changes never alter this record.</p>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Base price (per adult)</dt><dd class="col-sm-8">{{ money(booking.pricing?.base_price) }}</dd>
                        <dt v-if="Number(booking.pricing?.addons_total)" class="col-sm-4 text-muted">Extras total</dt><dd v-if="Number(booking.pricing?.addons_total)" class="col-sm-8">{{ money(booking.pricing?.addons_total) }}</dd>
                        <dt class="col-sm-4 text-muted">Subtotal</dt><dd class="col-sm-8">{{ money(booking.pricing?.subtotal) }}</dd>
                        <dt v-if="Number(booking.pricing?.discount_amount)" class="col-sm-4 text-muted">Discount{{ booking.pricing?.coupon_code ? ` (${booking.pricing.coupon_code})` : '' }}</dt><dd v-if="Number(booking.pricing?.discount_amount)" class="col-sm-8">−{{ money(booking.pricing?.discount_amount) }}</dd>
                        <dt v-if="Number(booking.pricing?.tax_amount)" class="col-sm-4 text-muted">Tax</dt><dd v-if="Number(booking.pricing?.tax_amount)" class="col-sm-8">{{ money(booking.pricing?.tax_amount) }}</dd>
                        <dt class="col-sm-4 text-muted">Booking gross</dt><dd class="col-sm-8 fw-bold">{{ money(booking.pricing?.gross_amount ?? booking.pricing?.total_amount) }}</dd>
                        <dt class="col-sm-4 text-muted">Platform commission</dt><dd class="col-sm-8">{{ booking.pricing?.platform_commission_percentage ?? '—' }}% · {{ money(booking.pricing?.platform_commission_amount) }}</dd>
                        <dt class="col-sm-4 text-muted">My earning</dt><dd class="col-sm-8 fw-bold text-success">{{ money(booking.pricing?.vendor_earning_amount) }}</dd>
                        <dt class="col-sm-4 text-muted">Financial state</dt><dd class="col-sm-8">{{ { credited: 'Credited to earnings', partially_reversed: 'Partially reversed (refunded)', reversed: 'Reversed (refunded)', pending_payment: 'Pending payment' }[booking.ledger_state] ?? booking.ledger_state ?? '—' }}</dd>
                        <template v-if="booking.refund_impact && Number(booking.refund_impact.refunded_total)">
                            <dt class="col-sm-4 text-muted">Refunded to customer</dt><dd class="col-sm-8">{{ money(booking.refund_impact.refunded_total) }}</dd>
                            <dt class="col-sm-4 text-muted">Earning reversed</dt><dd class="col-sm-8">{{ money(booking.refund_impact.earning_reversed) }}</dd>
                            <dt class="col-sm-4 text-muted">Earning remaining</dt><dd class="col-sm-8">{{ money(booking.refund_impact.earning_remaining) }}</dd>
                        </template>
                        <dt class="col-sm-4 text-muted">Gateway</dt><dd class="col-sm-8">{{ booking.payment_gateway || '—' }}</dd>
                    </dl>
                </section>

                <section v-if="booking.cancellation" class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Cancellation</h5>
                    <div class="d-flex flex-wrap align-items-center gap-2 small">
                        <span class="badge" :class="cancellationBadge(booking.cancellation.status)">{{ booking.cancellation.status }}</span>
                        <span class="text-muted">Requested {{ formatDateTime(booking.cancellation.requested_at) }}</span>
                    </div>
                    <p v-if="booking.cancellation.reason" class="small mt-2 mb-0"><span class="text-muted">Reason:</span> {{ booking.cancellation.reason }}</p>
                    <p class="text-muted small mb-0 mt-2">Cancellations and refunds are handled by the platform team.</p>
                </section>
            </div>

            <div class="col-lg-4">
                <aside class="card p-3 p-md-4">
                    <h5 class="mb-3">Good to know</h5>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Earnings count only paid, non-cancelled bookings.</li>
                        <li>Payment collection and refunds are handled by the platform.</li>
                        <li>Contact the customer only for trip fulfilment.</li>
                    </ul>
                </aside>
            </div>
        </div>
    </VendorLayout>
</template>

<style scoped>
.card { border-radius: 12px; }
@media print {
    .no-print { display: none !important; }
}
</style>
