<script setup>
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { galleryImages } from '../../festiveAssets';
import SeoHead from "@/Components/SeoHead.vue";

const active = ref(null);
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Travel Gallery"
            description="Explore memorable destinations, tours and travel experiences through our photo gallery."
        />
        <section class="trust-strip py-5 mb-4">
            <div class="container text-center">
                <p class="section-eyebrow text-white opacity-75">फोटो गैलरी</p>
                <h1 class="text-white" style="font-family: var(--font-display);">Gallery</h1>
                <p class="opacity-75 mb-0">Temples, ghats and festival colours from across Braj.</p>
            </div>
        </section>

        <div class="container py-2 pb-5">
            <div class="gallery-grid" style="grid-template-columns: repeat(3, 1fr);">
                <a v-for="(img, i) in galleryImages" :key="i" href="#" @click.prevent="active = img">
                    <img :src="img" :alt="`Gallery photo ${i + 1}`" loading="lazy" style="height: 220px;" />
                </a>
            </div>
            <p class="text-center text-muted small mt-4">
                Placeholder photography — swap in real trip photos any time via <code>galleryImages</code> in
                <code>festiveAssets.js</code>, or wire this up to your admin panel's Galleries module later.
            </p>
        </div>

        <div v-if="active" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
             style="background: rgba(36,6,19,0.85); z-index: 2000;" @click="active = null">
            <img :src="active" alt="Enlarged gallery photo" style="max-width: 90vw; max-height: 85vh; border-radius: 16px;" />
        </div>
    </AppLayout>
</template>
