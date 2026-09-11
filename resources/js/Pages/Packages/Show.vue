<script setup>
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ItineraryAccordion from '../../Components/ItineraryAccordion.vue';
import StarRating from '../../Components/StarRating.vue';
import TourPlanEnquiryModal from '../../Components/TourPlanEnquiryModal.vue';
import { appUrl } from '../../appUrl';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({ package: Object, reviews: Object, securityQuestion: String });

const gallery = computed(() => [...new Set([
    props.package.cover_image,
    ...(Array.isArray(props.package.gallery) ? props.package.gallery : []),
].filter(Boolean))]);
const itinerary = computed(() => Array.isArray(props.package.day_wise_itinerary) ? props.package.day_wise_itinerary : []);
const inclusions = computed(() => Array.isArray(props.package.inclusions) ? props.package.inclusions.filter(Boolean) : []);
const exclusions = computed(() => Array.isArray(props.package.exclusions) ? props.package.exclusions.filter(Boolean) : []);

const activeImage = ref(0);
const isGalleryOpen = ref(false);
const isTourEnquiryOpen = ref(false);
const hoveredRating = ref(0);
const reviewForm = useForm({ name: '', email: '', rating: 0, comment: '' });
const averageRating = computed(() => Number(props.package.approved_reviews_avg_rating || 0));
const approvedReviewCount = computed(() => Number(props.package.approved_reviews_count || 0));

const adults = ref(2);
const children = ref(0);
const unitPrice = computed(() => Number(props.package.discounted_price || props.package.price || 0));
const childPrice = computed(() => Math.round(unitPrice.value * 0.6));
const totalPrice = computed(() => (adults.value * unitPrice.value) + (children.value * childPrice.value));


const seoDescription = computed(() => {
    if (props.package.meta_description) {
        return props.package.meta_description;
    }

    if (props.package.overview) {
        const text = props.package.overview
            .replace(/<[^>]*>/g, '')
            .trim();

        return text.length > 160
            ? `${text.substring(0, 157)}...`
            : text;
    }

    return `Discover ${props.package.title}, itinerary, pricing and booking information.`;
});
function previousImage() {
    activeImage.value = (activeImage.value - 1 + gallery.value.length) % gallery.value.length;
}

function nextImage() {
    activeImage.value = (activeImage.value + 1) % gallery.value.length;
}

function submitReview() {
    reviewForm.post(appUrl(`/packages/${props.package.slug}/reviews`), {
        preserveScroll: true,
        onSuccess: () => reviewForm.reset('rating', 'comment'),
    });
}

function formatReviewDate(value) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value));
}
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="
        package.meta_title ||
        package.title
    "
            :description="seoDescription"
            :image="package.cover_image"
            type="article"
        />
        <div class="container py-4">
            <!-- Gallery -->
            <div v-if="gallery.length" class="gallery-hero mb-2">
                <button type="button" class="gallery-main-image" aria-label="View image larger" @click="isGalleryOpen = true">
                    <img :src="gallery[activeImage]" :alt="`${package.title} photo ${activeImage + 1}`" />
                </button>
                <button v-if="gallery.length > 1" type="button" class="gallery-control gallery-control-prev" aria-label="Previous image" @click="previousImage"><i class="bi bi-chevron-left"></i></button>
                <button v-if="gallery.length > 1" type="button" class="gallery-control gallery-control-next" aria-label="Next image" @click="nextImage"><i class="bi bi-chevron-right"></i></button>
            </div>
            <div v-if="gallery.length > 1" class="gallery-thumbs mb-4">
                <div
                    v-for="(img, i) in gallery"
                    :key="i"
                    class="gallery-thumb"
                    :class="{ 'is-active': i === activeImage }"
                    @click="activeImage = i"
                >
                    <img :src="img" :alt="`${package.title} photo ${i + 1}`" loading="lazy" />
                </div>
            </div>

            <div class="row g-5">
                <div class="col-lg-8">
                    <p class="section-eyebrow mb-1">{{ package.city?.name }}</p>
                    <h1 style="font-family: var(--font-display);">{{ package.title }}</h1>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <p class="text-muted mb-0"><i class="bi bi-calendar3 me-1"></i>{{ package.duration_days }} Days / {{ package.duration_nights }} Nights</p>
                        <StarRating v-if="package.approved_reviews_avg_rating" :rating="package.approved_reviews_avg_rating" />
                    </div>
<!--                    <p>{{ package.overview }}</p>-->

                    <div
                        class="tour-content"
                        v-html="package.overview"
                    ></div>

                    <!-- Spiritual significance -->
