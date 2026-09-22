<script setup>
defineProps({ review: { type: Object, required: true } });
</script>

<template>
    <article class="card p-3 p-md-4 hotel-review-card">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div class="d-flex flex-column gap-1">
                <strong>{{ review.customer_name }}</strong>
                <span v-if="review.verified_stay" class="small text-success"><i class="bi bi-patch-check me-1" aria-hidden="true"></i>Verified stay</span>
            </div>
            <div class="text-end">
                <span class="text-warning" :aria-label="`${review.overall_rating} out of 5 stars`">{{ '★'.repeat(review.overall_rating) }}{{ '☆'.repeat(5 - review.overall_rating) }}</span>
                <div v-if="review.review_date" class="small text-muted"><time :datetime="review.review_date">{{ review.review_date }}</time></div>
            </div>
        </div>
        <h3 v-if="review.title" class="h6 mt-3 mb-2">{{ review.title }}</h3>
        <p class="review-text mt-2 mb-0">{{ review.comment }}</p>
        <div v-if="review.vendor_reply" class="border-start border-3 rounded p-3 mt-3">
            <div class="d-flex flex-wrap justify-content-between gap-2 small">
                <strong><i class="bi bi-buildings me-1" aria-hidden="true"></i>Property response</strong>
                <time v-if="review.vendor_reply.date" class="text-muted" :datetime="review.vendor_reply.date">{{ review.vendor_reply.date }}</time>
            </div>
            <p class="review-text small mb-0 mt-2">{{ review.vendor_reply.comment }}</p>
        </div>
    </article>
</template>

<style scoped>
.hotel-review-card { min-width: 0; }
.review-text { white-space: pre-line; overflow-wrap: anywhere; }
</style>
