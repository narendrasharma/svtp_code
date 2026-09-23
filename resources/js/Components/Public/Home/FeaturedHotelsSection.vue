<script setup>
import { computed } from 'vue';
import Container from '../Layout/Container.vue';
import SectionHeading from '../UI/SectionHeading.vue';
import HotelCard from '../Cards/HotelCard.vue';
import { useLocalization } from '../../../i18n';

const props = defineProps({ section: { type: Object, required: true } });
const { t } = useLocalization();
const itemCount = computed(() => props.section.items?.length || 0);
</script>

<template>
    <section class="public-section homepage-section homepage-hotels-section">
        <Container>
            <SectionHeading :title="section.title || t('common.featured_hotels', 'Featured hotels')" :description="section.subtitle" variant="hairline" />
            <div class="homepage-hotel-grid" :class="`homepage-hotel-grid--count-${Math.min(itemCount, 4)}`">
                <HotelCard v-for="hotel in section.items" :key="hotel.id" :hotel="hotel" :featured="itemCount === 1" />
            </div>
        </Container>
    </section>
</template>