<!--                    <div class="shloka-quote-card my-4">
                        <p class="devanagari fs-5 mb-2">॥ वृन्दावनं परित्यज्य पादमेकं न गच्छति ॥</p>
                        <p class="mb-0 small">
                            This journey traces the pastimes of Shree Krishna through {{ package.city?.name || 'Braj' }} —
                            a route walked by pilgrims for centuries in search of the same darshan you're about to experience.
                        </p>
                    </div>-->

                    <template v-if="itinerary.length">
                        <h4 class="mt-4">Day-wise Itinerary</h4>
                        <ItineraryAccordion :days="itinerary" />
                    </template>

                    <div v-if="inclusions.length || exclusions.length" class="row mt-4">
                        <div v-if="inclusions.length" class="col-md-6">
                            <h5><i class="bi bi-check-circle-fill text-success me-1"></i>Inclusions</h5>
                            <ul>
                                <li v-for="(item, i) in inclusions" :key="i">{{ item }}</li>
                            </ul>
                        </div>
                        <div v-if="exclusions.length" class="col-md-6">
                            <h5><i class="bi bi-x-circle-fill text-danger me-1"></i>Exclusions</h5>
                            <ul>
                                <li v-for="(item, i) in exclusions" :key="i">{{ item }}</li>
                            </ul>
                        </div>
                    </div>

                    <section class="reviews-section mt-5">
                        <div class="reviews-heading">
                            <div><p class="section-eyebrow mb-1">Traveller experiences</p><h4 class="mb-0">Tour Reviews</h4></div>
                            <div class="review-summary">
                                <strong>{{ averageRating ? averageRating.toFixed(1) : '—' }}</strong>
                                <div><StarRating :rating="averageRating" /><span>{{ approvedReviewCount }} approved review{{ approvedReviewCount === 1 ? '' : 's' }}</span></div>
                            </div>
                        </div>

                        <div v-if="!reviews.data.length" class="review-empty">No approved reviews yet. Be the first to share your experience.</div>
                        <article v-for="review in reviews.data" :key="review.id" class="review-card">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div><strong>{{ review.reviewer_name }}</strong><div><StarRating :rating="review.rating" /></div></div>
                                <time class="text-muted small" :datetime="review.created_at">{{ formatReviewDate(review.created_at) }}</time>
                            </div>
                            <p class="mb-0 mt-3">{{ review.comment }}</p>
                        </article>

                        <nav v-if="reviews.links?.length > 3" class="d-flex flex-wrap gap-1 mt-3" aria-label="Review pages">
                            <template v-for="link in reviews.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="btn btn-sm" :class="link.active ? 'btn-svtp' : 'btn-outline-secondary'" preserve-scroll v-html="link.label" /><span v-else class="btn btn-sm btn-outline-secondary disabled" v-html="link.label"></span></template>
                        </nav>

                        <div class="review-form-card mt-4">
                            <h4 class="mb-1">Share your experience</h4>
                            <p class="text-muted small">No account is needed. Reviews are checked before appearing publicly.</p>
                            <form @submit.prevent="submitReview">
                                <div class="row g-3">
                                    <div class="col-md-6"><label for="review-name" class="form-label">Name</label><input id="review-name" v-model="reviewForm.name" class="form-control" maxlength="100" autocomplete="name" required><small class="text-danger">{{ reviewForm.errors.name }}</small></div>
                                    <div class="col-md-6"><label for="review-email" class="form-label">Email <span class="text-muted">(optional, not published)</span></label><input id="review-email" v-model="reviewForm.email" type="email" class="form-control" maxlength="255" autocomplete="email"><small class="text-danger">{{ reviewForm.errors.email }}</small></div>
                                    <div class="col-12">
                                        <fieldset><legend class="form-label mb-2">Rating</legend><div class="star-rating-input" @mouseleave="hoveredRating = 0">
                                            <button v-for="star in 5" :key="star" type="button" :aria-label="`${star} star${star === 1 ? '' : 's'}`" @mouseenter="hoveredRating = star" @focus="hoveredRating = star" @blur="hoveredRating = 0" @click="reviewForm.rating = star"><i class="bi" :class="star <= (hoveredRating || reviewForm.rating) ? 'bi-star-fill' : 'bi-star'"></i></button>
                                        </div></fieldset><small class="d-block text-danger">{{ reviewForm.errors.rating }}</small>
                                    </div>
                                    <div class="col-12"><label for="review-comment" class="form-label">Review</label><textarea id="review-comment" v-model="reviewForm.comment" class="form-control" rows="4" minlength="10" maxlength="2000" placeholder="Tell other travellers about your experience…" required></textarea><small class="text-danger">{{ reviewForm.errors.comment }}</small></div>
                                </div>
                                <button class="btn btn-svtp mt-3" :disabled="reviewForm.processing">{{ reviewForm.processing ? 'Submitting…' : 'Submit Review' }}</button>
                            </form>
                        </div>
                    </section>
                </div>

                <div class="col-lg-4">
                    <div class="glass-card p-4 booking-sticky">
                        <div class="d-flex align-items-baseline gap-2">
                            <span v-if="package.discounted_price" class="price-tag-strike">₹{{ package.price }}</span>
                            <p class="price-tag mb-0">₹{{ package.discounted_price || package.price }}</p>
                        </div>
                        <small class="text-muted">per person</small>

                        <hr />

                        <p class="fw-semibold small mb-2">Estimate your total</p>
                        <div class="price-calc-row">
                            <label class="mb-0 small">Adults</label>
                            <input v-model.number="adults" type="number" min="1" class="form-control form-control-sm" style="width: 80px;" />
                        </div>
                        <div class="price-calc-row">
                            <label class="mb-0 small">Children (under 12)</label>
                            <input v-model.number="children" type="number" min="0" class="form-control form-control-sm" style="width: 80px;" />
                        </div>
                        <div class="price-calc-row border-0 pt-3">
                            <span class="fw-semibold">Estimated Total</span>
                            <span class="price-tag fs-4 mb-0">₹{{ totalPrice.toLocaleString('en-IN') }}</span>
                        </div>

                        <button type="button" class="btn btn-svtp w-100 mt-3" @click="isTourEnquiryOpen = true">
                            <i class="bi bi-chat-square-text-fill me-1"></i>Enquire Now
                        </button>
                        <a href="https://wa.me/918923427393" target="_blank" rel="noopener" class="btn btn-outline-svtp w-100 mt-2">
                            <i class="bi bi-whatsapp me-1"></i>Ask on WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <TourPlanEnquiryModal
            :open="isTourEnquiryOpen"
            :security-question="securityQuestion"
            :tour-package="package"
            @close="isTourEnquiryOpen = false"
        />
        <div v-if="isGalleryOpen" class="gallery-lightbox" role="dialog" aria-modal="true" :aria-label="`${package.title} gallery`" @click.self="isGalleryOpen = false">
            <button type="button" class="gallery-lightbox-close" aria-label="Close gallery" @click="isGalleryOpen = false"><i class="bi bi-x-lg"></i></button>
            <button v-if="gallery.length > 1" type="button" class="gallery-control gallery-control-prev" aria-label="Previous image" @click="previousImage"><i class="bi bi-chevron-left"></i></button>
            <img :src="gallery[activeImage]" :alt="`${package.title} photo ${activeImage + 1}`" />
            <button v-if="gallery.length > 1" type="button" class="gallery-control gallery-control-next" aria-label="Next image" @click="nextImage"><i class="bi bi-chevron-right"></i></button>
        </div>
    </AppLayout>
