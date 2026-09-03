<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PackageCard from '../../Components/PackageCard.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({ destination: { type: Object, required: true } });

const image = computed(() => {
    if (!props.destination.image || /^(https?:)?\/\//.test(props.destination.image)) return props.destination.image;

    return appUrl(props.destination.image.startsWith('/') ? props.destination.image : `/${props.destination.image}`);
});
const seoDescription = computed(() => props.destination.meta_description
    || props.destination.description
    || `Explore places and tour packages for ${props.destination.name}.`);
</script>

<template>
    <AppLayout>
        <Head :title="`${destination.name} Tours & Places`">
            <meta head-key="description" name="description" :content="seoDescription">
        </Head>

        <main>
            <section class="destination-hero" :class="{ 'has-image': image }">
                <img v-if="image" :src="image" :alt="destination.name" class="destination-hero-image">
                <div class="destination-hero-overlay"></div>
                <div class="container destination-hero-content text-center">
                    <p v-if="destination.city" class="section-eyebrow text-white opacity-75">{{ destination.city.name }}</p>
                    <h1>{{ destination.name }}</h1>
                    <p v-if="destination.description" class="destination-intro mx-auto mb-0">{{ destination.description }}</p>
                </div>
            </section>

            <section class="container py-5">
                <p class="section-eyebrow text-center">प्रमुख स्थल</p>
                <h2 class="section-title text-center mb-5">Places to Visit in {{ destination.name }}</h2>
                <div v-if="destination.places.length" class="row g-4 justify-content-center">
                    <div v-for="place in destination.places" :key="place.id" class="col-sm-6 col-lg-4">
                        <article class="glass-card p-4 h-100 text-center">
                            <i class="bi bi-bank2 place-icon"></i>
                            <h3 class="h5 text-svtp mt-3 mb-1">{{ place.name }}</h3>
                            <p v-if="place.description" class="small text-muted">{{ place.description }}</p>
                            <Link :href="`${appUrl('/places')}/${place.slug}`" class="btn btn-outline-svtp btn-sm mt-2">View Place</Link>
                        </article>
                    </div>
                </div>
                <p v-else class="text-center text-muted">Places for this destination will be added soon.</p>
            </section>

            <section class="bg-cream-warm py-5">
                <div class="container">
                    <p class="section-eyebrow text-center">दर्शन यात्रा</p>
                    <h2 class="section-title text-center mb-5">Tours Featuring {{ destination.name }}</h2>
                    <div v-if="destination.tour_packages.length" class="row g-4 justify-content-center">
                        <div v-for="tour in destination.tour_packages" :key="tour.id" class="col-md-6 col-lg-4">
                            <PackageCard :pkg="tour" />
                        </div>
                    </div>
                    <div v-else class="text-center">
                        <p class="text-muted">No published tours are linked to this destination yet.</p>
                        <Link :href="appUrl('/packages')" class="btn btn-outline-svtp">Browse All Tours</Link>
                    </div>
                </div>
            </section>
        </main>
    </AppLayout>
</template>

<style scoped>
.destination-hero { position: relative; display: grid; min-height: 340px; overflow: hidden; place-items: center; color: #fff; background: var(--festive-gradient); }
.destination-hero-image, .destination-hero-overlay { position: absolute; inset: 0; width: 100%; height: 100%; }
.destination-hero-image { object-fit: cover; }
.destination-hero-overlay { background: linear-gradient(90deg, rgba(46, 16, 46, 0.82), rgba(73, 27, 56, 0.58)); }
.destination-hero:not(.has-image) .destination-hero-overlay { background: radial-gradient(circle at 50% 20%, rgba(255, 190, 64, 0.2), transparent 40%); }
.destination-hero-content { position: relative; z-index: 1; padding-top: 4rem; padding-bottom: 4rem; }
.destination-hero h1 { font-family: var(--font-display); font-size: clamp(2.5rem, 7vw, 4.7rem); }
.destination-intro { max-width: 760px; white-space: pre-line; }
.place-icon { color: var(--gulal-deep); font-size: 2rem; }
</style>
