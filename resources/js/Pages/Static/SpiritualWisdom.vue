<script setup>
import { ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { dailyShlokas, culturalTrivia, festivalGuidesDetailed } from '../../festiveAssets';
import { appUrl } from '../../appUrl';

const dayOfYear = Math.floor((Date.now() - new Date(new Date().getFullYear(), 0, 0)) / 86400000);
const todaysShloka = dailyShlokas[dayOfYear % dailyShlokas.length];

const openTrivia = ref(0);
function toggleTrivia(i) {
    openTrivia.value = openTrivia.value === i ? -1 : i;
}
</script>

<template>
    <AppLayout>
        <!-- Hero -->
        <section class="trust-strip py-5">
            <div class="container text-center">
                <p class="section-eyebrow text-white opacity-75">आध्यात्मिक ज्ञान</p>
                <h1 class="text-white mb-3" style="font-family: var(--font-display);">Spiritual Wisdom from Braj</h1>
                <p class="opacity-75 mb-0" style="max-width: 60ch; margin-inline: auto;">
                    Daily shlokas, cultural trivia, and festival guides from the land of Radha-Krishna —
                    to help you plan your yatra with meaning, not just an itinerary.
                </p>
            </div>
        </section>

        <!-- Today's Shloka -->
        <section class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="shloka-quote-card text-center fade-in-up">
                        <p class="small text-uppercase opacity-75 mb-2" style="letter-spacing: 0.1em;">Today's Shloka</p>
                        <p class="devanagari fs-3 mb-3">{{ todaysShloka.sanskrit }}</p>
                        <p class="mb-2">{{ todaysShloka.translation }}</p>
                        <small class="opacity-75">— {{ todaysShloka.source }}</small>
                    </div>
                </div>
            </div>
        </section>

        <!-- Shloka archive -->
        <section class="container py-4">
            <p class="section-eyebrow text-center">शास्त्रों से</p>
            <h2 class="section-title text-center mb-5">From the Holy Books</h2>
            <div class="row g-4">
                <div v-for="(s, i) in dailyShlokas" :key="i" class="col-md-4">
                    <div class="glass-card p-4 h-100 fade-in-up" :class="`delay-${(i % 6) + 1}`">
                        <p class="devanagari fs-5 mb-2">{{ s.sanskrit }}</p>
                        <p class="small mb-2">{{ s.translation }}</p>
                        <small class="text-svtp fw-semibold">{{ s.source }}</small>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cultural trivia -->
        <section class="bg-cream-warm py-5 my-5">
            <div class="container">
                <p class="section-eyebrow text-center">जिज्ञासा</p>
                <h2 class="section-title text-center mb-5">Cultural Trivia</h2>
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div
                            v-for="(t, i) in culturalTrivia"
                            :key="i"
                            class="glass-card p-4 mb-3"
                            style="cursor: pointer;"
                            @click="toggleTrivia(i)"
                        >
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 text-svtp">{{ t.question }}</h6>
                                <i class="bi" :class="openTrivia === i ? 'bi-dash-circle-fill' : 'bi-plus-circle-fill'" style="color: var(--gulal);"></i>
                            </div>
                            <p v-if="openTrivia === i" class="mb-0 mt-3 small">{{ t.answer }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Festival guides -->
        <section class="container py-5">
            <p class="section-eyebrow text-center">पर्व गाइड</p>
            <h2 class="section-title text-center mb-2">Festival Guides</h2>
            <p class="text-muted text-center mb-5">Plan around the celebrations that make Braj unforgettable.</p>

            <div class="row g-4">
                <div v-for="f in festivalGuidesDetailed" :key="f.name" class="col-lg-6">
                    <div class="glass-card overflow-hidden h-100">
                        <div class="wisdom-card" style="min-height: 220px;">
                            <img :src="f.image" :alt="f.name" loading="lazy" />
                            <div class="wisdom-card-body">
                                <span class="festival-badge" style="position: static; display: inline-flex; margin-bottom: 0.5rem;">{{ f.window }}</span>
                                <h5 class="text-white mb-0">{{ f.name }}</h5>
                            </div>
                        </div>
                        <div class="p-4">
                            <p class="small mb-3">{{ f.description }}</p>
                            <p class="small fw-semibold text-svtp mb-2">Travel tips</p>
                            <ul class="small mb-4">
                                <li v-for="(tip, i) in f.tips" :key="i">{{ tip }}</li>
                            </ul>
                            <a :href="appUrl('/packages?category=braj')" class="btn btn-outline-svtp btn-sm">Plan This Festival Tour</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
