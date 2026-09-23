<script setup>
import { appUrl } from '../../appUrl';
import { galleryImages } from '../../festiveAssets';

defineProps({
    settings: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section class="container py-5">
        <p class="section-eyebrow text-center">फोटो गैलरी</p>
        <h2 class="section-title text-center mb-2">{{ settings.heading || 'Moments from the journey' }}</h2>
        <p class="text-muted text-center mb-5">A glimpse of the places and experiences a journey can include.</p>
        <div class="gallery-grid">
            <a v-for="(img, i) in galleryImages" :key="i" :href="appUrl('/gallery')">
                <img :src="img" :alt="`Gallery photo ${i + 1}`" loading="lazy" />
            </a>
        </div>
    </section>
</template>
