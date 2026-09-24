<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';
import SeoHead from '../../Components/SeoHead.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import HotelCard from '../../Components/Public/Cards/HotelCard.vue';
import TourCard from '../../Components/Public/Cards/TourCard.vue';
import SectionHeading from '../../Components/Public/UI/SectionHeading.vue';
import { mediaUrl } from '../../Components/Public/homepage';
import { useLocalization } from '../../i18n';

const props = defineProps({
    place: { type: Object, required: true },
    landing: { type: Object, default: null },
    seo: { type: Object, default: () => ({}) },
});

const { t } = useLocalization();
const data = computed(() => props.landing || {
    name: props.place.display_name || props.place.name,
    description: props.place.display_description || props.place.description,
    image: props.place.image,
    geography: {
        destination: props.place.destination?.name,
        city: props.place.destination?.city?.name,
    },
    tours: [],
    nearby_hotels: [],
    seo: props.seo,
});
const heroImage = computed(() => mediaUrl(data.value.image));
const heroDescription = computed(() => {
    const excerpt = data.value.excerpt || props.place.display_excerpt;
    if (excerpt) return excerpt;
    return String(data.value.description || '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim().slice(0, 240);
});
const destinationHref = computed(() => props.place.destination?.slug ? appUrl(`/destinations/${props.place.destination.slug}`) : null);
const geographyLabel = computed(() => Object.values(data.value.geography || {}).filter(Boolean).join(' · '));
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="data.seo?.title || `${data.name} — ${t('common.place', 'Place')}`"
            :description="data.seo?.description || data.description || t('common.place_guide_description', 'Discover this place and the destination it belongs to.')"
            :image="data.image"
            :canonical="data.seo?.canonical"
            :noindex="data.seo?.noindex === true"
        />

        <main class="geo-detail-page">
            <section class="geo-detail-hero geo-detail-hero--place">
                <div class="geo-detail-hero__media"><ImageWithFallback :src="heroImage" :alt="data.name" aspect="editorial" kind="place" :label="t('common.place', 'Place')" loading="eager" /></div>
                <div class="geo-detail-hero__shade"></div>
                <div class="public-container geo-detail-hero__content">
                    <nav class="geo-breadcrumbs" aria-label="Breadcrumb">
                        <Link :href="appUrl('/')">{{ t('common.home', 'Home') }}</Link>
                        <span aria-hidden="true">/</span>
                        <Link :href="appUrl('/destinations')">{{ t('common.destinations', 'Destinations') }}</Link>
                        <template v-if="destinationHref">
                            <span aria-hidden="true">/</span>
                            <Link :href="destinationHref">{{ place.destination.name }}</Link>
                        </template>
                        <span aria-hidden="true">/</span>
                        <span aria-current="page">{{ data.name }}</span>
                    </nav>
                    <p v-if="geographyLabel" class="public-eyebrow">{{ geographyLabel }}</p>
                    <h1>{{ data.name }}</h1>
                    <p v-if="heroDescription" class="geo-detail-hero__lede">{{ heroDescription }}</p>
                </div>
            </section>

            <section class="public-container geo-place-overview">
                <div>
                    <p class="public-eyebrow">{{ t('common.about', 'About') }}</p>
                    <h2>{{ t('common.about_this_place', 'About this place') }}</h2>
                </div>
                <div class="geo-place-overview__copy">
                    <p v-if="data.description">{{ data.description }}</p>
                    <p v-else class="geo-muted">{{ t('common.place_description_empty', 'A longer description for this place will be added as the guide develops.') }}</p>
                    <Link v-if="destinationHref" :href="destinationHref" class="public-button public-button--outline public-button--sm">
                        {{ t('common.explore_destination', 'Explore destination') }} <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                    </Link>
                </div>
            </section>

            <section v-if="data.nearby_hotels?.length" class="geo-commercial-section">
                <div class="public-container geo-section">
                    <SectionHeading
                        :eyebrow="t('common.stays', 'Stays')"
                        :title="t('common.stays_in_destination', 'Stays in this destination')"
                        :description="t('common.stays_at_place_context', 'Published properties connected through this place’s destination.')"
                    />
                    <div class="geo-commercial-grid">
                        <HotelCard v-for="hotel in data.nearby_hotels" :key="hotel.id" :hotel="hotel" compact />
                    </div>
                </div>
            </section>

            <section v-if="data.tours?.length" class="public-container geo-section">
                <SectionHeading
                    :eyebrow="t('common.tours', 'Tours')"
                    :title="t('common.tours_including_place', 'Tours including this place')"
                    :description="t('common.tours_including_place_description', 'Published journeys that name this place in their travel context.')"
                />
                <div class="geo-commercial-grid">
                    <TourCard v-for="tour in data.tours" :key="tour.id" :tour="tour" />
                </div>
                <div class="geo-section-action"><Link :href="appUrl(`/search/tours?q=${encodeURIComponent(data.name)}`)" class="public-button public-button--outline">{{ t('common.explore_tours', 'Explore tours') }}</Link></div>
            </section>

            <section v-if="!data.tours?.length && !data.nearby_hotels?.length" class="public-container geo-discovery-cta">
                <div>
                    <p class="public-eyebrow">{{ t('common.keep_discovering', 'Keep discovering') }}</p>
                    <h2>{{ t('common.find_more_places', 'Find more places to explore') }}</h2>
                </div>
                <div class="geo-discovery-cta__actions">
                    <Link :href="appUrl('/places')" class="public-button public-button--primary">{{ t('common.discover_places', 'Discover places') }}</Link>
                    <Link :href="appUrl('/destinations')" class="public-button public-button--outline">{{ t('common.explore_destinations', 'Explore destinations') }}</Link>
                </div>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
.geo-detail-page { background: var(--public-surface, var(--cream)); }
.geo-detail-hero { position: relative; display: grid; min-height: min(72vh, 48rem); align-items: end; overflow: hidden; color: #fff; background: var(--public-ink, var(--maroon-deep)); }
.geo-detail-hero--place { background: var(--public-accent-deep, var(--yamuna-deep)); }
.geo-detail-hero__media, .geo-detail-hero__shade { position: absolute; inset: 0; }
.geo-detail-hero__media :deep(.public-media-frame) { width: 100%; height: 100%; min-height: 100%; }
.geo-detail-hero__media :deep(.public-media-frame > img) { width: 100%; height: 100%; object-fit: cover; }
.geo-detail-hero__shade { background: linear-gradient(180deg, rgba(15, 11, 20, .16) 20%, rgba(15, 11, 20, .84) 100%); }
.geo-detail-hero__content { position: relative; z-index: 1; padding-block: 2rem clamp(3.5rem, 9vw, 7.5rem); }
.geo-breadcrumbs { display: flex; flex-wrap: wrap; gap: .55rem; align-items: center; margin-bottom: 2.2rem; color: rgba(255,255,255,.7); font-size: .78rem; }
.geo-breadcrumbs a { color: #fff; }
.geo-detail-hero h1 { max-width: 10ch; margin: 0 0 1.2rem; color: #fff; font-size: clamp(3.5rem, 10vw, 8rem); line-height: .9; }
.geo-detail-hero__lede { max-width: 54ch; margin: 0; color: rgba(255,255,255,.84); font-size: 1.05rem; line-height: 1.75; white-space: pre-line; }
.geo-place-overview { display: grid; grid-template-columns: minmax(12rem, .75fr) minmax(0, 1.25fr); gap: clamp(2rem, 8vw, 8rem); align-items: start; padding-block: clamp(3.5rem, 8vw, 7rem); }
.geo-place-overview h2, .geo-discovery-cta h2 { margin: 0; font-size: clamp(2rem, 4vw, 3.5rem); }
.geo-place-overview__copy p { max-width: 62ch; margin: 0 0 1.5rem; color: var(--public-muted, #756c68); font-size: 1.15rem; line-height: 1.9; white-space: pre-line; }
.geo-muted { font-size: 1rem !important; }
.geo-commercial-section { background: var(--public-surface-alt, var(--cream-warm)); }
.geo-section { padding-block: clamp(3.5rem, 7vw, 6.5rem); }
.geo-commercial-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1.2rem; margin-top: 2.5rem; }
.geo-section-action { display: flex; justify-content: flex-end; margin-top: 2rem; }
.geo-discovery-cta { display: flex; flex-wrap: wrap; align-items: end; justify-content: space-between; gap: 2rem; padding-block: 1rem 6rem; }
.geo-discovery-cta__actions { display: flex; flex-wrap: wrap; gap: .75rem; }
@media (max-width: 991px) { .geo-commercial-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 767px) { .geo-detail-hero { min-height: 34rem; } .geo-place-overview { grid-template-columns: 1fr; gap: 1rem; } .geo-commercial-grid { grid-template-columns: 1fr; } .geo-section-action { justify-content: flex-start; } }
</style>
