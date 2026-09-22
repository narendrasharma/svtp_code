<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import ReviewSection from '../../Components/Hotel/ReviewSection.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({ property: Object, seo: Object, reviewSummary: Object, reviews: Object, reviewCategories: Object, reviewSort: String });

const checkIn = ref('');
const checkOut = ref('');
const rooms = ref(1);
const adults = ref(2);
const children = ref(0);
const checking = ref(false);
const checkError = ref('');
const checkResult = ref(null);
const ratesResult = ref(null);
const selectedPlan = ref(null);
const bookingError = ref('');
const guestName = ref('');
const guestEmail = ref('');
const guestPhone = ref('');
const specialRequests = ref('');
const termsAccepted = ref(false);

async function checkAvailability() {
    checkError.value = '';
    checkResult.value = null;
    ratesResult.value = null;
    if (!checkIn.value || !checkOut.value) { checkError.value = 'Choose check-in and check-out dates.'; return; }
    checking.value = true;
    try {
        const params = { check_in: checkIn.value, check_out: checkOut.value, rooms: rooms.value, adults: adults.value, children: children.value };
        const [{ data: avail }, { data: rates }] = await Promise.all([
            axios.get(appUrl(`/hotels/${props.property.slug}/availability`), { params: { check_in: checkIn.value, check_out: checkOut.value, rooms: rooms.value } }),
            axios.get(appUrl(`/hotels/${props.property.slug}/rates`), { params }),
        ]);
        checkResult.value = avail;
        ratesResult.value = rates;
    } catch (e) {
        checkError.value = e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {}).flat().join(' ') ?? 'Could not check availability.';
    } finally {
        checking.value = false;
    }
}

function roomStatus(slug) {
    return checkResult.value?.room_types?.find(r => r.slug === slug) ?? null;
}

function roomPlans(slug) {
    return ratesResult.value?.room_types?.find(r => r.slug === slug)?.plans?.filter(p => p.available) ?? [];
}

function selectPlan(plan) {
    selectedPlan.value = plan;
    bookingError.value = '';
}

function submitBooking() {
    if (!selectedPlan.value || !guestName.value || !guestEmail.value || !guestPhone.value) {
        bookingError.value = 'Enter your name, email and phone before confirming.';
        return;
    }
    router.post(appUrl(`/hotels/${props.property.slug}/book`), {
        room_type_id: selectedPlan.value.room_type_id,
        rate_plan_id: selectedPlan.value.rate_plan_id,
        check_in: checkIn.value,
        check_out: checkOut.value,
        rooms: rooms.value,
        adults: adults.value,
        children: children.value,
        guest_name: guestName.value,
        guest_email: guestEmail.value,
        guest_phone: guestPhone.value,
        special_requests: specialRequests.value,
        terms_accepted: termsAccepted.value,
        quote_fingerprint: selectedPlan.value.quote_fingerprint,
        idempotency_key: crypto.randomUUID(),
    }, { onError: errors => { bookingError.value = Object.values(errors).flat().join(' ') || 'Could not create the reservation.'; } });
}

function stars(n) {
    if (n === null || n === undefined) return 'Unrated';
    return '★'.repeat(Number(n)) + '☆'.repeat(5 - Number(n));
}

const groupedAmenities = (list) => {
    const groups = {};
    for (const a of (list ?? [])) (groups[a.category ?? 'General'] ??= []).push(a.name);
    return groups;
};
</script>
<template><AppLayout>
<SeoHead :title="seo.title" :description="seo.description" :image="seo.image" :canonical="seo.canonical" :structured-data="seo.structuredData" />
<div class="container py-4">
<Link :href="appUrl('/hotels')">← All stays</Link>
<h1 class="mt-2 mb-1">{{ property.name }}</h1>
<p class="text-muted">{{ property.type }} · <span class="text-warning">{{ stars(property.star_rating) }}</span> · {{ property.city }}<span v-if="property.state">, {{ property.state }}</span> {{ property.country_code }}</p>
<a v-if="reviewSummary?.reviews_count" href="#reviews" class="d-inline-flex gap-2 align-items-center mb-3"><strong>{{ Number(reviewSummary.rating_average).toFixed(1) }} ★</strong><span>{{ reviewSummary.reviews_count }} guest reviews</span></a>

