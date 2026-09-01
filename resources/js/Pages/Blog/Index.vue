<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';
import { blogPosts } from '../../festiveAssets';
</script>

<template>
    <AppLayout>
        <section class="trust-strip py-5 mb-4">
            <div class="container text-center">
                <p class="section-eyebrow text-white opacity-75">यात्रा गाइड</p>
                <h1 class="text-white" style="font-family: var(--font-display);">Travel Blog &amp; Guides</h1>
                <p class="opacity-75 mb-0">Practical, search-friendly guides for planning your Braj trip.</p>
            </div>
        </section>

        <div class="container py-2 pb-5">
            <div class="row g-4">
                <div v-for="post in blogPosts" :key="post.title" class="col-md-4">
                    <div class="blog-card">
                        <img :src="post.image" :alt="post.title" loading="lazy" />
                        <div class="blog-card-body">
                            <h5 class="text-svtp">{{ post.title }}</h5>
                            <p class="small text-muted">{{ post.excerpt }}</p>
                            <small class="text-muted fst-italic d-block mb-2">{{ post.date }}</small>
                            <a :href="appUrl('/packages')" class="btn btn-outline-svtp btn-sm">Related Tours</a>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-center text-muted small mt-5">
                These are placeholder posts to show the layout. Real articles (Banke Bihari timings, how to
                reach Vrindavan from Delhi, a 2-day itinerary, a Holi guide, etc.) can go live here once written
                — each one should link to its relevant tour package.
            </p>
        </div>
    </AppLayout>
</template>
