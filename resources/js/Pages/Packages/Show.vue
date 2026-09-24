<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { appUrl } from '../../appUrl';
import { useLocalization } from '../../i18n';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import DateField from '../../Components/Public/Search/DateField.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import MoneyDisplay from '../../Components/Public/UI/MoneyDisplay.vue';
import RatingDisplay from '../../Components/Public/UI/RatingDisplay.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import { mediaUrl } from '../../Components/Public/homepage';

const props = defineProps({
    package: { type: Object, required: true },
    reviews: { type: Object, default: () => ({ data: [], links: [] }) },
    context: { type: Object, default: () => ({}) },
    bookability: { type: Object, default: () => ({ bookable: null, reason: null }) },
    quote: { type: Object, default: () => ({}) },
    seo: { type: Object, default: () => ({}) },
});

const { locale, t } = useLocalization();
const galleryIndex = ref(0);
const galleryOpen = ref(false);
const quoteLoading = ref(false);
const quoteError = ref('');
const bookability = ref({ ...props.bookability });
const quote = ref({ ...props.quote });
const selection = reactive({
    travel_date: props.context.travel_date || '',
    adults: Number(props.context.adults || 1),
    children: Number(props.context.children || 0),
});
const reviewForm = useForm({ name: '', email: '', rating: 0, comment: '' });
const hoveredRating = ref(0);
let quoteTimer = null;

const gallery = computed(() => Array.isArray(props.package.gallery) ? props.package.gallery : []);
const currentImage = computed(() => gallery.value[galleryIndex.value] || null);
const hasDate = computed(() => Boolean(selection.travel_date));
const canBook = computed(() => hasDate.value && bookability.value.bookable === true);
const reviewCount = computed(() => Number(props.package.approved_reviews_count || 0));
const averageRating = computed(() => Number(props.package.approved_reviews_avg_rating || 0));
const overviewExcerpt = computed(() => {
    const overview = props.package.overview ? props.package.overview.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : '';

    return overview.slice(0, 260);
});
const durationLabel = computed(() => {
    const days = Number(props.package.duration_days);
    const nights = Number(props.package.duration_nights);

    if (!Number.isFinite(days) || days < 1) return null;
    if (days === 1) return t('common.same_day', 'Same day');

    const dayText = `${days} ${days === 1 ? t('common.day', 'day') : t('common.days', 'days')}`;
    const nightText = Number.isFinite(nights) && nights > 0
        ? ` · ${nights} ${nights === 1 ? t('common.night', 'night') : t('common.nights', 'nights')}`
        : '';

    return dayText + nightText;
});
const destinationLabel = computed(() => props.package.destinations?.map((item) => item.name).join(' · ') || props.package.city?.name || '');
const backToSearchHref = computed(() => {
    const params = new URLSearchParams();
    if (selection.travel_date) params.set('travel_date', selection.travel_date);
    params.set('adults', String(selection.adults));
    params.set('children', String(selection.children));
    return appUrl(`/search/tours?${params.toString()}`);
});
const bookingHref = computed(() => {
    if (!canBook.value) return '#booking-panel';

    const params = new URLSearchParams({
        travel_date: selection.travel_date,
        adults: String(selection.adults),
        children: String(selection.children),
    });

    return appUrl(`${props.package.booking_url}?${params.toString()}`);
});
const showNoDateMessage = computed(() => !hasDate.value && !props.context.invalid_date);

