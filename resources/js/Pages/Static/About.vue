<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import PublicPageHero from '../../Components/Public/Content/PublicPageHero.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    page: { type: Object, default: () => ({}) },
});
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="page.meta_title || page.title || 'About the travel marketplace'"
            :description="page.meta_description || 'A shared space for discovering destinations, places, stays, and journeys.'"
            :canonical="$page.props.localizedSeo?.canonical"
        />

        <PublicPageHero
            eyebrow="About the platform"
            :title="page.title || 'About the travel marketplace'"
            :description="page.excerpt || 'A shared space for discovering destinations, places, stays, and journeys.'"
        />

        <main class="public-container py-5">
            <article v-if="page.content_available && page.content" class="public-rich-content" v-html="page.content"></article>
            <section v-else class="public-content-panel">
                <p class="public-eyebrow">A clear place to begin</p>
                <h2 class="public-heading public-heading--2">Discover with useful context.</h2>
                <p class="mb-0">This marketplace brings destinations, places, stays, and journeys together so travellers can explore at their own pace. Published platform information will appear here as it becomes available.</p>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
.public-content-panel {
    max-width: 48rem;
    padding: clamp(1.5rem, 4vw, 3rem);
    border: 1px solid rgba(31, 72, 67, 0.1);
    border-radius: 1.25rem;
    background: #fffdf8;
    box-shadow: 0 1rem 2.5rem rgba(31, 72, 67, 0.07);
}

.public-content-panel p:last-child {
    color: var(--public-muted, #65716d);
    font-size: 1.05rem;
    line-height: 1.8;
}
</style>
