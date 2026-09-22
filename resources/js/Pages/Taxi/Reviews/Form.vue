<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AccountNav from '../../../Components/AccountNav.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    booking: { type: Object, required: true },
    eligibility: { type: Object, required: true },
    existing: { type: Object, default: null },
    allowText: { type: Boolean, default: true },
});

const form = useForm({
    overall_rating: 5,
    driver_rating: null,
    vehicle_rating: null,
    service_rating: null,
    punctuality_rating: null,
    cleanliness_rating: null,
    comment: '',
});

const dimensions = [
    ['driver_rating', 'Driver'],
    ['vehicle_rating', 'Vehicle'],
    ['service_rating', 'Service'],
    ['punctuality_rating', 'Punctuality'],
    ['cleanliness_rating', 'Cleanliness'],
];

function submit() {
    form.post(appUrl(`/account/taxi/reviews/${props.booking.id}`));
}

function stars(n) {
    if (n === null || n === undefined) return '—';
    return '★'.repeat(Number(n)) + '☆'.repeat(5 - Number(n));
}
</script>
<template>
<AppLayout><div class="container py-4"><AccountNav active="taxi" />
<Link :href="appUrl('/account/taxi/bookings')">← My taxi bookings</Link>
<h2 class="my-3">Rate your trip · {{ booking.reference }}</h2>
<p class="text-muted">{{ booking.trip_type }} · Pickup {{ booking.pickup_at }} · Status: {{ booking.status }}</p>

<div v-if="existing" class="card p-3 mb-3">
<h4>Your review</h4>
<p>Overall: <strong :title="`${existing.overall_rating}/5`">{{ stars(existing.overall_rating) }}</strong> · Status: <strong>{{ existing.status }}</strong></p>
<p v-if="existing.comment" class="mb-1">{{ existing.comment }}</p>
<p v-if="existing.vendor_reply" class="mt-2 mb-0"><strong>Vendor reply:</strong> {{ existing.vendor_reply }}</p>
<p v-if="existing.status === 'pending'" class="small text-muted mb-0">Your review is awaiting moderation and will appear once approved.</p>
</div>

<div v-else-if="!eligibility.eligible" class="alert alert-info">{{ eligibility.reason }}</div>

<form v-else class="card p-3" @submit.prevent="submit">
<h4>How was your trip?</h4>
<div v-for="(error, key) in form.errors" :key="key" class="text-danger">{{ error }}</div>
<div class="row g-3">
<div class="col-md-6"><label class="form-label" for="overall">Overall rating (required)</label>
<select id="overall" v-model="form.overall_rating" required class="form-select">
<option v-for="n in [5,4,3,2,1]" :key="n" :value="n">{{ n }} — {{ ['Poor','Fair','Good','Very good','Excellent'][n-1] }}</option>
</select></div>
<div v-for="[key, label] in dimensions" :key="key" class="col-md-6"><label class="form-label" :for="key">{{ label }} (optional)</label>
<select :id="key" v-model="form[key]" class="form-select"><option :value="null">No rating</option><option v-for="n in [5,4,3,2,1]" :key="n" :value="n">{{ n }}</option></select></div>
</div>
<div v-if="allowText" class="mt-3"><label class="form-label" for="comment">Comment (optional, plain text, max 1000)</label>
<textarea id="comment" v-model="form.comment" maxlength="1000" rows="4" class="form-control" /></div>
<p v-else class="mt-3 small text-muted">Text reviews are disabled — ratings only.</p>
<button class="btn btn-svtp mt-3" :disabled="form.processing">Submit review</button>
<p class="small text-muted mt-2 mb-0">One review per trip. Reviews may be moderated before they appear.</p>
</form>
</div></AppLayout>
</template>
