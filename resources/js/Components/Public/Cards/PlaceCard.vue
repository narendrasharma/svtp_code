<script setup>
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import ImageWithFallback from '../Media/ImageWithFallback.vue';
import { mediaUrl } from '../homepage';

defineProps({
    place: { type: Object, required: true },
    featured: { type: Boolean, default: false },
});
</script>

<template>
    <Link :href="appUrl(place.url)" class="homepage-place-card" :class="{ 'homepage-place-card--featured': featured }">
        <ImageWithFallback :src="mediaUrl(place.image)" :alt="place.name" aspect="portrait" kind="place" :label="place.subtitle || place.city || 'Place'" />
        <span class="homepage-place-card__copy">
            <span class="homepage-card-kicker">{{ place.subtitle || place.city }}</span>
            <strong>{{ place.name }}</strong>
        </span>
    </Link>
</template>