function formatDate(value) {
    if (!value) return t('common.select_date', 'Select date');

    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${value}T12:00:00`));
    } catch {
        return value;
    }
}

function normalizeParty() {
    selection.adults = Math.max(1, Math.min(30, Number(selection.adults || 1)));
    selection.children = Math.max(0, Math.min(30, Number(selection.children || 0)));
}

async function updateQuote() {
    normalizeParty();
    quoteError.value = '';
    quoteLoading.value = true;

    try {
        const response = await axios.post(appUrl('/booking/estimate'), {
            package_id: props.package.id,
            total_adults: selection.adults,
            total_children: selection.children,
            travel_date: selection.travel_date || undefined,
        });
        quote.value = { ...response.data, total_money: response.data.display_money?.total_amount };
        bookability.value = response.data.availability || { bookable: null, reason: null };
    } catch (error) {
        quoteError.value = error.response?.data?.errors?.travel_date?.[0]
            || error.response?.data?.message
            || t('common.tour_quote_error', 'The tour details could not be updated. Please try again.');
        bookability.value = { bookable: false, reason: quoteError.value };
    } finally {
        quoteLoading.value = false;
    }
}

function scheduleQuoteUpdate() {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(updateQuote, 260);
}

function openGallery(index) {
    if (!gallery.value.length) return;
    galleryIndex.value = index;
    galleryOpen.value = true;
}

function closeGallery() {
    galleryOpen.value = false;
}

function moveGallery(direction) {
    if (gallery.value.length < 2) return;
    galleryIndex.value = (galleryIndex.value + direction + gallery.value.length) % gallery.value.length;
}

function scrollToBooking() {
    document.getElementById('booking-panel')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function formatReviewDate(value) {
    if (!value) return '';
    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { dateStyle: 'medium' }).format(new Date(value));
    } catch {
        return value;
    }
}

function submitReview() {
    reviewForm.post(appUrl(`/packages/${props.package.slug}/reviews`), {
        preserveScroll: true,
        onSuccess: () => reviewForm.reset('rating', 'comment'),
    });
}

function onKeydown(event) {
    if (event.key === 'Escape') closeGallery();
    if (!galleryOpen.value) return;
    if (event.key === 'ArrowRight') moveGallery(1);
    if (event.key === 'ArrowLeft') moveGallery(-1);
}

watch([() => selection.travel_date, () => selection.adults, () => selection.children], scheduleQuoteUpdate);
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    clearTimeout(quoteTimer);
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <PublicLayout main-class="tour-detail-page">
        <SeoHead :title="seo.title || package.title" :description="seo.description" :image="seo.image" :canonical="seo.canonical" :structured-data="seo.structuredData" :alternates="seo.hreflang" type="article" />

        <div class="tour-detail-shell">
            <div class="public-container tour-detail-container">
                <nav class="tour-detail-breadcrumbs" aria-label="Breadcrumb">
                    <Link :href="appUrl('/')">{{ t('navigation.home', 'Home') }}</Link>
                    <span aria-hidden="true">/</span>
                    <Link v-if="package.destinations?.[0]" :href="appUrl(package.destinations[0].url)">{{ package.destinations[0].name }}</Link>
                    <span v-if="package.destinations?.[0]" aria-hidden="true">/</span>
                    <Link :href="backToSearchHref"><i class="bi bi-arrow-left" data-dir-icon="arrow" aria-hidden="true"></i>{{ t('common.back_to_tours', 'Back to tours') }}</Link>
                    <span aria-hidden="true">/</span>
                    <span>{{ package.title }}</span>
                </nav>

                <section class="tour-detail-hero" aria-labelledby="tour-title">
                    <div class="tour-detail-hero__media">
                        <button type="button" class="tour-detail-hero__image" :aria-label="t('common.show_all_photos', 'Show all photos')" @click="openGallery(0)">
                            <ImageWithFallback :src="mediaUrl(currentImage)" :alt="package.title" aspect="editorial" kind="tour" :label="package.category?.name || t('common.tour', 'Tour')" loading="eager" />
                            <span v-if="gallery.length > 1" class="tour-detail-hero__gallery-label"><i class="bi bi-images" aria-hidden="true"></i>{{ t('common.show_all_photos', 'Show all photos') }}</span>
                        </button>
                        <div v-if="gallery.length > 1" class="tour-detail-gallery-strip" aria-label="Tour photos">
                            <button v-for="(image, index) in gallery.slice(0, 5)" :key="image" type="button" :class="{ 'is-active': index === galleryIndex }" @click="openGallery(index)">
                                <ImageWithFallback :src="mediaUrl(image)" :alt="`${package.title} ${index + 1}`" aspect="square" kind="tour" loading="lazy" />
                            </button>
                        </div>
                    </div>

                    <div class="tour-detail-hero__copy">
                        <div class="tour-detail-hero__eyebrow">
                            <span v-if="package.category?.name" class="public-badge public-badge--accent">{{ package.category.name }}</span>
                            <span v-if="package.is_featured" class="public-badge public-badge--brand"><i class="bi bi-stars" aria-hidden="true"></i>{{ t('common.featured', 'Featured') }}</span>
                        </div>
                        <h1 id="tour-title">{{ package.title }}</h1>
                        <p v-if="destinationLabel" class="tour-detail-hero__location"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ destinationLabel }}</p>
                        <div class="tour-detail-hero__rating">
                            <RatingDisplay :rating="averageRating" :review-count="reviewCount" :label="t('common.not_rated', 'Not rated')" />
                            <a v-if="reviewCount" href="#reviews">{{ reviewCount }} {{ reviewCount === 1 ? t('common.review', 'review') : t('common.reviews', 'reviews') }}</a>
                        </div>
                        <p class="tour-detail-hero__lede">{{ overviewExcerpt || t('common.tour_detail_intro', 'A carefully planned journey through places worth remembering.') }}</p>
                        <div class="tour-detail-hero__facts">
                            <span v-if="durationLabel"><i class="bi bi-clock" aria-hidden="true"></i><strong>{{ durationLabel }}</strong></span>
                            <span v-if="package.city?.name"><i class="bi bi-building" aria-hidden="true"></i>{{ package.city.name }} · {{ t('common.city', 'City') }}</span>
                        </div>
                        <div class="tour-detail-hero__price">
                            <span>{{ t('common.from', 'From') }}</span>
                            <MoneyDisplay :money="package.display_money" />
                            <small>{{ t('common.starting_tour_price', 'starting tour price') }}</small>
                        </div>
                        <div class="tour-detail-hero__actions">
                            <button type="button" class="public-button public-button--primary public-button--lg" @click="scrollToBooking">
                                {{ canBook ? t('common.book_tour', 'Book tour') : t('common.select_date', 'Select date') }}
                                <i class="bi bi-arrow-down" data-dir-icon="arrow" aria-hidden="true"></i>
                            </button>
                            <a :href="backToSearchHref" class="public-button public-button--outline public-button--lg">{{ t('common.explore_tours', 'Explore tours') }}</a>
                        </div>
                    </div>
                </section>

                <nav class="tour-detail-section-nav" aria-label="Tour sections">
                    <a href="#overview">{{ t('common.overview', 'Overview') }}</a>
                    <a v-if="package.itinerary?.length" href="#itinerary">{{ t('common.itinerary', 'Itinerary') }}</a>
                    <a v-if="package.inclusions?.length || package.exclusions?.length" href="#included">{{ t('common.inclusions', 'Inclusions') }}</a>
                    <a v-if="reviewCount" href="#reviews">{{ t('common.reviews', 'Reviews') }}</a>
                </nav>

                <div class="tour-detail-layout">
                    <main class="tour-detail-main">
                        <section id="overview" class="tour-detail-section" aria-labelledby="overview-heading">
                            <div class="tour-detail-section__heading">
                                <span class="public-eyebrow">{{ t('common.the_journey', 'The journey') }}</span>
                                <h2 id="overview-heading">{{ t('common.tour_overview', 'A journey with room to look around') }}</h2>
                            </div>
                            <div v-if="package.tags?.length" class="tour-detail-tag-list" aria-label="Tour tags">
                                <span v-for="tag in package.tags" :key="tag.id" class="public-chip">{{ tag.name }}</span>
                            </div>
                            <div v-if="package.overview" class="tour-detail-richtext" v-html="package.overview"></div>
                            <EmptyState v-else :title="t('common.tour_overview_coming_soon', 'Tour details coming soon')" :description="t('common.tour_overview_empty', 'This tour has not published a longer overview yet.')" />
                        </section>

                        <section v-if="package.itinerary?.length" id="itinerary" class="tour-detail-section" aria-labelledby="itinerary-heading">
                            <div class="tour-detail-section__heading">
                                <span class="public-eyebrow">{{ t('common.days_on_the_road', 'Days on the road') }}</span>
                                <h2 id="itinerary-heading">{{ t('common.itinerary', 'Itinerary') }}</h2>
                                <p>{{ t('common.itinerary_description', 'A considered rhythm of places, pauses and moments, kept clear enough to plan around.') }}</p>
                            </div>
                            <ol class="tour-itinerary">
                                <li v-for="(day, index) in package.itinerary" :key="`${day.day}-${index}`" class="tour-itinerary__day">
                                    <div class="tour-itinerary__marker"><span>{{ day.day }}</span></div>
                                    <div class="tour-itinerary__content">
                                        <div class="tour-itinerary__topline"><span>{{ t('common.day', 'Day') }} {{ day.day }}</span><span v-if="day.places?.length">{{ day.places.length }} {{ day.places.length === 1 ? t('common.place', 'place') : t('common.places', 'places') }}</span></div>
                                        <h3>{{ day.title || `${t('common.day', 'Day')} ${day.day}` }}</h3>
                                        <p v-if="day.summary" class="tour-itinerary__summary">{{ day.summary }}</p>
                                        <ul v-if="day.details?.length" class="tour-itinerary__details">
                                            <li v-for="detail in day.details" :key="detail"><i class="bi bi-arrow-up-right" aria-hidden="true"></i><span>{{ detail }}</span></li>
                                        </ul>
                                        <div v-if="day.places?.length" class="tour-itinerary__places">
                                            <Link v-for="place in day.places" :key="place.id" :href="appUrl(place.url)" class="public-chip public-chip--brand">{{ place.name }}</Link>
                                        </div>
                                    </div>
                                </li>
                            </ol>
                        </section>

                        <section v-if="package.places?.length" class="tour-detail-section tour-detail-places" aria-labelledby="places-heading">
                            <div class="tour-detail-section__heading">
                                <span class="public-eyebrow">{{ t('common.along_the_route', 'Along the route') }}</span>
                                <h2 id="places-heading">{{ t('common.places_to_discover', 'Places to discover') }}</h2>
                            </div>
                            <div class="tour-place-list">
                                <Link v-for="place in package.places" :key="place.id" :href="appUrl(place.url)" class="tour-place-list__item">
                                    <ImageWithFallback :src="mediaUrl(place.image)" :alt="place.name" aspect="square" kind="place" :label="place.name" loading="lazy" />
                                    <span><strong>{{ place.name }}</strong><small v-if="place.destination?.name">{{ place.destination.name }}</small></span>
                                    <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                                </Link>
                            </div>
                        </section>

                        <section v-if="package.inclusions?.length || package.exclusions?.length" id="included" class="tour-detail-section" aria-labelledby="included-heading">
                            <div class="tour-detail-section__heading">
                                <span class="public-eyebrow">{{ t('common.tour_details', 'Tour details') }}</span>
                                <h2 id="included-heading">{{ t('common.what_is_included', 'What is included') }}</h2>
                            </div>
                            <div class="tour-detail-terms">
                                <div v-if="package.inclusions?.length" class="tour-detail-terms__group tour-detail-terms__group--included">
                                    <h3><i class="bi bi-check2" aria-hidden="true"></i>{{ t('common.inclusions', 'Inclusions') }}</h3>
                                    <ul><li v-for="item in package.inclusions" :key="item"><i class="bi bi-check2-circle" aria-hidden="true"></i><span>{{ item }}</span></li></ul>
                                </div>
                                <div v-if="package.exclusions?.length" class="tour-detail-terms__group tour-detail-terms__group--excluded">
                                    <h3><i class="bi bi-dash-circle" aria-hidden="true"></i>{{ t('common.exclusions', 'Exclusions') }}</h3>
                                    <ul><li v-for="item in package.exclusions" :key="item"><i class="bi bi-dash-circle" aria-hidden="true"></i><span>{{ item }}</span></li></ul>
                                </div>
                            </div>
                        </section>

                        <section v-if="reviewCount || reviews.data?.length" id="reviews" class="tour-detail-section tour-detail-reviews" aria-labelledby="reviews-heading">
                            <div class="tour-detail-section__heading">
                                <span class="public-eyebrow">{{ t('common.traveller_notes', 'Traveller notes') }}</span>
                                <h2 id="reviews-heading">{{ t('common.reviews', 'Reviews') }}</h2>
                            </div>
                            <div v-if="reviewCount" class="tour-review-summary">
                                <strong>{{ averageRating.toFixed(1) }}</strong>
                                <RatingDisplay :rating="averageRating" :review-count="reviewCount" />
                                <span>{{ reviewCount }} {{ reviewCount === 1 ? t('common.review', 'review') : t('common.reviews', 'reviews') }}</span>
                            </div>
                            <p v-else class="tour-detail-reviews__quiet">{{ t('common.no_reviews_yet', 'No published reviews yet.') }}</p>
                            <div v-if="reviews.data?.length" class="tour-review-list">
                                <article v-for="review in reviews.data" :key="review.id" class="tour-review-card">
                                    <div class="tour-review-card__top"><div><strong>{{ review.reviewer_name }}</strong><span v-if="review.is_verified_booking" class="tour-review-card__verified"><i class="bi bi-patch-check" aria-hidden="true"></i>{{ t('common.verified_booking', 'Verified booking') }}</span></div><time :datetime="review.created_at">{{ formatReviewDate(review.created_at) }}</time></div>
                                    <RatingDisplay :rating="review.rating" />
                                    <p>{{ review.comment }}</p>
                                </article>
                            </div>
                            <div v-if="reviews.last_page > 1" class="tour-review-pagination"><Link v-if="reviews.current_page > 1" :href="reviews.prev_page_url" class="public-button public-button--outline public-button--sm" preserve-scroll><i class="bi bi-arrow-left" data-dir-icon="arrow" aria-hidden="true"></i>{{ t('common.previous', 'Previous') }}</Link><span>{{ reviews.current_page }} / {{ reviews.last_page }}</span><Link v-if="reviews.current_page < reviews.last_page" :href="reviews.next_page_url" class="public-button public-button--outline public-button--sm" preserve-scroll>{{ t('common.next', 'Next') }}<i class="bi bi-arrow-right" data-dir-icon="arrow" aria-hidden="true"></i></Link></div>
                        </section>

                        <section v-else class="tour-detail-section tour-detail-reviews tour-detail-reviews--empty" aria-labelledby="reviews-heading">
                            <div class="tour-detail-section__heading"><span class="public-eyebrow">{{ t('common.traveller_notes', 'Traveller notes') }}</span><h2 id="reviews-heading">{{ t('common.reviews', 'Reviews') }}</h2></div>
                            <p class="tour-detail-reviews__quiet">{{ t('common.no_reviews_yet', 'No published reviews yet.') }}</p>
                        </section>

                        <section class="tour-review-form" aria-labelledby="share-review-heading">
                            <span class="public-eyebrow">{{ t('common.share_experience', 'Share your experience') }}</span>
                            <h2 id="share-review-heading">{{ t('common.tell_other_travellers', 'Tell other travellers what stood out') }}</h2>
                            <p>{{ t('common.review_moderation_note', 'Reviews are checked before they appear publicly.') }}</p>
                            <form @submit.prevent="submitReview">
                                <div class="tour-review-form__grid">
                                    <label class="public-field"><span class="public-field__label">{{ t('common.name', 'Name') }}</span><input v-model="reviewForm.name" class="public-input" autocomplete="name" maxlength="100" required><small v-if="reviewForm.errors.name">{{ reviewForm.errors.name }}</small></label>
                                    <label class="public-field"><span class="public-field__label">{{ t('common.email', 'Email') }} <em>({{ t('common.optional', 'optional') }})</em></span><input v-model="reviewForm.email" class="public-input" type="email" autocomplete="email" maxlength="255"><small v-if="reviewForm.errors.email">{{ reviewForm.errors.email }}</small></label>
                                </div>
                                <fieldset class="tour-review-form__rating"><legend class="public-field__label">{{ t('common.rating', 'Rating') }}</legend><div @mouseleave="hoveredRating = 0"><button v-for="star in 5" :key="star" type="button" :aria-label="`${star} ${t('common.stars', 'stars')}`" @mouseenter="hoveredRating = star" @focus="hoveredRating = star" @blur="hoveredRating = 0" @click="reviewForm.rating = star"><span aria-hidden="true">{{ star <= (hoveredRating || reviewForm.rating) ? '★' : '☆' }}</span></button></div><small v-if="reviewForm.errors.rating">{{ reviewForm.errors.rating }}</small></fieldset>
                                <label class="public-field"><span class="public-field__label">{{ t('common.review_comment', 'Review') }}</span><textarea v-model="reviewForm.comment" class="public-textarea" minlength="10" maxlength="2000" rows="4" required></textarea><small v-if="reviewForm.errors.comment">{{ reviewForm.errors.comment }}</small></label>
                                <button type="submit" class="public-button public-button--primary" :disabled="reviewForm.processing">{{ reviewForm.processing ? t('common.submitting', 'Submitting…') : t('common.submit_review', 'Submit review') }}</button>
                            </form>
                        </section>
                    </main>

                    <aside id="booking-panel" class="tour-detail-aside">
                        <div class="tour-booking-card">
                            <span class="public-eyebrow">{{ t('common.plan_your_tour', 'Plan your tour') }}</span>
                            <h2>{{ t('common.your_tour_details', 'Your tour details') }}</h2>
                            <form class="tour-booking-form" @submit.prevent="updateQuote">
                                <DateField v-model="selection.travel_date" :label="t('common.travel_date', 'Travel date')" id="tour-detail-date" min="today" />
                                <p v-if="props.context.invalid_date && !selection.travel_date" id="tour-date-error" class="tour-booking-card__error" role="alert">{{ t('common.invalid_travel_date', 'Please choose a valid travel date.') }}</p>
                                <div class="tour-booking-form__party"><label class="public-field"><span class="public-field__label">{{ t('common.adults', 'Adults') }}</span><input v-model.number="selection.adults" class="public-input" type="number" min="1" max="30"></label><label class="public-field"><span class="public-field__label">{{ t('common.children', 'Children') }}</span><input v-model.number="selection.children" class="public-input" type="number" min="0" max="30"></label></div>
                                <button type="submit" class="public-button public-button--outline tour-booking-form__update" :disabled="quoteLoading"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>{{ quoteLoading ? t('common.checking', 'Checking…') : t('common.update_details', 'Update details') }}</button>
                            </form>
                            <div v-if="quoteError" class="tour-booking-card__error" role="alert">{{ quoteError }}</div>
                            <div v-if="showNoDateMessage" class="tour-booking-card__status tour-booking-card__status--neutral"><i class="bi bi-calendar3" aria-hidden="true"></i><span>{{ t('common.select_date_to_book', 'Select a travel date to check bookability.') }}</span></div>
                            <div v-else-if="bookability.bookable === true" class="tour-booking-card__status tour-booking-card__status--available"><i class="bi bi-check-circle" aria-hidden="true"></i><span>{{ t('common.available_for_date', 'Available for this date') }}</span></div>
                            <div v-else-if="bookability.bookable === false" class="tour-booking-card__status tour-booking-card__status--unavailable"><i class="bi bi-info-circle" aria-hidden="true"></i><span>{{ bookability.reason || t('common.unavailable_for_date', 'This date is not available for this tour.') }}</span></div>
                            <div class="tour-booking-summary"><div><span>{{ t('common.starting_price', 'starting price') }}</span><MoneyDisplay :money="quote.total_money || package.display_money" /></div><small>{{ t('common.server_price_note', 'Final pricing is recalculated securely when you continue.') }}</small></div>
                            <Link v-if="canBook" :href="bookingHref" class="public-button public-button--primary public-button--lg tour-booking-card__cta">{{ t('common.book_tour', 'Book tour') }}<i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i></Link>
                            <button v-else type="button" class="public-button public-button--primary public-button--lg tour-booking-card__cta" @click="selection.travel_date ? updateQuote() : scrollToBooking()">{{ hasDate ? t('common.choose_date', 'Choose another date') : t('common.select_date', 'Select date') }}</button>
                            <p class="tour-booking-card__fineprint">{{ t('common.no_seat_inventory_note', 'Your date is a booking context. This page does not show departure or seat inventory.') }}</p>
                        </div>
                        <div v-if="package.destinations?.length" class="tour-context-card"><span class="public-eyebrow">{{ t('common.destination_context', 'Destination context') }}</span><div v-for="destination in package.destinations" :key="destination.id"><Link :href="appUrl(destination.url)">{{ destination.name }}<i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i></Link></div></div>
                    </aside>
                </div>
            </div>
        </div>

        <div v-if="galleryOpen" class="tour-gallery-dialog" role="dialog" aria-modal="true" :aria-label="`${package.title} ${t('common.gallery', 'gallery')}`" @click.self="closeGallery">
            <button type="button" class="tour-gallery-dialog__close public-icon-button" :aria-label="t('common.close', 'Close')" @click="closeGallery"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            <button v-if="gallery.length > 1" type="button" class="tour-gallery-dialog__nav tour-gallery-dialog__nav--prev public-icon-button" :aria-label="t('common.previous_photo', 'Previous photo')" @click="moveGallery(-1)"><i class="bi bi-chevron-left" data-dir-icon="arrow" aria-hidden="true"></i></button>
            <ImageWithFallback :src="mediaUrl(currentImage)" :alt="`${package.title} ${galleryIndex + 1}`" aspect="editorial" kind="tour" :label="package.title" loading="eager" />
            <button v-if="gallery.length > 1" type="button" class="tour-gallery-dialog__nav tour-gallery-dialog__nav--next public-icon-button" :aria-label="t('common.next_photo', 'Next photo')" @click="moveGallery(1)"><i class="bi bi-chevron-right" data-dir-icon="arrow" aria-hidden="true"></i></button>
        </div>
    </PublicLayout>
</template>
