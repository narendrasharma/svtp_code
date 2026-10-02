<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import RatingDisplay from '../../Components/Public/UI/RatingDisplay.vue';
import MoneyDisplay from '../../Components/Public/UI/MoneyDisplay.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import ErrorState from '../../Components/Public/States/ErrorState.vue';
import PropertyReviews from '../../Components/Public/Hotels/PropertyReviews.vue';
import RoomTypeCard from '../../Components/Public/Hotels/RoomTypeCard.vue';
import DateRangeField from '../../Components/Public/Search/DateRangeField.vue';
import OccupancyField from '../../Components/Public/Search/OccupancyField.vue';
import { mediaUrl } from '../../Components/Public/homepage';

const props = defineProps({
    property: { type: Object, required: true },
    seo: { type: Object, default: () => ({}) },
    reviewSummary: { type: Object, default: null },
    reviews: { type: Object, default: null },
    reviewCategories: { type: Object, default: () => ({}) },
    reviewSort: { type: String, default: 'recent' },
    stay: { type: Object, default: () => ({}) },
    maxRoomsPerBooking: { type: Number, default: 10 },
});

const { locale, t } = useLocalization();
const stay = reactive({
    check_in: props.stay.check_in || '',
    check_out: props.stay.check_out || '',
    rooms: props.stay.rooms || 1,
    adults: props.stay.adults || 2,
    children: props.stay.children || 0,
});
const checking = ref(false);
const checkError = ref('');
const availability = ref(null);
const rates = ref(null);
const selectedRooms = ref({});
const selectionQuote = ref(null);
const selectionError = ref('');
let quoteRequest = 0;
let availabilityRequest = 0;
const galleryOpen = ref(false);
const galleryIndex = ref(0);

const hasDates = computed(() => Boolean(stay.check_in && stay.check_out));
const gallery = computed(() => props.property.gallery?.length ? props.property.gallery : [{ url: null, alt: props.property.name, primary: true }]);
const primaryImage = computed(() => gallery.value[0]);
const secondaryImages = computed(() => gallery.value.slice(1, 5));
const searchHref = computed(() => {
    const params = new URLSearchParams();
    Object.entries(stay).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) params.set(key, value);
    });
    return appUrl('/search/hotels?' + params.toString());
});
const locationText = computed(() => [props.property.city, props.property.destination, props.property.state].filter(Boolean).filter((value, index, list) => list.indexOf(value) === index).join(' · '));
const selectionItems = computed(() => Object.values(selectedRooms.value).filter((item) => item.quantity > 0));
const selectedRoomCount = computed(() => selectionItems.value.reduce((total, item) => total + item.quantity, 0));
const bookingHref = computed(() => {
    if (!selectionQuote.value) return '#rooms';
    const params = new URLSearchParams({
        check_in: stay.check_in,
        check_out: stay.check_out,
        adults: String(stay.adults),
        children: String(stay.children),
    });
    selectionItems.value.forEach((item, index) => {
        params.set(`items[${index}][room_type_id]`, String(item.room_type_id));
        params.set(`items[${index}][rate_plan_id]`, String(item.rate_plan_id));
        params.set(`items[${index}][quantity]`, String(item.quantity));
    });

    return appUrl('/hotels/' + props.property.slug + '/book?' + params.toString());
});

function formatDate(value) {
    if (!value) return t('common.select_dates', 'Select dates');
    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value + 'T12:00:00'));
    } catch {
        return value;
    }
}

function formatTime(value) {
    if (!value) return null;
    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { hour: 'numeric', minute: '2-digit' }).format(new Date('2020-01-01T' + value));
    } catch {
        return value;
    }
}

function groupedAmenities() {
    const groups = {};
    (props.property.amenities || []).forEach((amenity) => {
        const group = amenity.category || t('common.general', 'General');
        if (!groups[group]) groups[group] = [];
        groups[group].push(amenity);
    });
    return groups;
}

function roomAvailability(room) {
    return availability.value?.room_types?.find((item) => item.slug === room.slug) || null;
}

function roomRates(room) {
    return rates.value?.room_types?.find((item) => item.slug === room.slug)?.plans || [];
}

