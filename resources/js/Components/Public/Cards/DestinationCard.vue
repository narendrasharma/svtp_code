<script setup>
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import ImageWithFallback from '../Media/ImageWithFallback.vue';
import { mediaUrl } from '../homepage';
import { useLocalization } from '../../../i18n';

defineProps({
    destination: { type: Object, required: true },
    feature: { type: Boolean, default: false },
});

const { t } = useLocalization();
</script>

<template>
    <Link :href="appUrl(destination.url)" class="homepage-destination-card" :class="{ 'homepage-destination-card--feature': feature }">
        <ImageWithFallback :src="mediaUrl(destination.image)" :alt="destination.name" aspect="editorial" kind="destination" :label="destination.subtitle || 'Destination'" />
        <span class="homepage-destination-card__overlay">
            <span v-if="destination.subtitle" class="homepage-card-kicker">{{ destination.subtitle }}</span>
            <strong>{{ destination.name }}</strong>
            <span v-if="destination.property_count || destination.tour_count" class="homepage-destination-card__context">
                <span v-if="destination.property_count">{{ destination.property_count }} {{ destination.property_count === 1 ? t('common.stay', 'stay') : t('common.stays', 'stays') }}</span>
                <span v-if="destination.property_count && destination.tour_count" aria-hidden="true"> · </span>
                <span v-if="destination.tour_count">{{ destination.tour_count }} {{ destination.tour_count === 1 ? t('common.tour', 'tour') : t('common.tours', 'tours') }}</span>
            </span>
        </span>
    </Link>
</template>
