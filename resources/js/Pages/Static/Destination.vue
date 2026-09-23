<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';
import SeoHead from '../../Components/SeoHead.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import PlaceCard from '../../Components/Public/Cards/PlaceCard.vue';
import HotelCard from '../../Components/Public/Cards/HotelCard.vue';
import TourCard from '../../Components/Public/Cards/TourCard.vue';
import SectionHeading from '../../Components/Public/UI/SectionHeading.vue';
import { mediaUrl } from '../../Components/Public/homepage';
import { useLocalization } from '../../i18n';

const props = defineProps({
    destination: { type: Object, required: true },
    landing: { type: Object, default: null },
    seo: { type: Object, default: () => ({}) },
});

const { t } = useLocalization();
const data = computed(() => props.landing || {
    name: props.destination.display_name || props.destination.name,
    description: props.destination.display_description || props.destination.description,
    image: props.destination.image,
    geography: { city: props.destination.city?.name, state: props.destination.state, country: props.destination.country },
    places: props.destination.places || [],
    hotels: [],
    tours: [],
    seo: props.seo,
});
const heroImage = computed(() => mediaUrl(data.value.image));
const geographyLabel = computed(() => Object.values(data.value.geography || {}).filter(Boolean).join(' · '));
const placeItems = computed(() => (data.value.places || []).map((place) => ({
    ...place,
    name: place.name || place.display_name,
    subtitle: data.value.name,
    url: place.url || `/places/${place.slug}`,
})));

function destinationUrl(slug) {
    return appUrl(`/destinations/${slug}`);
}
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="data.seo?.title || `${data.name} — ${t('common.destination', 'Destination')}`"
            :description="data.seo?.description || data.description || t('common.destination_guide_description', 'Explore this destination and the places and experiences it connects.')"
            :image="data.image"
            :canonical="data.seo?.canonical"
            :noindex="data.seo?.noindex === true"
        />

        <main class="geo-detail-page">
            <section class="geo-detail-hero">
                <div class="geo-detail-hero__media"><ImageWithFallback :src="heroImage" :alt="data.name" aspect="editorial" kind="destination" :label="t('common.destination', 'Destination')" loading="eager" /></div>
                <div class="geo-detail-hero__shade"></div>
                <div class="container geo-detail-hero__content">
                    <nav class="geo-breadcrumbs" aria-label="Breadcrumb">
                        <Link :href="appUrl('/')">{{ t('common.home', 'Home') }}</Link>
                        <span aria-hidden="true">/</span>
                        <Link :href="appUrl('/destinations')">{{ t('common.destinations', 'Destinations') }}</Link>
                        <span aria-hidden="true">/</span>
                        <span aria-current="page">{{ data.name }}</span>
                    </nav>
                    <p v-if="geographyLabel" class="public-eyebrow">{{ geographyLabel }}</p>
                    <h1>{{ data.name }}</h1>
                    <p v-if="data.description" class="geo-detail-hero__lede">{{ data.description }}</p>
                </div>
            </section>

            <section v-if="data.description" class="container geo-editorial-intro">
                <div>
                    <p class="public-eyebrow">{{ t('common.about', 'About') }}</p>
                    <h2>{{ t('common.a_place_to_begin', 'A place to begin') }}</h2>
                </div>
                <p>{{ data.description }}</p>
            </section>

            <section v-if="placeItems.length" class="container geo-section">
                <SectionHeading
                    :eyebrow="t('common.places_to_explore', 'Places to explore')"
                    :title="t('common.in_this_destination', 'Look closer at this destination')"
                    :description="t('common.destination_places_description', 'Landmarks and visitable places connected to this destination.')"
                />
                <div class="geo-place-mosaic">
                    <PlaceCard v-for="(place, index) in placeItems" :key="place.id" :place="place" :featured="index === 0" />
                </div>
            </section>

            <section v-else class="container geo-sparse-note">
                <i class="bi bi-compass" aria-hidden="true"></i>
                <p>{{ t('common.destination_places_empty', 'Places to explore will be added as this destination guide grows.') }}</p>
                <Link :href="appUrl('/places')" class="public-button public-button--outline public-button--sm">{{ t('common.discover_places', 'Discover places') }}</Link>
            </section>

            <section v-if="data.hotels?.length" class="geo-commercial-section">
                <div class="container geo-section">
                    <SectionHeading
                        :eyebrow="t('common.stays', 'Stays')"
                        :title="t('common.stays_in_destination', 'Stays in this destination')"
                        :description="t('common.stays_in_destination_description', 'Published properties connected to this destination.')"
                    />
                    <div class="geo-commercial-grid geo-commercial-grid--hotels">
                        <HotelCard v-for="hotel in data.hotels" :key="hotel.id" :hotel="hotel" compact />
                    </div>
                    <div class="geo-section-action"><Link :href="appUrl(`/search/hotels?q=${encodeURIComponent(data.name)}`)" class="public-button public-button--outline">{{ t('common.view_all_stays', 'View all stays') }}</Link></div>
                </div>
            </section>

            <section v-if="data.tours?.length" class="container geo-section">
                <SectionHeading
                    :eyebrow="t('common.tours', 'Tours')"
                    :title="t('common.experiences_in_destination', 'Experiences connected to this destination')"
                    :description="t('common.tours_in_destination_description', 'Published tours that include this destination in their journey.')"
                />
                <div class="geo-commercial-grid">
                    <TourCard v-for="tour in data.tours" :key="tour.id" :tour="tour" />
                </div>
                <div class="geo-section-action"><Link :href="appUrl(`/search/tours?q=${encodeURIComponent(data.name)}`)" class="public-button public-button--outline">{{ t('common.explore_tours', 'Explore tours') }}</Link></div>
            </section>

            <section v-if="!data.hotels?.length && !data.tours?.length" class="container geo-discovery-cta">
                <div>
                    <p class="public-eyebrow">{{ t('common.keep_discovering', 'Keep discovering') }}</p>
                    <h2>{{ t('common.more_journey_to_find', 'There is more of the journey to find') }}</h2>
                </div>
                <div class="geo-discovery-cta__actions">
                    <Link :href="appUrl('/destinations')" class="public-button public-button--primary">{{ t('common.explore_destinations', 'Explore destinations') }}</Link>
                    <Link :href="appUrl('/places')" class="public-button public-button--outline">{{ t('common.discover_places', 'Discover places') }}</Link>
                </div>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