async function checkAvailability() {
    checkError.value = '';
    clearSelection();
    const request = ++availabilityRequest;

    if (!stay.check_in || !stay.check_out) {
        checkError.value = t('common.choose_dates_first', 'Choose check-in and check-out dates to check availability.');
        return;
    }

    checking.value = true;

    try {
        const base = {
            check_in: stay.check_in,
            check_out: stay.check_out,
            rooms: 1,
            adults: 1,
            children: 0,
        };
        const response = await Promise.all([
            axios.get(appUrl('/hotels/' + props.property.slug + '/availability'), { params: base }),
            axios.get(appUrl('/hotels/' + props.property.slug + '/rates'), { params: base }),
        ]);
        if (request === availabilityRequest) {
            availability.value = response[0].data;
            rates.value = response[1].data;
        }
    } catch (error) {
        if (request === availabilityRequest) {
            availability.value = null;
            rates.value = null;
            checkError.value = error.response?.data?.message || t('common.availability_error', 'Availability could not be checked. Please try again.');
        }
    } finally {
        if (request === availabilityRequest) checking.value = false;
    }
}

function clearSelection() {
    quoteRequest++;
    selectedRooms.value = {};
    selectionQuote.value = null;
    selectionError.value = '';
}

async function changeRoom(rate, room, quantity) {
    const updated = { ...selectedRooms.value };
    if (quantity > 0) updated[room.slug] = { room_type_id: rate.room_type_id, rate_plan_id: rate.rate_plan_id, room_name: room.name, rate_name: rate.name, quantity };
    else delete updated[room.slug];
    selectedRooms.value = updated;
    stay.rooms = selectedRoomCount.value || 1;
    await refreshSelection();
}

async function refreshSelection() {
    selectionQuote.value = null;
    selectionError.value = '';
    const request = ++quoteRequest;
    if (!selectionItems.value.length) return;
    try {
        const response = await axios.get(appUrl('/hotels/' + props.property.slug + '/booking-quote'), {
            params: { items: selectionItems.value.map(({ room_type_id, rate_plan_id, quantity }) => ({ room_type_id, rate_plan_id, quantity })), check_in: stay.check_in, check_out: stay.check_out, adults: Number(stay.adults), children: Number(stay.children) },
        });
        if (request === quoteRequest) selectionQuote.value = response.data;
    } catch (error) {
        if (request === quoteRequest) selectionError.value = Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || t('common.quote_error', 'The stay could not be priced right now.');
    }
}

watch(() => [stay.check_in, stay.check_out], () => {
    availabilityRequest++;
    availability.value = null;
    rates.value = null;
    clearSelection();
});
watch(() => [stay.adults, stay.children], () => {
    if (selectedRoomCount.value) refreshSelection();
});
watch(() => stay.rooms, () => {
    if (selectedRoomCount.value && Number(stay.rooms) !== selectedRoomCount.value) clearSelection();
});

