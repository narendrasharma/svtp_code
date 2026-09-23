<script setup>
import { computed } from 'vue';
import Container from '../Layout/Container.vue';
import SectionHeading from '../UI/SectionHeading.vue';
import TourCard from '../Cards/TourCard.vue';
import { useLocalization } from '../../../i18n';

const props = defineProps({ section: { type: Object, required: true } });
const { t } = useLocalization();
const itemCount = computed(() => props.section.items?.length || 0);
</script>

<template>
    <section class="public-section homepage-section homepage-tours-section">
        <Container>
            <SectionHeading :title="section.title || t('common.featured_tours', 'Featured tours')" :description="section.subtitle" variant="editorial" />
            <div class="homepage-tour-grid" :class="`homepage-tour-grid--count-${Math.min(itemCount, 5)}`">
                <TourCard v-for="(tour, index) in section.items" :key="tour.id" :tour="tour" :featured="index === 0 && itemCount > 1" />
            </div>
        </Container>
    </section>
</template>
