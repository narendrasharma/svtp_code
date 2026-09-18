<script setup>
import StarRating from '../StarRating.vue';
import { appUrl } from '../../appUrl';

defineProps({
    testimonials: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section v-if="testimonials.length" class="bg-cream-warm py-5">
        <div class="container">
            <p class="section-eyebrow text-center">भक्तों के अनुभव</p>
            <h2 class="section-title text-center mb-5">{{ settings.heading || 'What Our Devotees Say' }}</h2>
            <div class="row g-4">
                <div v-for="t in testimonials" :key="t.id" class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-mark mb-1">॥</p>
                        <StarRating :rating="t.rating" />
                        <p class="mt-2 mb-3">{{ t.comment }}</p>
                        <p class="fw-semibold mb-0">— {{ t.user?.name }}</p>
                        <small class="text-muted">{{ t.package?.title }}</small>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <a :href="appUrl('/testimonials')" class="btn btn-outline-svtp">Read More Reviews</a>
            </div>
        </div>
    </section>
</template>
