<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { appUrl } from '../../appUrl';
import SeoHead from "@/Components/SeoHead.vue";

defineProps({ destinations: { type: Array, default: () => [] } });

function imageUrl(image) {
    if (!image || /^(https?:)?\/\//.test(image)) return image;

    return appUrl(image.startsWith('/') ? image : `/${image}`);
}
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Destinations"
            description="Explore popular travel destinations, attractions and available tour packages."
        />

        <section class="trust-strip py-5 mb-4">
            <div class="container text-center">
                <p class="section-eyebrow text-white opacity-75">ब्रज दर्शन</p>
                <h1 class="text-white" style="font-family: var(--font-display);">Explore Braj</h1>
                <p class="opacity-75 mb-0">The sacred destinations, temples and ghats our darshan routes are built around.</p>
            </div>
        </section>

        <main class="container py-2 pb-5">
            <div v-if="destinations.length" class="row g-4">
                <div v-for="destination in destinations" :key="destination.id" class="col-md-6 col-lg-4">
                    <article class="attraction-card h-100">
                        <img
                            v-if="destination.image"
                            :src="imageUrl(destination.image)"
                            :alt="destination.name"
                            loading="lazy"
                            style="height: 220px;"
                        >
                        <div v-else class="destination-placeholder" aria-hidden="true">
                            <i class="bi bi-flower3"></i>
                        </div>
                        <div class="attraction-card-body">
                            <p v-if="destination.city" class="section-eyebrow mb-1">{{ destination.city.name }}</p>
                            <h2 class="h5 text-svtp mb-2">{{ destination.name }}</h2>
                            <p v-if="destination.description" class="small text-muted destination-summary">{{ destination.description }}</p>
                            <p class="small text-muted mb-3">
                                {{ destination.places_count }} place<span v-if="destination.places_count !== 1">s</span>
                                · {{ destination.tour_packages_count }} tour<span v-if="destination.tour_packages_count !== 1">s</span>
                            </p>
                            <Link :href="`${appUrl('/destinations')}/${destination.slug}`" class="btn btn-outline-svtp btn-sm">Explore Destination</Link>
                        </div>
                    </article>
                </div>
            </div>
            <div v-else class="glass-card p-5 text-center">
                <i class="bi bi-geo-alt display-5 text-muted"></i>
                <p class="text-muted mt-3 mb-0">Destination guides are being prepared. Please check back soon.</p>
            </div>
        </main>
    </AppLayout>
</template>

<style scoped>
.destination-placeholder { display: grid; height: 220px; place-items: center; color: #fff; background: var(--festive-gradient); font-size: 3rem; }
.destination-summary { display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
</style>
