<script setup>
import { router } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';
import RatingDisplay from '../UI/RatingDisplay.vue';

const props = defineProps({
    summary: { type: Object, default: null },
    reviews: { type: Object, default: null },
    categories: { type: Object, default: () => ({}) },
    sort: { type: String, default: 'recent' },
    propertySlug: { type: String, required: true },
    stay: { type: Object, required: true },
});

const { t } = useLocalization();

function percentage(rating) {
    return props.summary?.reviews_count ? (props.summary.distribution[rating] / props.summary.reviews_count) * 100 : 0;
}

function goTo(page, sort = props.sort) {
    router.get(appUrl('/hotels/' + props.propertySlug), { ...props.stay, review_sort: sort, reviews_page: page }, { preserveScroll: true, preserveState: false });
}

function changeSort(event) {
    goTo(1, event.target.value);
}
</script>

<template>
    <section id="reviews" class="property-reviews" aria-labelledby="property-reviews-heading">
        <div class="property-section-heading">
            <span class="public-eyebrow">{{ t('common.guest_feedback', 'Guest feedback') }}</span>
            <h2 id="property-reviews-heading">{{ t('common.guest_reviews', 'Guest reviews') }}</h2>
            <p>{{ t('common.approved_reviews_description', 'Published feedback from guests with a completed stay.') }}</p>
        </div>

        <div v-if="summary?.reviews_count" class="property-review-summary">
            <div class="property-review-summary__score">
                <strong>{{ Number(summary.rating_average).toFixed(1) }}</strong>
                <RatingDisplay :rating="summary.rating_average" :review-count="summary.reviews_count" />
                <span>{{ summary.reviews_count }} {{ summary.reviews_count === 1 ? t('common.review', 'review') : t('common.reviews', 'reviews') }}</span>
            </div>
            <div class="property-review-summary__distribution" aria-label="Rating distribution">
                <div v-for="rating in [5, 4, 3, 2, 1]" :key="rating" class="property-review-bar">
                    <span>{{ rating }}</span>
                    <div class="property-review-bar__track" role="progressbar" :aria-valuenow="summary.distribution[rating]" :aria-valuemin="0" :aria-valuemax="summary.reviews_count">
                        <span :style="{ width: percentage(rating) + '%' }"></span>
                    </div>
                    <small>{{ summary.distribution[rating] }}</small>
                </div>
            </div>
            <div v-if="Object.keys(categories).length" class="property-review-summary__categories">
                <div v-for="(label, key) in categories" :key="key"><span>{{ label }}</span><strong>{{ summary.category_averages[key] || '—' }}</strong></div>
            </div>
        </div>
        <div v-else class="property-reviews__empty"><i class="bi bi-chat-square-heart" aria-hidden="true"></i><p>{{ t('common.no_reviews_yet', 'No published reviews yet.') }}</p></div>

        <template v-if="summary?.reviews_count && reviews">
            <div class="property-reviews__toolbar">
                <span>{{ t('common.showing_reviews', 'Showing published guest feedback') }}</span>
                <label class="property-review-sort"><span>{{ t('common.sort_reviews', 'Sort reviews') }}</span><select :value="sort" class="public-select-input" @change="changeSort"><option value="recent">{{ t('common.most_recent', 'Most recent') }}</option><option value="highest">{{ t('common.highest_rating', 'Highest rating') }}</option><option value="lowest">{{ t('common.lowest_rating', 'Lowest rating') }}</option></select></label>
            </div>
            <div class="property-review-list">
                <article v-for="review in reviews.data" :key="review.id" class="property-review-card">
                    <div class="property-review-card__top">
                        <div><strong>{{ review.customer_name }}</strong><span v-if="review.verified_stay" class="property-review-card__verified"><i class="bi bi-patch-check" aria-hidden="true"></i>{{ t('common.verified_stay', 'Verified stay') }}</span></div>
                        <div class="property-review-card__rating"><span aria-hidden="true">{{ '★'.repeat(review.overall_rating) }}{{ '☆'.repeat(5 - review.overall_rating) }}</span><time v-if="review.review_date" :datetime="review.review_date">{{ review.review_date }}</time></div>
                    </div>
                    <h3 v-if="review.title">{{ review.title }}</h3>
                    <p>{{ review.comment }}</p>
                    <div v-if="review.vendor_reply" class="property-review-card__reply"><strong><i class="bi bi-buildings" aria-hidden="true"></i>{{ t('common.property_response', 'Property response') }}</strong><p>{{ review.vendor_reply.comment }}</p></div>
                </article>
            </div>
            <div v-if="reviews.last_page > 1" class="property-review-pagination">
                <button v-if="reviews.current_page > 1" type="button" class="public-button public-button--outline public-button--sm" @click="goTo(reviews.current_page - 1)"><i class="bi bi-arrow-left" data-dir-icon="arrow" aria-hidden="true"></i>{{ t('common.previous', 'Previous') }}</button>
                <span>{{ reviews.current_page }} / {{ reviews.last_page }}</span>
                <button v-if="reviews.current_page < reviews.last_page" type="button" class="public-button public-button--outline public-button--sm" @click="goTo(reviews.current_page + 1)">{{ t('common.next', 'Next') }}<i class="bi bi-arrow-right" data-dir-icon="arrow" aria-hidden="true"></i></button>
            </div>
        </template>
    </section>
</template>
