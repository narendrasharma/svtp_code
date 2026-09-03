<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';
import { categoryImage } from '../festiveAssets';

const props = defineProps({ pkg: { type: Object, required: true } });

const loaded = ref(false);
const tour = computed(() => props.pkg);
const imgSrc = computed(() => {
    const galleryImage = Array.isArray(tour.value.gallery)
        ? tour.value.gallery.find((image) => typeof image === 'string' && image.length)
        : null;
    const category = typeof tour.value.category === 'string'
        ? tour.value.category
        : tour.value.category?.slug;

    //return tour.value.cover_image || galleryImage || categoryImage(category);
    return tour.value.cover_image || galleryImage || appUrl('/storage/images/default.svg');

});
const packageUrl = computed(() => {
    const slug = typeof tour.value.slug === 'string' ? tour.value.slug.trim() : '';

    return slug ? appUrl(`/packages/${encodeURIComponent(slug)}`) : null;
});

const isFestivalTagged = computed(() => tour.value.is_featured);
</script>

<template>
    <div class="package-card fade-in-up">
        <div class="package-card-header lazy-img">
            <span v-if="isFestivalTagged" class="festival-badge">
                <i class="bi bi-stars"></i> Featured
            </span>
            <span class="rating-pill" v-if="tour.approved_reviews_avg_rating">
                <i class="bi bi-star-fill text-warning"></i> {{ tour.approved_reviews_avg_rating }}
            </span>
            <img
                :src="imgSrc"
                :alt="tour.title"
                loading="lazy"
                :class="{ 'is-loaded': loaded }"
                @load="loaded = true"
            />
            <div class="card-title-wrap">
                <h5 class="mb-0 text-white">{{ tour.title }}</h5>
            </div>
        </div>
        <div class="package-card-body">
            <p v-if="tour.city?.name" class="text-muted small mb-2">
                <i class="bi bi-geo-alt me-1"></i>{{ tour.city.name }}
            </p>
            <p class="text-muted small mb-2">
                <i class="bi bi-calendar3 me-1"></i>{{ tour.duration_days }} Days / {{ tour.duration_nights }} Nights
            </p>
            <div class="d-flex align-items-baseline gap-2 mb-3">
                <span v-if="tour.discounted_price" class="price-tag-strike">₹{{ tour.price }}</span>
                <span class="price-tag">₹{{ tour.discounted_price || tour.price }}</span>
                <span class="small text-muted">/ person</span>
            </div>
            <Link v-if="packageUrl" :href="packageUrl" class="btn btn-svtp w-100">View Itinerary</Link>
            <span v-else class="btn btn-svtp w-100 disabled" aria-disabled="true">View Itinerary</span>
        </div>
    </div>
</template>
