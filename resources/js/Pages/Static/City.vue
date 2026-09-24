<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';
import SeoHead from '../../Components/SeoHead.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import DestinationCard from '../../Components/Public/Cards/DestinationCard.vue';
import PlaceCard from '../../Components/Public/Cards/PlaceCard.vue';
import HotelCard from '../../Components/Public/Cards/HotelCard.vue';
import TourCard from '../../Components/Public/Cards/TourCard.vue';
import SectionHeading from '../../Components/Public/UI/SectionHeading.vue';
import { mediaUrl } from '../../Components/Public/homepage';
import { useLocalization } from '../../i18n';

const props = defineProps({ city: { type: Object, required: true }, seo: { type: Object, default: () => ({}) } });
const { t } = useLocalization();
const cityImage = computed(() => mediaUrl(props.city.image));
const destinations = computed(() => (props.city.destinations || []).map((item) => ({ ...item, url: item.url || `/destinations/${item.slug}` })));
const places = computed(() => (props.city.places || []).map((item) => ({ ...item, url: item.url || `/places/${item.slug}`, subtitle: item.subtitle || props.city.name })));
const geographyLabel = computed(() => [props.city.geography?.state, props.city.geography?.country].filter(Boolean).join(' · '));
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="city.seo?.title || seo.title || `${city.name} — ${t('common.city', 'City')}`"
            :description="city.seo?.description || seo.description || `${t('common.explore_this_city', 'Explore this city')} — ${city.name}.`"
            :image="city.image"
            :canonical="city.seo?.canonical || seo.canonical"
            :noindex="city.seo?.noindex === true"
        />

        <main class="geo-detail-page">
            <section class="geo-city-hero">
                <div class="geo-city-hero__media"><ImageWithFallback :src="cityImage" :alt="city.name" aspect="editorial" kind="destination" :label="t('common.city', 'City')" loading="eager" /></div>
                <div class="geo-city-hero__shade"></div>
                <div class="public-container geo-city-hero__content">
                    <nav class="geo-breadcrumbs" aria-label="Breadcrumb">
                        <Link :href="appUrl('/')">{{ t('common.home', 'Home') }}</Link>
                        <span aria-hidden="true">/</span>
                        <span aria-current="page">{{ city.name }}</span>
                    </nav>
                    <p class="public-eyebrow">{{ t('common.city', 'City') }}<span v-if="geographyLabel"> · {{ geographyLabel }}</span></p>
                    <h1>{{ city.name }}</h1>
                    <p>{{ t('common.explore_this_city_description', 'Explore destinations, places, stays, and tours connected to this city.') }}</p>
                </div>
            </section>

            <section v-if="destinations.length" class="public-container geo-section">
                <SectionHeading :eyebrow="t('common.destinations', 'Destinations')" :title="t('common.destinations_in_city', 'Destinations in this city')" :description="t('common.destinations_in_city_description', 'Travel concepts and areas connected to the city.')" />
                <div class="geo-city-destination-grid">
                    <DestinationCard v-for="destination in destinations" :key="destination.id" :destination="destination" />
                </div>
            </section>

            <section v-if="places.length" class="public-container geo-section geo-section--places">
                <SectionHeading :eyebrow="t('common.places_to_explore', 'Places to explore')" :title="t('common.places_in_city', 'Places to visit in this city')" />
                <div class="geo-city-place-grid">
                    <PlaceCard v-for="place in places" :key="place.id" :place="place" />
                </div>
            </section>

            <section v-if="city.hotels?.length" class="geo-commercial-section">
                <div class="public-container geo-section">
                    <SectionHeading :eyebrow="t('common.stays', 'Stays')" :title="t('common.stays_in_city', 'Stays in this city')" />
                    <div class="geo-commercial-grid"><HotelCard v-for="hotel in city.hotels" :key="hotel.id" :hotel="hotel" compact /></div>
                </div>
            </section>

            <section v-if="city.tours?.length" class="public-container geo-section">
                <SectionHeading :eyebrow="t('common.tours', 'Tours')" :title="t('common.tours_in_city', 'Tours in this city')" />
                <div class="geo-commercial-grid"><TourCard v-for="tour in city.tours" :key="tour.id" :tour="tour" /></div>
            </section>

            <section v-if="!destinations.length && !places.length && !city.hotels?.length && !city.tours?.length" class="public-container geo-empty-state">
                <i class="bi bi-building" aria-hidden="true"></i>
                <h2>{{ t('common.city_discovery_coming_soon', 'City discovery is taking shape') }}</h2>
                <p>{{ t('common.city_discovery_empty_description', 'Published destinations and places will appear here as this city guide grows.') }}</p>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
.geo-detail-page { background: var(--public-surface, var(--cream)); }
.geo-city-hero { position: relative; display: grid; min-height: min(62vh, 42rem); align-items: end; overflow: hidden; color: #fff; background: var(--public-ink, var(--maroon-deep)); }
.geo-city-hero__media, .geo-city-hero__shade { position: absolute; inset: 0; }
.geo-city-hero__media :deep(.public-media-frame) { width: 100%; height: 100%; min-height: 100%; }
.geo-city-hero__media :deep(.public-media-frame > img) { width: 100%; height: 100%; object-fit: cover; }
.geo-city-hero__shade { background: linear-gradient(180deg, rgba(15, 11, 20, .14), rgba(15, 11, 20, .86)); }
.geo-city-hero__content { position: relative; z-index: 1; padding-block: 2rem clamp(3.5rem, 8vw, 6.5rem); }
.geo-city-hero h1 { margin: 0 0 .8rem; color: #fff; font-size: clamp(3.2rem, 9vw, 7rem); line-height: .92; }
.geo-city-hero p:last-child { max-width: 52ch; margin: 0; color: rgba(255,255,255,.82); line-height: 1.7; }
.geo-breadcrumbs { display: flex; flex-wrap: wrap; gap: .55rem; align-items: center; margin-bottom: 2rem; color: rgba(255,255,255,.7); font-size: .78rem; }
.geo-breadcrumbs a { color: #fff; }
.geo-section { padding-block: clamp(3.5rem, 7vw, 6rem); }
.geo-city-destination-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; margin-top: 2.5rem; }
.geo-city-place-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-top: 2.5rem; }
.geo-city-place-grid :deep(.homepage-place-card) { min-height: 17rem; }
.geo-section--places { padding-top: 0; }
.geo-commercial-section { background: var(--public-surface-alt, var(--cream-warm)); }
.geo-commercial-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.2rem; margin-top: 2.5rem; }
.geo-empty-state { max-width: 38rem; margin-block: 3rem 6rem; padding: 3.5rem 1.5rem; text-align: center; border: 1px dashed var(--public-border-strong, #d7c9bd); border-radius: 1rem; }
.geo-empty-state i { color: var(--public-brand, var(--maroon)); font-size: 2.2rem; }
.geo-empty-state h2 { margin: 1rem 0 .5rem; font-size: 1.6rem; }
.geo-empty-state p { margin: 0; color: var(--public-muted, #756c68); }
@media (max-width: 991px) { .geo-city-destination-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .geo-city-place-grid, .geo-commercial-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 575px) { .geo-city-destination-grid, .geo-city-place-grid, .geo-commercial-grid { grid-template-columns: 1fr; } }
</style>
