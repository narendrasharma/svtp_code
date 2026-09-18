<script setup>
import { appUrl } from '../../appUrl';
import { attractions } from '../../festiveAssets';

defineProps({
    settings: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section class="container py-5">
        <p class="section-eyebrow text-center">प्रमुख स्थल</p>
        <h2 class="section-title text-center mb-2">{{ settings.heading || 'Explore Braj — Must-Visit Places' }}</h2>
        <p class="text-muted text-center mb-5">A quick look at the sites every darshan route revolves around.</p>
        <div class="row g-4">
            <div v-for="a in attractions" :key="a.name" class="col-md-3 col-6">
                <div class="attraction-card">
                    <img :src="a.image" :alt="a.name" loading="lazy" />
                    <div class="attraction-card-body">
                        <h6 class="text-svtp mb-1">{{ a.name }}</h6>
                        <p class="small text-muted mb-0">{{ a.blurb }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="text-center mt-4">
            <a :href="appUrl('/destinations')" class="btn btn-outline-svtp">Explore All Destinations</a>
        </div>
    </section>
</template>
