<script setup>
import HeroCarousel from '../HeroCarousel.vue';
import SearchWidget from '../SearchWidget.vue';

defineProps({
    banners: { type: Array, default: () => [] },
    destinations: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ({}) },
});
</script>

<template>
    <HeroCarousel :banners="banners">
        <template v-if="settings.show_search !== false" #search>
            <SearchWidget :destinations="destinations" />
        </template>
    </HeroCarousel>
</template>
