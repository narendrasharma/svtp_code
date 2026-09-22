<script setup>
import axios from 'axios';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import ReviewBookingAction from '../../../Components/Hotel/ReviewBookingAction.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ booking: Object, cancellationQuote: Object, reviewEligibility: Object, reviewsEnabled: Boolean });
const cancelForm = useForm({ idempotency_key: `hotel-cancel-${props.booking.id}`, reason_code: 'customer_request' });
const rescheduleForm = useForm({ check_in: props.booking.check_in, check_out: props.booking.check_out, quote_fingerprint: '', idempotency_key: `hotel-reschedule-${props.booking.id}` });
const rescheduleQuote = ref(null);
const quoteError = ref('');

async function quoteReschedule() {
    quoteError.value = '';
    try { rescheduleQuote.value = (await axios.post(appUrl(`/account/hotel-bookings/${props.booking.id}/reschedule-quote`), { check_in: rescheduleForm.check_in, check_out: rescheduleForm.check_out })).data; } catch (error) { quoteError.value = error.response?.data?.message || 'Unable to quote these dates.'; }
}
function confirmReschedule() { rescheduleForm.quote_fingerprint = rescheduleQuote.value.quote_fingerprint; rescheduleForm.post(appUrl(`/account/hotel-bookings/${props.booking.id}/reschedule`)); }
function cancel() { if (window.confirm('Cancel this hotel booking?')) cancelForm.post(appUrl(`/account/hotel-bookings/${props.booking.id}/cancel`)); }
</script>
<template>
<AppLayout>
<div class="container py-5" style="max-width:900px">
<Link :href="appUrl('/account/hotel-bookings')">← My Hotel Bookings</Link>
<h1 class="mt-3">{{ booking.booking_number }}</h1>
<p class="text-muted">{{ booking.property_name }} · {{ booking.check_in }} → {{ booking.check_out }}</p>
<div class="card p-4">
<p><strong>Booking status:</strong> {{ booking.status }} · <strong>Payment status:</strong> {{ booking.payment_status }}</p>
<p><strong>Total:</strong> {{ booking.currency }} {{ booking.total }}</p>
<div v-for="item in booking.items" :key="item.room_type + item.rate_plan"><strong>{{ item.quantity }} × {{ item.room_type }}</strong><div class="small text-muted">{{ item.rate_plan }} · {{ item.meal_plan }} · {{ item.cancellation_mode }}</div></div>
<div class="mt-3"><ReviewBookingAction :booking-id="booking.id" :eligibility="reviewEligibility" :enabled="reviewsEnabled" /></div>
</div>
<div v-if="cancellationQuote.eligible" class="card p-4 mt-3"><h4>Cancellation</h4><p>Cancellation fee: {{ booking.currency }} {{ cancellationQuote.cancellation_fee }}</p><p>Refund eligible: {{ booking.currency }} {{ cancellationQuote.remaining_refundable }}</p><p class="small text-muted">{{ cancellationQuote.policy_summary.summary || cancellationQuote.policy_summary.mode }}</p><button class="btn btn-outline-danger" :disabled="cancelForm.processing" @click="cancel">Cancel booking</button></div>
<div v-if="['pending','confirmed'].includes(booking.status)" class="card p-4 mt-3"><h4>Reschedule booking</h4><div class="row g-2"><div class="col-sm-6"><label class="form-label">New check-in</label><input v-model="rescheduleForm.check_in" type="date" class="form-control"></div><div class="col-sm-6"><label class="form-label">New check-out</label><input v-model="rescheduleForm.check_out" type="date" class="form-control"></div></div><button class="btn btn-outline-primary mt-3" @click="quoteReschedule">Get authoritative quote</button><p v-if="quoteError" class="text-danger mt-2">{{ quoteError }}</p><div v-if="rescheduleQuote" class="border rounded p-3 mt-3"><p>Availability: {{ rescheduleQuote.availability ? 'Available' : 'Unavailable' }}</p><p>Old total: {{ booking.currency }} {{ rescheduleQuote.old_total }} · New total: {{ booking.currency }} {{ rescheduleQuote.new_total }}</p><p v-if="rescheduleQuote.difference > 0">Additional amount due: {{ booking.currency }} {{ rescheduleQuote.additional_payment_due }}</p><p v-else-if="rescheduleQuote.difference < 0">Potential refund: {{ booking.currency }} {{ rescheduleQuote.refundable_difference }}</p><p v-else>No price difference.</p><button v-if="rescheduleQuote.eligible" class="btn btn-primary" :disabled="rescheduleForm.processing" @click="confirmReschedule">Confirm reschedule</button><span v-else class="text-danger">These dates are unavailable.</span></div></div>
<div class="card p-4 mt-3"><h4>Booking timeline</h4><div v-for="entry in booking.timeline" :key="entry.event + entry.created_at" class="small border-bottom py-2">{{ entry.description || entry.event }} <span class="text-muted">{{ entry.created_at }}</span></div></div>
</div>
</AppLayout>
</template>