function scrollToRooms() {
    document.getElementById('rooms')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function openGallery(index) {
    galleryIndex.value = index;
    galleryOpen.value = true;
}

function closeGallery() {
    galleryOpen.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape') closeGallery();
    if (!galleryOpen.value || gallery.value.length < 2) return;
    if (event.key === 'ArrowRight') galleryIndex.value = (galleryIndex.value + 1) % gallery.value.length;
    if (event.key === 'ArrowLeft') galleryIndex.value = (galleryIndex.value - 1 + gallery.value.length) % gallery.value.length;
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    if (hasDates.value) checkAvailability();
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <PublicLayout main-class="property-detail-page">
        <SeoHead :title="seo.title" :description="seo.description" :image="seo.image" :canonical="seo.canonical" :structured-data="seo.structuredData" />

        <main :class="{ 'has-room-selection': selectedRoomCount > 0 }">
            <div class="public-container property-detail-container">
                <nav class="property-breadcrumbs" aria-label="Breadcrumb">
                    <Link :href="searchHref"><i class="bi bi-arrow-left" data-dir-icon="arrow" aria-hidden="true"></i>{{ t('common.back_to_stays', 'Back to stays') }}</Link>
                </nav>

                <section class="property-identity">
                    <div class="property-identity__copy">
                        <div class="property-identity__badges">
                            <span v-if="property.type" class="public-badge public-badge--neutral">{{ property.type }}</span>
                            <span v-if="property.star_rating" class="property-classification"><i v-for="star in property.star_rating" :key="star" class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden">{{ property.star_rating }} {{ t('common.property_stars', 'property stars') }}</span></span>
                        </div>
                        <h1>{{ property.name }}</h1>
                        <p v-if="locationText" class="property-identity__location"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ locationText }}</p>
                        <div class="property-identity__rating">
                            <RatingDisplay :rating="reviewSummary?.rating_average" :review-count="reviewSummary?.reviews_count" :label="t('common.not_rated', 'Not rated')" />
                            <a v-if="reviewSummary?.reviews_count" href="#reviews">{{ reviewSummary.reviews_count }} {{ reviewSummary.reviews_count === 1 ? t('common.review', 'review') : t('common.reviews', 'reviews') }}</a>
                        </div>
                    </div>
                    <div class="property-identity__actions">
                        <a v-if="property.website" :href="property.website" target="_blank" rel="noopener noreferrer" class="public-button public-button--outline public-button--sm">{{ t('common.visit_website', 'Visit website') }}</a>
                        <button type="button" class="public-button public-button--primary public-button--sm" @click="scrollToRooms">{{ hasDates ? t('common.view_rates', 'View rates') : t('common.check_availability', 'Check availability') }}</button>
                    </div>
                </section>

                <section
                    class="property-gallery"
                    :class="{ 'property-gallery--single': !secondaryImages.length }"
                    aria-label="Property photos"
                >
                    <button type="button" class="property-gallery__primary" @click="openGallery(0)">
                        <ImageWithFallback :src="mediaUrl(primaryImage.url)" :alt="primaryImage.alt || property.name" aspect="editorial" kind="hotel" :label="property.type || t('common.hotel', 'Hotel')" loading="eager" />
                        <span class="property-gallery__view-label"><i class="bi bi-images" aria-hidden="true"></i>{{ t('common.view_all_photos', 'View all photos') }}</span>
                    </button>
                    <div v-if="secondaryImages.length" class="property-gallery__secondary">
                        <button v-for="(image, index) in secondaryImages" :key="index" type="button" @click="openGallery(index + 1)">
                            <ImageWithFallback :src="mediaUrl(image.url)" :alt="image.alt || property.name" aspect="square" kind="hotel" :label="property.type || t('common.hotel', 'Hotel')" loading="lazy" />
                        </button>
                    </div>
                </section>

                <section class="property-stay-panel" aria-labelledby="stay-panel-heading">
                    <div class="property-stay-panel__intro">
                        <span class="public-eyebrow">{{ t('common.plan_your_stay', 'Plan your stay') }}</span>
                        <h2 id="stay-panel-heading">{{ hasDates ? t('common.stay_context', 'Your stay details') : t('common.check_availability', 'Check availability') }}</h2>
                        <p>{{ hasDates ? formatDate(stay.check_in) + ' – ' + formatDate(stay.check_out) : t('common.select_dates_for_exact_rates', 'Choose dates and guests to see exact availability and rates.') }}</p>
                    </div>
                    <form class="property-stay-form" @submit.prevent="checkAvailability">
                        <DateRangeField v-model:start="stay.check_in" v-model:end="stay.check_out" :label="t('common.stay_dates', 'Check-in — Check-out')" id="property-stay-dates" required />
                        <OccupancyField id="property-guests" v-model:adults="stay.adults" v-model:children="stay.children" v-model:rooms="stay.rooms" :label="t('common.guests_and_rooms', 'Guests and rooms')" />
                        <button type="submit" class="public-button public-button--primary" :disabled="checking">{{ checking ? t('common.checking', 'Checking…') : t('common.update_stay', 'Update stay') }}</button>
                    </form>
                    <p v-if="checkError" class="property-stay-panel__error" role="alert">{{ checkError }}</p>
                    <p v-if="availability" class="property-stay-panel__result" :class="{ 'is-available': availability.available }"><i class="bi" :class="availability.available ? 'bi-check-circle' : 'bi-info-circle'" aria-hidden="true"></i>{{ availability.available ? t('common.available_for_stay', 'Options available for your stay') : t('common.no_rooms_for_dates', 'No rooms available for these dates') }}</p>
                </section>

                <div class="property-detail-layout">
                    <div class="property-detail-main">
                        <section v-if="property.short_description || property.description" class="property-content-section" aria-labelledby="overview-heading">
                            <span class="public-eyebrow">{{ t('common.overview', 'Overview') }}</span>
                            <h2 id="overview-heading">{{ t('common.about_this_property', 'About this property') }}</h2>
                            <p v-if="property.short_description" class="property-lede">{{ property.short_description }}</p>
                            <div v-if="property.description" class="property-description" v-html="property.description"></div>
                        </section>

                        <section id="rooms" class="property-content-section property-rooms-section" aria-labelledby="rooms-heading">
                            <div class="property-section-heading"><span class="public-eyebrow">{{ t('common.accommodation', 'Accommodation') }}</span><h2 id="rooms-heading">{{ t('common.rooms_and_rates', 'Rooms & rates') }}</h2><p>{{ hasDates ? t('common.rooms_rates_description', 'Compare room types and the commercial terms available for your selected stay.') : t('common.browse_room_types', 'Browse the room types, then add dates when you are ready to check availability.') }}</p></div>
                            <div v-if="property.rooms?.length" class="property-room-list">
                                <RoomTypeCard v-for="room in property.rooms" :key="room.slug" :room="room" :rates="roomRates(room)" :availability="roomAvailability(room)" :has-dates="hasDates && Boolean(rates)" :selection="selectedRooms[room.slug]" :max-quantity="Math.max(0, Math.min(Number(roomAvailability(room)?.available_rooms || 0), maxRoomsPerBooking - selectedRoomCount + Number(selectedRooms[room.slug]?.quantity || 0)))" @change="changeRoom($event.rate, room, $event.quantity)" />
                            </div>
                            <EmptyState v-else :title="t('common.no_room_types', 'Room details coming soon')" :description="t('common.no_room_types_description', 'This property has not published room types yet.')"><a v-if="property.phone" :href="'tel:' + property.phone" class="public-button public-button--outline">{{ t('common.contact_property', 'Contact property') }}</a></EmptyState>
                        </section>

                        <section v-if="Object.keys(groupedAmenities()).length" class="property-content-section" aria-labelledby="amenities-heading">
                            <div class="property-section-heading"><span class="public-eyebrow">{{ t('common.at_the_property', 'At the property') }}</span><h2 id="amenities-heading">{{ t('common.amenities', 'Amenities') }}</h2></div>
                            <div class="property-amenity-groups"><div v-for="(amenities, group) in groupedAmenities()" :key="group" class="property-amenity-group"><h3>{{ group }}</h3><ul><li v-for="amenity in amenities" :key="amenity.name"><i :class="amenity.icon || 'bi bi-check2'" aria-hidden="true"></i><span>{{ amenity.name }}</span></li></ul></div></div>
                        </section>

                        <section v-if="property.customFields?.length" class="property-content-section" aria-labelledby="details-heading">
                            <div class="property-section-heading"><span class="public-eyebrow">{{ t('common.details', 'Details') }}</span><h2 id="details-heading">{{ t('common.property_highlights', 'Property highlights') }}</h2></div>
                            <div class="property-facts"><div v-for="group in property.customFields" :key="group.group" class="property-fact-group"><h3>{{ group.group }}</h3><dl><template v-for="field in group.fields" :key="field.label || field.value"><dt>{{ field.label }}</dt><dd><a v-if="field.type === 'url'" :href="field.value" target="_blank" rel="noopener noreferrer">{{ field.value }}</a><span v-else>{{ field.value }}</span></dd></template></dl></div></div>
                        </section>

                        <PropertyReviews :summary="reviewSummary" :reviews="reviews" :categories="reviewCategories" :sort="reviewSort" :property-slug="property.slug" :stay="stay" />
                    </div>

                    <aside class="property-detail-aside">
                        <div v-if="selectedRoomCount" class="property-selection-card">
                            <span class="public-eyebrow">{{ t('common.your_selection', 'Your selection') }}</span>
                            <h2>{{ selectedRoomCount }} {{ selectedRoomCount === 1 ? t('common.room', 'room') : t('common.rooms', 'rooms') }}</h2>
                            <p>{{ formatDate(stay.check_in) }} – {{ formatDate(stay.check_out) }}</p>
                            <ul class="property-selection-list"><li v-for="item in selectionItems" :key="item.room_type_id">{{ item.room_name }} × {{ item.quantity }} <small>{{ item.rate_name }}</small></li></ul>
                            <div v-if="selectionQuote" class="property-selection-total"><span>{{ t('common.total', 'Total') }}</span><MoneyDisplay :money="selectionQuote.display_total" /></div>
                            <p v-else-if="selectionError" role="alert">{{ selectionError }}</p>
                            <p v-else>{{ t('common.rechecking_price', 'Rechecking price…') }}</p>
                            <Link v-if="selectionQuote" :href="bookingHref" class="public-button public-button--primary public-button--lg">{{ t('common.continue_to_booking', 'Continue to booking') }}</Link>
                        </div>
                        <div class="property-aside-card">
                            <span class="public-eyebrow">{{ t('common.good_to_know', 'Good to know') }}</span>
                            <h2>{{ t('common.property_policies', 'Property policies') }}</h2>
                            <dl class="property-policy-list">
                                <div v-if="property.check_in_time"><dt>{{ t('common.check_in', 'Check-in') }}</dt><dd>{{ formatTime(property.check_in_time) }}</dd></div>
                                <div v-if="property.check_out_time"><dt>{{ t('common.check_out', 'Check-out') }}</dt><dd>{{ formatTime(property.check_out_time) }}</dd></div>
                                <div v-if="property.children_policy"><dt>{{ t('common.children', 'Children') }}</dt><dd>{{ property.children_policy }}</dd></div>
                                <div v-if="property.pet_policy"><dt>{{ t('common.pets', 'Pets') }}</dt><dd>{{ property.pet_policy }}</dd></div>
                                <div v-if="property.smoking_policy"><dt>{{ t('common.smoking', 'Smoking') }}</dt><dd>{{ property.smoking_policy }}</dd></div>
                            </dl>
                            <p v-if="property.check_in_instructions" class="property-policy-note">{{ property.check_in_instructions }}</p>
                            <p v-if="property.house_rules" class="property-policy-note">{{ property.house_rules }}</p>
                        </div>
                        <div class="property-aside-card">
                            <span class="public-eyebrow">{{ t('common.location', 'Location') }}</span>
                            <h2>{{ locationText || t('common.property_location', 'Property location') }}</h2>
                            <p v-if="property.address_line_1">{{ property.address_line_1 }}<span v-if="property.address_line_2">, {{ property.address_line_2 }}</span></p>
                            <p v-if="property.postal_code || property.country_code">{{ property.postal_code }} {{ property.country_code }}</p>
                            <a v-if="property.website" :href="property.website" target="_blank" rel="noopener noreferrer" class="public-button public-button--outline public-button--sm">{{ t('common.visit_website', 'Visit website') }}</a>
                        </div>
                    </aside>
                </div>
            </div>
        </main>

        <div v-if="selectedRoomCount" class="property-mobile-selection">
            <div><span>{{ selectedRoomCount }} {{ selectedRoomCount === 1 ? t('common.room', 'room') : t('common.rooms', 'rooms') }} · {{ selectionItems.map((item) => item.room_name + ' × ' + item.quantity).join(', ') }}</span><MoneyDisplay v-if="selectionQuote" :money="selectionQuote.display_total" /><small v-else>{{ selectionError || t('common.rechecking_price', 'Rechecking price…') }}</small></div>
            <Link v-if="selectionQuote" :href="bookingHref" class="public-button public-button--primary public-button--sm">{{ t('common.continue_to_booking', 'Continue to booking') }}</Link>
        </div>

        <div v-if="galleryOpen" class="property-lightbox" role="dialog" aria-modal="true" :aria-label="t('common.property_photos', 'Property photos')" @click.self="closeGallery">
            <button type="button" class="public-icon-button property-lightbox__close" :aria-label="t('common.close', 'Close')" @click="closeGallery"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            <button v-if="gallery.length > 1" type="button" class="property-lightbox__prev" :aria-label="t('common.previous_photo', 'Previous photo')" @click="galleryIndex = (galleryIndex - 1 + gallery.length) % gallery.length"><i class="bi bi-arrow-left" data-dir-icon="arrow" aria-hidden="true"></i></button>
            <ImageWithFallback :src="mediaUrl(gallery[galleryIndex]?.url)" :alt="gallery[galleryIndex]?.alt || property.name" aspect="editorial" kind="hotel" :label="property.name" loading="eager" />
            <button v-if="gallery.length > 1" type="button" class="property-lightbox__next" :aria-label="t('common.next_photo', 'Next photo')" @click="galleryIndex = (galleryIndex + 1) % gallery.length"><i class="bi bi-arrow-right" data-dir-icon="arrow" aria-hidden="true"></i></button>
        </div>
    </PublicLayout>
</template>