.geo-detail-page { background: var(--public-surface, var(--cream)); }
.geo-detail-hero { position: relative; display: grid; min-height: min(72vh, 48rem); align-items: end; overflow: hidden; color: #fff; background: var(--public-ink, var(--maroon-deep)); }
.geo-detail-hero__media, .geo-detail-hero__shade { position: absolute; inset: 0; }
.geo-detail-hero__media :deep(.public-media-frame) { width: 100%; height: 100%; min-height: 100%; }
.geo-detail-hero__media :deep(.public-media-frame > img) { width: 100%; height: 100%; object-fit: cover; }
.geo-detail-hero__shade { background: linear-gradient(180deg, rgba(15, 11, 20, .16) 20%, rgba(15, 11, 20, .84) 100%); }
.geo-detail-hero__content { position: relative; z-index: 1; padding-block: 2rem clamp(3.5rem, 9vw, 7.5rem); }
.geo-breadcrumbs { display: flex; flex-wrap: wrap; gap: .55rem; align-items: center; margin-bottom: 2.2rem; color: rgba(255,255,255,.7); font-size: .78rem; }
.geo-breadcrumbs a { color: #fff; }
.geo-detail-hero h1 { max-width: 10ch; margin: 0 0 1.2rem; color: #fff; font-size: clamp(3.5rem, 10vw, 8rem); line-height: .9; }
.geo-detail-hero__lede { max-width: 54ch; margin: 0; color: rgba(255,255,255,.84); font-size: 1.05rem; line-height: 1.75; white-space: pre-line; }
.geo-editorial-intro { display: grid; grid-template-columns: minmax(12rem, .75fr) minmax(0, 1.25fr); gap: clamp(2rem, 8vw, 8rem); align-items: start; padding-block: clamp(3.5rem, 8vw, 7rem); }
.geo-editorial-intro h2, .geo-discovery-cta h2 { margin: 0; font-size: clamp(2rem, 4vw, 3.5rem); }
.geo-editorial-intro > p { max-width: 62ch; margin: 0; color: var(--public-muted, #756c68); font-size: 1.15rem; line-height: 1.9; white-space: pre-line; }
.geo-section { padding-block: clamp(3.5rem, 7vw, 6.5rem); }
.geo-place-mosaic { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 1rem; margin-top: 2.5rem; }
.geo-place-mosaic :deep(.homepage-place-card) { grid-column: span 3; min-height: 18rem; }
.geo-place-mosaic :deep(.homepage-place-card--featured) { grid-column: span 6; }
.geo-commercial-section { background: var(--public-surface-alt, var(--cream-warm)); }
.geo-commercial-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.2rem; margin-top: 2.5rem; }
.geo-commercial-grid--hotels { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.geo-section-action { display: flex; justify-content: flex-end; margin-top: 2rem; }
.geo-sparse-note { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding-block: 0 4rem; color: var(--public-muted, #756c68); }
.geo-sparse-note i { color: var(--public-brand, var(--maroon)); font-size: 1.35rem; }
.geo-sparse-note p { margin: 0; }
.geo-discovery-cta { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: 2rem; padding-block: 1rem 6rem; }
.geo-discovery-cta__actions { display: flex; flex-wrap: wrap; gap: .75rem; }
@media (max-width: 991px) { .geo-place-mosaic :deep(.homepage-place-card), .geo-place-mosaic :deep(.homepage-place-card--featured) { grid-column: span 4; } .geo-commercial-grid, .geo-commercial-grid--hotels { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 767px) { .geo-detail-hero { min-height: 34rem; } .geo-editorial-intro { grid-template-columns: 1fr; gap: 1rem; } .geo-place-mosaic { display: flex; flex-wrap: wrap; } .geo-place-mosaic :deep(.homepage-place-card), .geo-place-mosaic :deep(.homepage-place-card--featured) { flex: 1 1 calc(50% - .5rem); min-height: 15rem; } .geo-commercial-grid, .geo-commercial-grid--hotels { grid-template-columns: 1fr; } .geo-section-action { justify-content: flex-start; } }
@media (max-width: 420px) { .geo-place-mosaic :deep(.homepage-place-card), .geo-place-mosaic :deep(.homepage-place-card--featured) { flex-basis: 100%; } }
</style>
