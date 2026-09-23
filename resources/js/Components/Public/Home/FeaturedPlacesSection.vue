<script setup>
import { computed } from 'vue';
import Container from '../Layout/Container.vue';
import SectionHeading from '../UI/SectionHeading.vue';
import PlaceCard from '../Cards/PlaceCard.vue';
import { useLocalization } from '../../../i18n';

const props = defineProps({ section: { type: Object, required: true } });
const { t } = useLocalization();
const itemCount = computed(() => props.section.items?.length || 0);
</script>

<template>
    <section class="public-section homepage-section homepage-places-section">
        <Container size="wide">
            <SectionHeading :title="section.title || t('common.featured_places', 'Featured places')" :description="section.subtitle" variant="heritage" />
            <div class="homepage-place-gallery" :class="`homepage-place-gallery--count-${itemCount <= 1 ? 1 : itemCount === 2 ? 2 : itemCount <= 4 ? 4 : 5}`">
                <PlaceCard v-for="(place, index) in section.items" :key="place.id" :place="place" :featured="index === 0" />
            </div>
        </Container>
    </section>
</template>
