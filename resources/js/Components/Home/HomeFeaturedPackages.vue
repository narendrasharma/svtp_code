<script setup>
import PackageCard from '../PackageCard.vue';
import { appUrl } from '../../appUrl';
import { contactInfo } from '../../festiveAssets';

defineProps({
    tours: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ({}) },
});
</script>

<template>
    <section class="container py-5">
        <p class="section-eyebrow text-center">दर्शन यात्रा</p>
        <h2 class="section-title text-center mb-5">{{ settings.heading || 'Popular Tour Packages' }}</h2>
        <div class="row g-4">
            <div v-for="tour in tours" :key="tour.id" class="col-md-4">
                <PackageCard :pkg="tour" />
            </div>
        </div>
        <div class="text-center mt-5">
            <a :href="appUrl('/packages')" class="btn btn-outline-svtp">Browse All Tours</a>
            <a href="https://wa.me/918923427393" target="_blank" rel="noopener" class="btn btn-svtp ms-2">
                <i class="bi bi-whatsapp me-1"></i>Plan My Trip on WhatsApp
            </a>
            <a :href="contactInfo.phoneHref" class="btn btn-outline-svtp ms-2">
                <i class="bi bi-telephone-fill me-1"></i>Call Now
            </a>
        </div>
    </section>
</template>
