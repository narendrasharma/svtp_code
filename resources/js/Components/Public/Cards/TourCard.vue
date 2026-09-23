<script setup>
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import ImageWithFallback from '../Media/ImageWithFallback.vue';
import MoneyDisplay from '../UI/MoneyDisplay.vue';
import RatingDisplay from '../UI/RatingDisplay.vue';
import Badge from '../UI/Badge.vue';
import { durationLabel, mediaUrl } from '../homepage';
import { useLocalization } from '../../../i18n';

defineProps({
    tour: { type: Object, required: true },
    featured: { type: Boolean, default: false },
});

const { t } = useLocalization();
</script>

<template>
    <Link :href="appUrl(tour.url)" class="homepage-tour-card" :class="{ 'homepage-tour-card--featured': featured }">
        <div class="homepage-tour-card__media">
            <ImageWithFallback :src="mediaUrl(tour.image)" :alt="tour.title" aspect="editorial" kind="tour" :label="tour.destination || 'Tour'" />
            <Badge v-if="durationLabel(tour, t)" variant="accent" class="homepage-tour-card__duration">
                <i class="bi bi-clock" aria-hidden="true"></i>{{ durationLabel(tour, t) }}
            </Badge>
        </div>
        <div class="homepage-tour-card__body">
            <p v-if="tour.destination" class="homepage-card-kicker">{{ tour.destination }}</p>
            <h3>{{ tour.title }}</h3>
            <div class="homepage-tour-card__footer">
                <RatingDisplay v-if="tour.rating_average" :rating="tour.rating_average" :review-count="tour.reviews_count" />
                <span v-else class="homepage-card-placeholder"></span>
                <MoneyDisplay :money="tour.display_money" />
            </div>
        </div>
    </Link>
</template>
