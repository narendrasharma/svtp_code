<script setup>
import { computed } from 'vue';
import Container from '../Layout/Container.vue';
import SectionHeading from '../UI/SectionHeading.vue';
import DestinationCard from '../Cards/DestinationCard.vue';
import { useLocalization } from '../../../i18n';

const props = defineProps({ section: { type: Object, required: true } });
const { t } = useLocalization();
const itemCount = computed(() => props.section.items?.length || 0);
</script>

<template>
    <section class="public-section homepage-section homepage-destinations-section">
        <Container size="wide">
            <SectionHeading :title="section.title || t('common.featured_destinations', 'Featured destinations')" :description="section.subtitle" variant="editorial" />
            <div class="homepage-destination-mosaic" :class="`homepage-destination-mosaic--count-${itemCount <= 1 ? 1 : itemCount === 2 ? 2 : itemCount <= 4 ? 4 : 5}`">
                <DestinationCard v-for="(destination, index) in section.items" :key="destination.id" :destination="destination" :feature="index === 0" />
            </div>
        </Container>
    </section>
</template>
