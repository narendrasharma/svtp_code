<script setup>
import { useLocalization } from '../../i18n';

defineProps({ review: { type: Object, required: true } });

const { locale, t } = useLocalization();

function formatDate(value) {
    if (!value) return '';

    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { dateStyle: 'medium' }).format(new Date(`${value}T12:00:00`));
    } catch {
        return value;
    }
}
</script>

<template>
    <article class="hotel-customer-review-card">
        <div class="hotel-customer-review-card__topline">
            <div>
                <strong>{{ review.customer_name || t('common.you', 'You') }}</strong>
                <span v-if="review.verified_stay" class="hotel-customer-review-card__verified"><i class="bi bi-patch-check" aria-hidden="true"></i>{{ t('common.verified_stay', 'Verified stay') }}</span>
            </div>
            <div class="hotel-customer-review-card__rating">
                <span :aria-label="`${review.overall_rating} out of 5 stars`" aria-hidden="true">{{ '★'.repeat(review.overall_rating) }}{{ '☆'.repeat(5 - review.overall_rating) }}</span>
                <time v-if="review.review_date" :datetime="review.review_date">{{ formatDate(review.review_date) }}</time>
            </div>
        </div>
        <h3 v-if="review.title">{{ review.title }}</h3>
        <p>{{ review.comment }}</p>
        <div v-if="review.vendor_reply" class="hotel-customer-review-card__reply">
            <strong><i class="bi bi-buildings" aria-hidden="true"></i>{{ t('common.property_response', 'Property response') }}</strong>
            <time v-if="review.vendor_reply.date" :datetime="review.vendor_reply.date">{{ formatDate(review.vendor_reply.date) }}</time>
            <p>{{ review.vendor_reply.comment }}</p>
        </div>
    </article>
</template>
