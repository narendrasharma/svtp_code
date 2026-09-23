<script setup>
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import ImageWithFallback from '../Media/ImageWithFallback.vue';
import MoneyDisplay from '../UI/MoneyDisplay.vue';
import RatingDisplay from '../UI/RatingDisplay.vue';
import Badge from '../UI/Badge.vue';
import { mediaUrl } from '../homepage';
import { useLocalization } from '../../../i18n';

defineProps({
    hotel: { type: Object, required: true },
    compact: { type: Boolean, default: false },
    featured: { type: Boolean, default: false },
});

const { t } = useLocalization();
</script>

<template>
    <Link :href="appUrl(hotel.url)" class="homepage-hotel-card" :class="{ 'homepage-hotel-card--compact': compact, 'homepage-hotel-card--featured': featured }">
        <ImageWithFallback :src="mediaUrl(hotel.image)" :alt="hotel.name" aspect="editorial" kind="hotel" :label="hotel.property_type || 'Stay'" />
        <div class="homepage-hotel-card__body">
            <div class="homepage-card-meta">
                <Badge v-if="hotel.property_type" variant="neutral">{{ hotel.property_type }}</Badge>
                <span v-if="hotel.star_rating" class="homepage-card-stars" :aria-label="`${hotel.star_rating} ${t('common.stars', 'stars')}`">
                    <i v-for="star in hotel.star_rating" :key="star" class="bi bi-star-fill" aria-hidden="true"></i>
                </span>
            </div>
            <h3>{{ hotel.name }}</h3>
            <p v-if="hotel.location" class="homepage-card-location">
                <i class="bi bi-geo-alt" aria-hidden="true"></i>{{ hotel.location }}
            </p>
            <div class="homepage-hotel-card__footer">
                <RatingDisplay :rating="hotel.rating_average" :review-count="hotel.reviews_count" />
                <div v-if="hotel.display_money" class="homepage-price">
                    <span class="homepage-price__prefix">{{ t('common.from', 'From') }}</span>
                    <MoneyDisplay :money="hotel.display_money" />
                </div>
            </div>
            <span v-if="featured" class="homepage-card-action">{{ t('common.view_details', 'View details') }} <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i></span>
        </div>
    </Link>
</template>
