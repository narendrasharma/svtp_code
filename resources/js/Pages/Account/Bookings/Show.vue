<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';
import AccountNav from '../../../Components/AccountNav.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    booking: Object,
    pendingCancellation: { type: Object, default: null },
    refunds: { type: Array, default: () => [] },
    paymentSummary: { type: Object, default: () => ({ total: 0, paid: 0, refunded: 0, due: 0 }) },
    reviewEligibility: { type: Object, default: () => ({ can_review: false }) },
});

const cancelForm = useForm({ reason: '' });
const reviewForm = useForm({ rating: 5, comment: '' });
const canRequest = ['pending', 'confirmed'].includes(props.booking.booking_status) && !props.pendingCancellation;

function requestCancellation() {
    cancelForm.post(appUrl(`/account/bookings/${props.booking.id}/cancellation-requests`), { preserveScroll: true });
}
function submitReview() {
    reviewForm.post(appUrl(`/bookings/${props.booking.id}/review`), { preserveScroll: true });
}
function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN') : '—';
}
function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
function money(value) {
    return `₹${Number(value ?? 0).toLocaleString('en-IN')}`;
}
</script>

<template>
    <AppLayout>
        <SeoHead :title="`Booking ${booking.booking_reference_id}`" noindex />
        <div class="container py-5" style="max-width: 960px;">
            <Link :href="appUrl('/account/bookings')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>My Bookings</Link>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-4">
                <h1 class="section-title mb-0">{{ booking.booking_reference_id }}</h1>
                <span class="badge bg-secondary">{{ booking.booking_status }}</span>
                <span class="badge bg-secondary">{{ booking.payment_status }}</span>
            </div>
            <AccountNav active="bookings" />

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="glass-card p-3 p-md-4 mb-3">
                        <h5 class="text-svtp mb-3">Trip Summary</h5>
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-muted">Tour package</dt><dd class="col-sm-8"><a v-if="booking.package" :href="appUrl(`/packages/${booking.package.slug}`)">{{ booking.package.title }}</a><span v-else>—</span></dd>
                            <dt class="col-sm-4 text-muted">Travel date</dt><dd class="col-sm-8">{{ formatDate(booking.travel_date) }}</dd>
                            <dt class="col-sm-4 text-muted">Travellers</dt><dd class="col-sm-8">{{ booking.total_adults }} adult(s)<span v-if="booking.total_children">, {{ booking.total_children }} child(ren)</span></dd>
                            <dt v-if="booking.pickup_address" class="col-sm-4 text-muted">Pickup</dt><dd v-if="booking.pickup_address" class="col-sm-8">{{ booking.pickup_address }}</dd>
                        </dl>
                    </div>

                    <div class="glass-card p-3 p-md-4 mb-3">
                        <h5 class="text-svtp mb-3">Price Details</h5>
                        <div class="small">
                            <div class="d-flex justify-content-between py-1"><span class="text-muted">Subtotal</span><span>{{ money(booking.subtotal) }}</span></div>
                            <div v-if="Number(booking.discount_amount)" class="d-flex justify-content-between py-1 text-success"><span>Discount</span><span>−{{ money(booking.discount_amount) }}</span></div>
                            <div v-if="Number(booking.tax_amount)" class="d-flex justify-content-between py-1"><span class="text-muted">Tax</span><span>{{ money(booking.tax_amount) }}</span></div>
                            <div class="d-flex justify-content-between py-1 fs-5"><strong>Total</strong><strong class="text-svtp">{{ money(booking.total_amount) }}</strong></div>
                        </div>
                    </div>

                    <div class="glass-card p-3 p-md-4 mb-3">
                        <h5 class="text-svtp mb-3">Payments</h5>
                        <div class="small">
                            <div class="d-flex justify-content-between py-1"><span class="text-muted">Paid so far</span><span>{{ money(paymentSummary.paid) }}</span></div>
                            <div class="d-flex justify-content-between py-1 fs-5"><strong>Outstanding due</strong><strong class="text-svtp">{{ money(paymentSummary.due) }}</strong></div>
                        </div>
                        <ul v-if="booking.payments?.length" class="list-unstyled mb-0 small mt-2">
                            <li v-for="payment in booking.payments" :key="payment.id" class="border-bottom py-2">
                                <div class="d-flex justify-content-between"><span>{{ payment.reference }} · {{ payment.payment_method }}</span><strong>{{ money(payment.amount) }}</strong></div>
                                <div class="text-muted">{{ formatDate(payment.paid_at) }}</div>
                            </li>
                        </ul>
                    </div>

                    <div v-if="refunds?.length" class="glass-card p-3 p-md-4 mb-3">
                        <h5 class="text-svtp mb-3">Refunds</h5>
                        <ul class="list-unstyled mb-0 small">
                            <li v-for="refund in refunds" :key="refund.id" class="border-bottom py-2">
                                <div class="d-flex justify-content-between"><span class="badge bg-info text-dark">{{ refund.status }}</span><strong>{{ money(refund.amount) }}</strong></div>
                                <div class="text-muted">{{ refund.reason }} · {{ formatDate(refund.processed_at) }}</div>
                            </li>
                        </ul>
                    </div>

                    <div class="glass-card p-3 p-md-4">
                        <h5 class="text-svtp mb-3">Booking Timeline</h5>
                        <ul v-if="booking.status_histories?.length" class="list-unstyled mb-0 small">
                            <li v-for="entry in booking.status_histories" :key="entry.id" class="border-bottom py-2">
                                <div>
                                    <template v-if="entry.payment_from && entry.payment_from !== entry.payment_to">
                                        Payment <strong>{{ entry.payment_from }} → {{ entry.payment_to }}</strong>
                                    </template>
                                    <template v-else-if="entry.from_status">
                                        Status <strong>{{ entry.from_status }} → {{ entry.to_status }}</strong>
                                    </template>
                                    <template v-else>
                                        Booking <strong>{{ entry.to_status }}</strong>
                                    </template>
                                </div>
                                <div class="text-muted">{{ formatDateTime(entry.created_at) }}</div>
                            </li>
                        </ul>
                        <p v-else class="text-muted small mb-0">No history yet.</p>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div v-if="reviewEligibility.can_review" class="glass-card p-3 p-md-4 mb-3">
                        <h5 class="text-svtp mb-1">Write a Review</h5>
                        <p class="text-muted small">You travelled on this tour — share your verified experience.</p>
                        <form @submit.prevent="submitReview">
                            <label for="review-rating" class="form-label small">Rating</label>
                            <select id="review-rating" v-model.number="reviewForm.rating" class="form-select form-select-sm mb-2">
                                <option :value="5">5 — Excellent</option>
                                <option :value="4">4 — Very good</option>
                                <option :value="3">3 — Average</option>
                                <option :value="2">2 — Poor</option>
                                <option :value="1">1 — Terrible</option>
                            </select>
                            <label for="review-comment" class="form-label small">Review (optional)</label>
                            <textarea id="review-comment" v-model="reviewForm.comment" class="form-control form-control-sm" rows="3" maxlength="1000" placeholder="How was your tour?"></textarea>
                            <small class="text-danger">{{ reviewForm.errors.rating || reviewForm.errors.comment || reviewForm.errors.booking }}</small>
                            <button class="btn btn-svtp btn-sm w-100 mt-2" :disabled="reviewForm.processing">Submit Review</button>
                        </form>
                    </div>
                    <div v-else-if="reviewEligibility.has_review" class="glass-card p-3 p-md-4 mb-3">
                        <h5 class="text-svtp mb-1">Your Review</h5>
                        <p class="text-muted small mb-0">Thanks — your verified review was submitted and appears after moderation.</p>
                    </div>
                    <div class="glass-card p-3 p-md-4 mb-3">
                        <h5 class="text-svtp mb-3">Actions</h5>
                        <a :href="appUrl(`/bookings/${booking.id}/invoice`)" class="btn btn-outline-svtp w-100 mb-2"><i class="bi bi-receipt me-1"></i>View Invoice</a>
                        <form v-if="canRequest" @submit.prevent="requestCancellation">
                            <label for="cancel-reason" class="form-label small mt-2">Need to cancel? Tell us why (optional)</label>
                            <textarea id="cancel-reason" v-model="cancelForm.reason" class="form-control form-control-sm" rows="2" maxlength="1000" placeholder="Reason for cancellation"></textarea>
                            <small class="text-danger">{{ cancelForm.errors.reason || cancelForm.errors.booking }}</small>
                            <button class="btn btn-outline-danger btn-sm w-100 mt-2" :disabled="cancelForm.processing">Request Cancellation</button>
                        </form>
                        <div v-else-if="pendingCancellation" class="alert alert-warning small mb-0">
                            Cancellation requested on {{ formatDate(pendingCancellation.created_at) }} — currently <strong>{{ pendingCancellation.status }}</strong>. Our team will update you shortly.
                        </div>
                        <p v-else class="text-muted small mb-0">This booking can no longer be cancelled online. Please contact us for assistance.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
