<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';
import SeoHead from '../../Components/SeoHead.vue';
import ImageWithFallback from '../../Components/Public/Media/ImageWithFallback.vue';
import SectionHeading from '../../Components/Public/UI/SectionHeading.vue';
import { mediaUrl } from '../../Components/Public/homepage';
import { useLocalization } from '../../i18n';

const props = defineProps({
    destinations: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
});

const { t } = useLocalization();

function destinationHref(destination) {
    return appUrl(`/destinations/${destination.slug}`);
}

function context(destination) {
    return [destination.city?.name, destination.state, destination.country].filter(Boolean).join(' · ');
}
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="seo.title || t('common.destinations', 'Destinations')"
            :description="seo.description || t('common.destinations_page_description', 'Explore destinations, places to visit, and travel experiences.')"
            :canonical="seo.canonical"
        />

        <main class="geo-page">
            <section class="geo-index-hero">
                <div class="public-container geo-index-hero__inner">
                    <nav class="geo-breadcrumbs" aria-label="Breadcrumb">
                        <Link :href="appUrl('/')">{{ t('common.home', 'Home') }}</Link>
                        <span aria-hidden="true">/</span>
                        <span aria-current="page">{{ t('common.destinations', 'Destinations') }}</span>
                    </nav>
                    <p class="public-eyebrow">{{ t('common.travel_marketplace', 'Travel marketplace') }}</p>
                    <h1>{{ t('common.discover_destinations', 'Discover destinations with room to wander') }}</h1>
                    <p>{{ t('common.destinations_page_intro', 'Find the places, cities, and experiences that can shape your next journey.') }}</p>
                </div>
            </section>

            <section class="public-container geo-section">
                <SectionHeading
                    :eyebrow="t('common.destinations', 'Destinations')"
                    :title="t('common.destinations_index_title', 'Start with a place that feels like yours')"
                    :description="t('common.destinations_index_description', 'Explore travel destinations with useful context and honest availability.')"
                />

                <div v-if="destinations.length" class="geo-destination-grid">
                    <article v-for="destination in destinations" :key="destination.id" class="geo-destination-card">
                        <Link :href="destinationHref(destination)" class="geo-destination-card__media" :aria-label="`${t('common.explore_destination', 'Explore destination')}: ${destination.display_name || destination.name}`">
                            <ImageWithFallback
                                :src="mediaUrl(destination.image)"
                                :alt="destination.display_name || destination.name"
                                aspect="editorial"
                                kind="destination"
                                :label="t('common.destination', 'Destination')"
                            />
                        </Link>
                        <div class="geo-destination-card__body">
                            <p v-if="context(destination)" class="public-eyebrow">{{ context(destination) }}</p>
                            <h2><Link :href="destinationHref(destination)">{{ destination.display_name || destination.name }}</Link></h2>
                            <p v-if="destination.display_description" class="geo-card-description">{{ destination.display_description }}</p>
                            <div class="geo-card-footer">
                                <span v-if="destination.places_count" class="geo-card-fact">{{ destination.places_count }} {{ destination.places_count === 1 ? t('common.place', 'place') : t('common.places', 'places') }}</span>
                                <span v-if="destination.tour_packages_count" class="geo-card-fact">{{ destination.tour_packages_count }} {{ destination.tour_packages_count === 1 ? t('common.tour', 'tour') : t('common.tours', 'tours') }}</span>
                                <Link :href="destinationHref(destination)" class="public-button public-button--text public-button--sm">
                                    {{ t('common.explore', 'Explore') }} <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="geo-empty-state">
                    <i class="bi bi-compass" aria-hidden="true"></i>
                    <h2>{{ t('common.destinations_coming_soon', 'Destination guides are taking shape') }}</h2>
                    <p>{{ t('common.destinations_empty_description', 'New destination guides will appear here as they are published.') }}</p>
                </div>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
.geo-page { background: var(--public-surface, var(--cream)); }
.geo-index-hero { background: var(--public-ink, var(--maroon-deep)); color: #fff; }
.geo-index-hero__inner { padding-block: clamp(3.5rem, 8vw, 7rem); max-width: 960px; }
.geo-index-hero h1 { max-width: 13ch; margin: 0 0 1rem; color: #fff; font-size: clamp(2.7rem, 7vw, 5.8rem); line-height: .98; }
.geo-index-hero p:last-child { max-width: 54ch; margin: 0; color: rgba(255,255,255,.74); font-size: 1.05rem; }
.geo-breadcrumbs { display: flex; flex-wrap: wrap; gap: .55rem; align-items: center; margin-bottom: 3.2rem; color: rgba(255,255,255,.66); font-size: .78rem; }
.geo-breadcrumbs a { color: #fff; }
.geo-section { padding-block: clamp(3.5rem, 7vw, 6.5rem); }
.geo-destination-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 1.4rem; margin-top: 2.5rem; }
.geo-destination-card { grid-column: span 4; overflow: hidden; background: #fff; border: 1px solid var(--public-border, #eadfd4); border-radius: 1.1rem; box-shadow: var(--public-shadow-sm, 0 10px 30px rgba(55,35,25,.06)); }
.geo-destination-card:nth-child(4n + 1) { grid-column: span 5; }
.geo-destination-card:nth-child(4n + 2) { grid-column: span 7; }
.geo-destination-card__media { display: block; }
.geo-destination-card__media :deep(.public-media-frame) { min-height: 15rem; }
.geo-destination-card__body { display: flex; flex-direction: column; gap: .65rem; padding: 1.25rem 1.3rem 1.35rem; min-height: 13rem; }
.geo-destination-card h2 { margin: 0; font-size: 1.55rem; }
.geo-destination-card h2 a { color: var(--public-text, var(--ink)); }
.geo-card-description { display: -webkit-box; overflow: hidden; margin: 0; color: var(--public-muted, #756c68); line-height: 1.65; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
.geo-card-footer { display: flex; flex-wrap: wrap; align-items: center; gap: .8rem; margin-top: auto; }
.geo-card-fact { color: var(--public-muted, #756c68); font-size: .78rem; }
.geo-empty-state { max-width: 38rem; margin: 2.5rem auto 0; padding: 3.5rem 1.5rem; text-align: center; border: 1px dashed var(--public-border-strong, #d7c9bd); border-radius: 1rem; }
.geo-empty-state i { color: var(--public-brand, var(--maroon)); font-size: 2.2rem; }
.geo-empty-state h2 { margin: 1rem 0 .5rem; font-size: 1.6rem; }
.geo-empty-state p { margin: 0; color: var(--public-muted, #756c68); }
@media (max-width: 767px) {
    .geo-destination-grid { display: flex; flex-direction: column; }
    .geo-destination-card:nth-child(n) { grid-column: auto; }
    .geo-destination-card__media :deep(.public-media-frame) { min-height: 13rem; }
}
</style>
