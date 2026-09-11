<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PackageCard from '../../Components/PackageCard.vue';
import { appUrl } from '../../appUrl';
import SeoHead from "@/Components/SeoHead.vue";

const props = defineProps({ place: { type: Object, required: true } });

const image = computed(() => {
    if (!props.place.image || /^(https?:)?\/\//.test(props.place.image)) return props.place.image;

    return appUrl(props.place.image.startsWith('/') ? props.place.image : `/${props.place.image}`);
});
const seoDescription = computed(() => props.place.meta_description
    || props.place.description
    || `Discover ${props.place.name} in ${props.place.destination.name} and explore related tour packages.`);
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="
        place.meta_title ||
        `${place.name} Tours & Visitor Guide`
    "
            :description="seoDescription"
            :image="place.image"
        />

        <main>
            <section class="place-hero" :class="{ 'has-image': image }">
                <img v-if="image" :src="image" :alt="place.name" class="place-hero-image">
                <div class="place-hero-overlay"></div>
                <div class="container place-hero-content text-center">
                    <Link :href="`${appUrl('/destinations')}/${place.destination.slug}`" class="section-eyebrow text-white opacity-75 text-decoration-none">
                        {{ place.destination.name }}<span v-if="place.destination.city"> · {{ place.destination.city.name }}</span>
                    </Link>
                    <h1>{{ place.name }}</h1>
                </div>
            </section>

            <section class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        <p class="section-eyebrow text-center">स्थल परिचय</p>
                        <h2 class="section-title text-center mb-4">About {{ place.name }}</h2>
                        <div class="glass-card p-4 p-md-5">
                            <p v-if="place.description" class="place-description mb-4">{{ place.description }}</p>
                            <p v-else class="text-muted mb-4">Visitor information for this place will be added soon.</p>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-top pt-3">
                                <span class="text-muted"><i class="bi bi-geo-alt-fill me-2 text-svtp"></i>{{ place.destination.name }}</span>
                                <Link :href="`${appUrl('/destinations')}/${place.destination.slug}`" class="btn btn-outline-svtp btn-sm">
                                    Explore {{ place.destination.name }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-cream-warm py-5">
                <div class="container">
                    <p class="section-eyebrow text-center">दर्शन यात्रा</p>
                    <h2 class="section-title text-center mb-5">Tours Including {{ place.name }}</h2>
                    <div v-if="place.tour_packages.length" class="row g-4 justify-content-center">
                        <div v-for="tour in place.tour_packages" :key="tour.id" class="col-md-6 col-lg-4">
                            <PackageCard :pkg="tour" />
                        </div>
                    </div>
                    <div v-else class="text-center">
                        <p class="text-muted">No published tours include this place yet.</p>
                        <Link :href="appUrl('/packages')" class="btn btn-outline-svtp">Browse All Tours</Link>
                    </div>
                </div>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
.place-hero { position: relative; display: grid; min-height: 340px; overflow: hidden; place-items: center; color: #fff; background: var(--festive-gradient); }
.place-hero-image, .place-hero-overlay { position: absolute; inset: 0; width: 100%; height: 100%; }
.place-hero-image { object-fit: cover; }
.place-hero-overlay { background: linear-gradient(90deg, rgba(35, 24, 74, 0.84), rgba(95, 29, 67, 0.58)); }
.place-hero:not(.has-image) .place-hero-overlay { background: radial-gradient(circle at 50% 20%, rgba(255, 190, 64, 0.2), transparent 40%); }
.place-hero-content { position: relative; z-index: 1; padding-top: 4rem; padding-bottom: 4rem; }
.place-hero h1 { font-family: var(--font-display); font-size: clamp(2.5rem, 7vw, 4.7rem); }
.place-description { color: #5f5359; font-size: 1.05rem; line-height: 1.8; white-space: pre-line; }
</style>
