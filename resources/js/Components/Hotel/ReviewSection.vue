<script setup>
import { router } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import Pagination from '../Pagination.vue';
import ReviewCard from './ReviewCard.vue';

const props = defineProps({ summary: Object, reviews: Object, categories: Object, sort: String, propertySlug: String });

function sortReviews(event) {
    router.get(appUrl(`/hotels/${props.propertySlug}`), { review_sort: event.target.value }, {
        preserveState: true,
        preserveScroll: true,
        only: ['reviews', 'reviewSort', 'reviewSummary', 'seo'],
    });
}

function percentage(rating) {
    return props.summary.reviews_count ? (props.summary.distribution[rating] / props.summary.reviews_count) * 100 : 0;
}
</script>

<template>
    <section id="reviews" class="d-flex flex-column gap-3 mt-4 hotel-reviews" aria-labelledby="hotel-review-heading">
        <div>
            <h2 id="hotel-review-heading" class="h3 mb-1">Guest reviews</h2>
            <p class="text-muted small mb-0">Feedback from guests with a completed stay.</p>
        </div>
        <div class="card p-3 p-md-4">
            <div v-if="summary.reviews_count" class="row g-4">
                <div class="col-sm-4">
                    <div class="display-5 fw-semibold">{{ Number(summary.rating_average).toFixed(1) }}<span class="fs-5 text-muted"> / 5</span></div>
                    <p class="small text-muted mb-0">{{ summary.reviews_count }} {{ summary.reviews_count === 1 ? 'review' : 'reviews' }}</p>
                </div>
                <div class="col-sm-8 d-flex flex-column gap-2" aria-label="Rating distribution">
                    <div v-for="rating in [5, 4, 3, 2, 1]" :key="rating" class="d-flex align-items-center gap-2 small">
                        <span class="text-nowrap">{{ rating }} <span class="text-warning" aria-label="stars">★</span></span>
                        <div class="progress flex-grow-1" role="progressbar" :aria-label="`${rating} star reviews`" :aria-valuenow="summary.distribution[rating]" :aria-valuemin="0" :aria-valuemax="summary.reviews_count">
                            <div class="progress-bar bg-warning" :style="{ width: `${percentage(rating)}%` }"></div>
                        </div>
                        <span class="text-muted review-distribution-count">{{ summary.distribution[rating] }}</span>
                    </div>
                </div>
                <div class="col-12"><div class="row g-3">
                    <div v-for="(label, key) in categories" :key="key" class="col-6 col-md-4">
                        <div class="d-flex justify-content-between gap-2 small"><span>{{ label }}</span><strong>{{ summary.category_averages[key] }}</strong></div>
                    </div>
                </div></div>
            </div>
            <p v-else class="text-muted mb-0">No published reviews yet. After your stay is completed, you can share your experience from My Hotel Bookings.</p>
        </div>
        <template v-if="summary.reviews_count">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <p class="small text-muted mb-0">Showing {{ reviews.from ?? 0 }}–{{ reviews.to ?? 0 }} of {{ reviews.total }}</p>
                <div class="d-flex align-items-center gap-2">
                    <label for="hotel-review-sort" class="small text-nowrap">Sort reviews</label>
                    <select id="hotel-review-sort" :value="sort" class="form-select form-select-sm" @change="sortReviews">
                        <option value="recent">Most recent</option>
                        <option value="highest">Highest rating</option>
                        <option value="lowest">Lowest rating</option>
                    </select>
                </div>
            </div>
            <ReviewCard v-for="review in reviews.data" :key="review.id" :review="review" />
            <p v-if="!reviews.data.length" class="text-muted">There are no reviews on this page. Choose another page below.</p>
            <Pagination :links="reviews.links" />
        </template>
    </section>
</template>

<style scoped>
.hotel-reviews { scroll-margin-top: 5rem; }
.review-distribution-count { min-width: 2rem; text-align: right; }
.hotel-reviews :deep(.pagination) { flex-wrap: wrap; }
</style>
