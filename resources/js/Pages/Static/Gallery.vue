<script setup>
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import PublicPageHero from '../../Components/Public/Content/PublicPageHero.vue';
import SeoHead from '../../Components/SeoHead.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    images: { type: Array, default: () => [] },
});
const active = ref(null);
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Gallery"
            description="A visual journal of published travel places and experiences."
            :canonical="appUrl('/gallery')"
            :noindex="true"
        />
        <PublicPageHero eyebrow="Visual journal" title="Gallery" description="A visual journal of published travel places and experiences." />

        <main class="public-container py-5">
            <div v-if="images.length" class="row g-3">
                <div v-for="(image, index) in images" :key="image.id || image.src || index" class="col-6 col-lg-4">
                    <button type="button" class="gallery-tile" @click="active = image">
                        <img :src="image.src" :alt="image.alt || 'Published travel image'" loading="lazy">
                    </button>
                </div>
            </div>
            <EmptyState v-else :title="'The gallery is being prepared'" description="Published images will appear here when the marketplace has a gallery collection to share.">
                <template #icon><i class="bi bi-images" aria-hidden="true"></i></template>
            </EmptyState>
        </main>
        <div v-if="active" class="gallery-lightbox" role="dialog" aria-modal="true" aria-label="Image preview" @click.self="active = null">
            <button type="button" class="btn-close btn-close-white" aria-label="Close image preview" @click="active = null"></button>
            <img :src="active.src" :alt="active.alt || 'Published travel image'">
        </div>
    </AppLayout>
</template>

<style scoped>
.public-content-panel {
    padding: 1.5rem;
    border: 1px solid rgba(31, 72, 67, 0.1);
    border-radius: 1.25rem;
    background: #fffdf8;
}

.gallery-tile {
    width: 100%;
    padding: 0;
    overflow: hidden;
    border: 0;
    border-radius: 1.25rem;
    background: #edf3ef;
}

.gallery-tile img,
.team-image img {
    display: block;
    width: 100%;
    height: 15rem;
    object-fit: cover;
}

.gallery-lightbox {
    position: fixed;
    z-index: 2000;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 2rem;
    background: rgba(18, 35, 33, 0.9);
}

.gallery-lightbox .btn-close {
    position: absolute;
    inset-block-start: 1.5rem;
    inset-inline-end: 1.5rem;
}

.gallery-lightbox img {
    max-width: min(92vw, 70rem);
    max-height: 84vh;
    border-radius: 1rem;
}
</style>
