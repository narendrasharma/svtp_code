<script setup>
import Container from '../Layout/Container.vue';
import SectionHeading from '../UI/SectionHeading.vue';
import HotelCard from '../Cards/HotelCard.vue';
import { useLocalization } from '../../../i18n';

defineProps({ section: { type: Object, required: true } });
const { t } = useLocalization();
</script>

<template>
    <section class="public-section public-section--compact homepage-section homepage-rated-section">
        <Container>
            <SectionHeading :title="section.title || t('common.top_rated_hotels', 'Top rated hotels')" :description="section.subtitle" variant="minimal" />
            <div class="homepage-rated-layout">
                <div class="homepage-rated-lead">
                    <span class="public-eyebrow">{{ t('common.guest_favourites', 'Guest favourites') }}</span>
                    <p>{{ t('common.approved_ratings', 'A considered shortlist shaped by approved guest ratings.') }}</p>
                </div>
                <div class="homepage-rated-list">
                    <HotelCard v-for="hotel in section.items" :key="hotel.id" :hotel="hotel" compact />
                </div>
            </div>
        </Container>
    </section>
</template>
