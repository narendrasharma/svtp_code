<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import StarRating from '../../Components/StarRating.vue';
import { reviewsSummary } from '../../festiveAssets';
import SeoHead from "@/Components/SeoHead.vue";

defineProps({ testimonials: { type: Array, default: () => [] } });
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Customer Reviews & Testimonials"
            description="Read genuine travel experiences and reviews shared by our customers."
        />
        <section class="trust-strip py-5 mb-4">
            <div class="container text-center">
                <p class="section-eyebrow text-white opacity-75">भक्तों के अनुभव</p>
                <h1 class="text-white" style="font-family: var(--font-display);">Guest Reviews</h1>
                <p class="opacity-75 mb-0">Real feedback, collected after guests complete their tour.</p>
            </div>
        </section>

        <div class="container py-2 pb-5">
            <div class="row g-4 mb-5">
                <div class="col-lg-4">
                    <div class="glass-card p-4 text-center">
                        <h2 class="price-tag mb-0">{{ reviewsSummary.average }}</h2>
                        <StarRating :rating="reviewsSummary.average" />
                        <p class="text-muted small mt-2 mb-0">Based on {{ reviewsSummary.count }}+ trips</p>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="glass-card p-4">
                        <div v-for="b in reviewsSummary.breakdown" :key="b.stars" class="review-bar-row">
                            <span style="width: 44px;">{{ b.stars }} ★</span>
                            <div class="review-bar-track"><div class="review-bar-fill" :style="{ width: b.pct + '%' }"></div></div>
                            <span style="width: 36px;" class="text-muted">{{ b.pct }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="testimonials.length" class="row g-4">
                <div v-for="t in testimonials" :key="t.id" class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-mark mb-1">॥</p>
                        <StarRating :rating="t.rating" />
                        <p class="mt-2 mb-3">{{ t.comment }}</p>
                        <p class="fw-semibold mb-0">— {{ t.reviewer_name || t.user?.name || 'Guest' }}</p>
                        <small class="text-muted">{{ t.package?.title }}</small>
                    </div>
                </div>
            </div>
            <p v-else class="text-center text-muted">Reviews will appear here as guests submit them.</p>
        </div>
    </AppLayout>
</template>
