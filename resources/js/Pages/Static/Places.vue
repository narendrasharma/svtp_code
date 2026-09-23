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
    places: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
});

const { t } = useLocalization();

function placeHref(place) {
    return appUrl(`/places/${place.slug}`);
}

function destinationHref(place) {
    return place.destination?.slug ? appUrl(`/destinations/${place.destination.slug}`) : null;
}
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="seo.title || t('common.places_to_explore', 'Places to explore')"
            :description="seo.description || t('common.places_page_description', 'Explore landmarks, attractions, and places worth adding to your journey.')"
            :canonical="seo.canonical"
        />

        <main class="geo-page">
            <section class="geo-index-hero geo-index-hero--places">
                <div class="container geo-index-hero__inner">
                    <nav class="geo-breadcrumbs" aria-label="Breadcrumb">
                        <Link :href="appUrl('/')">{{ t('common.home', 'Home') }}</Link>
                        <span aria-hidden="true">/</span>
                        <span aria-current="page">{{ t('common.places_to_explore', 'Places to explore') }}</span>
                    </nav>
                    <p class="public-eyebrow">{{ t('common.discovery', 'Discovery') }}</p>
                    <h1>{{ t('common.places_index_title', 'Places that give a journey its character') }}</h1>
                    <p>{{ t('common.places_index_intro', 'Browse visitable places and landmarks with their true destination context.') }}</p>
                </div>
            </section>

            <section class="container geo-section">
                <SectionHeading
                    :eyebrow="t('common.places_to_explore', 'Places to explore')"
                    :title="t('common.places_section_title', 'Look closer')"
                    :description="t('common.places_section_description', 'Move from a broad destination to the places that make it memorable.')"
                />

                <div v-if="places.length" class="geo-place-grid">
                    <article v-for="place in places" :key="place.id" class="geo-place-card">
                        <Link :href="placeHref(place)" class="geo-place-card__media" :aria-label="`${t('common.view_place', 'View place')}: ${place.display_name || place.name}`">
                            <ImageWithFallback :src="mediaUrl(place.image)" :alt="place.display_name || place.name" aspect="portrait" kind="place" :label="t('common.place', 'Place')" />
                        </Link>
                        <div class="geo-place-card__body">
                            <Link v-if="destinationHref(place)" :href="destinationHref(place)" class="public-eyebrow geo-place-card__context">{{ place.destination.name }}</Link>
                            <h2><Link :href="placeHref(place)">{{ place.display_name || place.name }}</Link></h2>
                            <p v-if="place.display_description" class="geo-card-description">{{ place.display_description }}</p>
                            <Link :href="placeHref(place)" class="public-button public-button--text public-button--sm mt-auto">
                                {{ t('common.view_place', 'View place') }} <i class="bi bi-arrow-up-right" data-dir-icon="arrow" aria-hidden="true"></i>
                            </Link>
                        </div>
                    </article>
                </div>

                <div v-else class="geo-empty-state">
                    <i class="bi bi-signpost-2" aria-hidden="true"></i>
                    <h2>{{ t('common.places_coming_soon', 'Places to explore are being prepared') }}</h2>
                    <p>{{ t('common.places_empty_description', 'Published places will appear here as destination guides grow.') }}</p>
                </div>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
.geo-page { background: var(--public-surface, var(--cream)); }
.geo-index-hero { background: var(--public-ink, var(--maroon-deep)); color: #fff; }
.geo-index-hero--places { background: var(--public-accent-deep, var(--yamuna-deep)); }
.geo-index-hero__inner { padding-block: clamp(3.5rem, 8vw, 7rem); max-width: 960px; }
.geo-index-hero h1 { max-width: 13ch; margin: 0 0 1rem; color: #fff; font-size: clamp(2.7rem, 7vw, 5.8rem); line-height: .98; }
.geo-index-hero p:last-child { max-width: 54ch; margin: 0; color: rgba(255,255,255,.74); font-size: 1.05rem; }
.geo-breadcrumbs { display: flex; flex-wrap: wrap; gap: .55rem; align-items: center; margin-bottom: 3.2rem; color: rgba(255,255,255,.66); font-size: .78rem; }
.geo-breadcrumbs a { color: #fff; }
.geo-section { padding-block: clamp(3.5rem, 7vw, 6.5rem); }
.geo-place-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.3rem; margin-top: 2.5rem; }
.geo-place-card { overflow: hidden; background: #fff; border: 1px solid var(--public-border, #eadfd4); border-radius: 1.1rem; box-shadow: var(--public-shadow-sm, 0 10px 30px rgba(55,35,25,.06)); }
.geo-place-card__media { display: block; }
.geo-place-card__media :deep(.public-media-frame) { min-height: 15rem; }
.geo-place-card__body { display: flex; flex-direction: column; gap: .55rem; min-height: 12rem; padding: 1.1rem 1.15rem 1.2rem; }
.geo-place-card__context { width: fit-content; }
.geo-place-card h2 { margin: 0; font-size: 1.35rem; }
.geo-place-card h2 a { color: var(--public-text, var(--ink)); }
.geo-card-description { display: -webkit-box; overflow: hidden; margin: 0; color: var(--public-muted, #756c68); line-height: 1.6; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
.geo-empty-state { max-width: 38rem; margin: 2.5rem auto 0; padding: 3.5rem 1.5rem; text-align: center; border: 1px dashed var(--public-border-strong, #d7c9bd); border-radius: 1rem; }
.geo-empty-state i { color: var(--public-brand, var(--maroon)); font-size: 2.2rem; }
.geo-empty-state h2 { margin: 1rem 0 .5rem; font-size: 1.6rem; }
.geo-empty-state p { margin: 0; color: var(--public-muted, #756c68); }
@media (max-width: 991px) { .geo-place-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 575px) { .geo-place-grid { grid-template-columns: 1fr; } .geo-place-card__media :deep(.public-media-frame) { min-height: 13rem; } }
</style>
