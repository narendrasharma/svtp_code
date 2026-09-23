<script setup>
import { computed } from 'vue';
import CustomCtaSection from './CustomCtaSection.vue';
import FeaturedCitiesSection from './FeaturedCitiesSection.vue';
import FeaturedDestinationsSection from './FeaturedDestinationsSection.vue';
import FeaturedHotelsSection from './FeaturedHotelsSection.vue';
import FeaturedPlacesSection from './FeaturedPlacesSection.vue';
import FeaturedToursSection from './FeaturedToursSection.vue';
import HeroSearchSection from './HeroSearchSection.vue';
import TopRatedHotelsSection from './TopRatedHotelsSection.vue';

const SECTION_COMPONENTS = Object.freeze({
    hero_search: HeroSearchSection,
    featured_cities: FeaturedCitiesSection,
    featured_destinations: FeaturedDestinationsSection,
    featured_hotels: FeaturedHotelsSection,
    top_rated_hotels: TopRatedHotelsSection,
    featured_tours: FeaturedToursSection,
    featured_places: FeaturedPlacesSection,
    custom_cta: CustomCtaSection,
});

const props = defineProps({
    sections: { type: Array, default: () => [] },
});

const renderableSections = computed(() => props.sections
    .map((section) => ({ section, component: SECTION_COMPONENTS[section?.type] }))
    .filter(({ section, component }) => {
        if (!component) return false;
        if (['hero_search', 'custom_cta'].includes(section.type)) return true;
        return Array.isArray(section.items) && section.items.length > 0;
    }));

defineExpose({ SECTION_COMPONENTS });
</script>

<template>
    <component
        :is="entry.component"
        v-for="entry in renderableSections"
        :key="entry.section.key || entry.section.type"
        :section="entry.section"
    />
</template>