</template>

<style scoped>
.reviews-heading { display: flex; align-items: end; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }.review-summary { display: flex; align-items: center; gap: .8rem; }.review-summary > strong { color: var(--maroon); font-family: var(--font-display); font-size: 2.5rem; line-height: 1; }.review-summary span { display: block; margin-top: .15rem; color: #6c757d; font-size: .78rem; }.review-card,.review-form-card,.review-empty { padding: 1.15rem; border: 1px solid rgba(107,16,41,.12); border-radius: 1rem; background: rgba(255,255,255,.7); }.review-card + .review-card { margin-top: .75rem; }.review-empty { color: #6c757d; }.star-rating-input { display: inline-flex; gap: .2rem; }.star-rating-input button { padding: .1rem; border: 0; background: transparent; color: #f59e0b; font-size: 1.8rem; line-height: 1; }.star-rating-input button:focus-visible { border-radius: .25rem; outline: 2px solid var(--maroon); outline-offset: 2px; }
@media (max-width: 575.98px) { .reviews-heading { align-items: flex-start; flex-direction: column; }.review-summary { width: 100%; }.review-form-card { padding: 1rem; } }

.tour-content h2,
.tour-content h3 {
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.tour-content p {
    line-height: 1.8;
}

.tour-content ul,
.tour-content ol {
    padding-left: 1.5rem;
}

</style>
