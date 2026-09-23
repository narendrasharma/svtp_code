<script setup>
import Container from '../Layout/Container.vue';
import SectionHeading from '../UI/SectionHeading.vue';
import DestinationCard from '../Cards/DestinationCard.vue';
import { useLocalization } from '../../../i18n';

defineProps({ section: { type: Object, required: true } });
const { t } = useLocalization();
</script>

<template>
    <section class="public-section homepage-section homepage-cities-section">
        <Container>
            <SectionHeading :title="section.title || t('common.featured_cities', 'Featured cities')" :description="section.subtitle" variant="minimal" />
            <div class="homepage-city-strip">
                <DestinationCard v-for="city in section.items" :key="city.id" :destination="city" />
            </div>
        </Container>
    </section>
</template>