<div v-if="property.gallery?.length" class="row g-2 mb-3">
<div v-for="(img, i) in property.gallery" :key="i" class="col-6 col-md-4"><img :src="img.url" :alt="img.alt ?? property.name" class="img-fluid rounded" loading="lazy" /></div>
</div>

<div class="row g-3">
<div class="col-lg-8">
<div v-if="property.short_description" class="lead">{{ property.short_description }}</div>
<div v-if="property.description" v-html="property.description" class="mt-2"></div>
<div class="card p-3 mt-3"><h4>Amenities</h4>
<div v-for="(names, group) in groupedAmenities(property.amenities)" :key="group" class="mb-2"><strong>{{ group }}</strong><br />{{ names.join(' · ') }}</div>
<p v-if="!property.amenities?.length" class="text-muted mb-0">No amenities listed yet.</p>
</div>
<div v-for="group in property.customFields ?? []" :key="group.group" class="card p-3 mt-3"><h4>{{ group.group }}</h4>
<dl class="row mb-0">
<template v-for="field in group.fields" :key="field.label || field.value">
<dt class="col-sm-4">{{ field.label || '—' }}</dt>
<dd class="col-sm-8"><a v-if="field.type === 'url'" :href="field.value" target="_blank" rel="noopener noreferrer">{{ field.value }}</a><span v-else>{{ field.value }}</span></dd>
</template>
</dl>
</div>
<div class="card p-3 mt-3"><h4>Rooms</h4>
<div v-if="property.rooms?.length" class="row g-3">
<div v-for="room in property.rooms" :key="room.slug" class="col-md-6">
<div class="border rounded p-3 h-100">
<img v-if="room.image" :src="room.image" :alt="room.name" class="img-fluid rounded mb-2" loading="lazy" />
<h5 class="mb-1">{{ room.name }}</h5>
<p class="small text-muted mb-1">Sleeps {{ room.max_adults }}<span v-if="room.max_children"> + {{ room.max_children }} children</span> (max {{ room.max_occupancy }})<span v-if="room.size"> · {{ room.size }}</span></p>
<p v-if="room.beds" class="small mb-1">Beds: {{ room.beds }}</p>
<p v-if="room.amenities?.length" class="small mb-1">{{ room.amenities.map(a => a.name).join(' · ') }}</p>
<p v-if="room.short_description" class="small mb-2">{{ room.short_description }}</p>
<p v-if="roomStatus(room.slug)" class="small mb-2" :class="roomStatus(room.slug).available ? 'text-success' : 'text-danger'"><strong>{{ roomStatus(room.slug).available ? `Available (${roomStatus(room.slug).available_rooms} left)` : 'Not available for selected dates' }}</strong></p>
<div v-for="plan in roomPlans(room.slug)" :key="plan.code" class="small border-top pt-1 mt-1">
<strong>{{ plan.name }}</strong> <span class="text-muted">({{ plan.meal_plan }})</span><br />
<span class="fs-6">{{ plan.currency }} {{ plan.total }}</span> <span class="text-muted">total · incl. taxes/fees</span>
<button type="button" class="btn btn-sm btn-svtp ms-2" @click="selectPlan(plan)">Reserve</button>
</div>
<div v-if="selectedPlan" class="card p-3 mb-3 border-primary"><h4>Confirm reservation</h4>
<p class="small mb-2">{{ selectedPlan.name }} · {{ selectedPlan.currency }} {{ selectedPlan.total }} · payment remains unpaid until collected.</p>
<input v-model="guestName" class="form-control mb-2" maxlength="150" placeholder="Full name" />
<input v-model="guestEmail" class="form-control mb-2" type="email" maxlength="150" placeholder="Email" />
<input v-model="guestPhone" class="form-control mb-2" maxlength="40" placeholder="Phone" />
<textarea v-model="specialRequests" class="form-control mb-2" maxlength="2000" rows="2" placeholder="Special requests (optional)"></textarea>
<label class="small mb-2"><input v-model="termsAccepted" type="checkbox" class="me-1" /> I accept the booking terms.</label>
<p v-if="bookingError" class="text-danger small mb-2">{{ bookingError }}</p>
<button type="button" class="btn btn-svtp w-100" @click="submitBooking">Confirm reservation</button>
</div>
<button type="button" class="btn btn-sm btn-outline-primary" @click="room._open = !room._open">{{ room._open ? 'Hide details' : 'View Room Details' }}</button>
<div v-if="room._open" class="mt-2">
<div v-if="room.description" v-html="room.description" class="small"></div>
<div v-for="group in room.customFields ?? []" :key="group.group" class="mt-2">
<h6 class="text-muted text-uppercase small mb-1">{{ group.group }}</h6>
<p v-for="field in group.fields" :key="field.label || field.value" class="small mb-1"><template v-if="field.label"><strong>{{ field.label }}:</strong> </template><a v-if="field.type === 'url'" :href="field.value" target="_blank" rel="noopener noreferrer">{{ field.value }}</a><span v-else>{{ field.value }}</span></p>
</div>
<div v-if="room.gallery?.length" class="row g-1 mt-2">
<div v-for="(img, j) in room.gallery" :key="j" class="col-4"><img :src="img.url" :alt="img.alt ?? room.name" class="img-fluid rounded" loading="lazy" /></div>
</div>
</div>
</div></div>
</div>
<p v-else class="text-muted mb-0">Rooms will be listed here soon — contact the property for details.</p>
</div>
<div class="card p-3 mt-3"><h4>Good to know</h4><p class="mb-1">Check-in: <strong>{{ property.check_in_time ?? '—' }}</strong> · Check-out: <strong>{{ property.check_out_time ?? '—' }}</strong></p>
<p v-if="property.children_policy" class="mb-1">Children: {{ property.children_policy }}</p>
<p v-if="property.pet_policy" class="mb-1">Pets: {{ property.pet_policy }}</p>
<p v-if="property.smoking_policy" class="mb-1">Smoking: {{ property.smoking_policy }}</p>
<p v-if="property.check_in_instructions" class="mb-1">{{ property.check_in_instructions }}</p>
<p v-if="property.house_rules" class="mb-0">{{ property.house_rules }}</p>
</div>
<ReviewSection v-if="reviewSummary && reviews" :summary="reviewSummary" :reviews="reviews" :categories="reviewCategories" :sort="reviewSort" :property-slug="property.slug" />
</div>
<div class="col-lg-4">
<div class="card p-3 mb-3"><h4>Check availability</h4>
<div class="row g-2">
<div class="col-6"><label class="form-label small" for="av-in">Check-in</label><input id="av-in" v-model="checkIn" type="date" class="form-control" /></div>
<div class="col-6"><label class="form-label small" for="av-out">Check-out</label><input id="av-out" v-model="checkOut" type="date" class="form-control" /></div>
<div class="col-6"><label class="form-label small" for="av-rooms">Rooms</label><input id="av-rooms" v-model="rooms" type="number" min="1" max="100" class="form-control" /></div>
<div class="col-6"><label class="form-label small" for="av-adults">Adults</label><input id="av-adults" v-model="adults" type="number" min="1" max="200" class="form-control" /></div>
<div class="col-6"><label class="form-label small" for="av-children">Children</label><input id="av-children" v-model="children" type="number" min="0" max="200" class="form-control" /></div>
<div class="col-6 d-flex align-items-end"><button class="btn btn-svtp w-100" :disabled="checking" @click="checkAvailability">{{ checking ? 'Checking…' : 'Check' }}</button></div>
</div>
<p v-if="checkError" class="text-danger small mt-2 mb-0">{{ checkError }}</p>
<p v-if="checkResult" class="mt-2 mb-0" :class="checkResult.available ? 'text-success' : 'text-danger'"><strong>{{ checkResult.available ? 'Available for your dates' : 'No rooms available for selected dates' }}</strong></p>
</div>
<div class="card p-3 mb-3"><h4>Location</h4>
<p class="mb-1">{{ property.address_line_1 }}<span v-if="property.address_line_2">, {{ property.address_line_2 }}</span></p>
<p class="mb-0">{{ property.city }}<span v-if="property.state">, {{ property.state }}</span> {{ property.postal_code }} {{ property.country_code }}</p>
</div>
<div v-if="property.phone || property.email || property.website" class="card p-3"><h4>Contact</h4>
<p v-if="property.phone" class="mb-1">Phone: {{ property.phone }}</p>
<p v-if="property.email" class="mb-1">Email: {{ property.email }}</p>
<p v-if="property.website" class="mb-0">Web: <a :href="property.website" target="_blank" rel="noopener">{{ property.website }}</a></p>
</div>
</div>
</div>
</div>
</AppLayout></template>
