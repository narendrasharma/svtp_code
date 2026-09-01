<script setup>
import { ref } from 'vue';
import { appUrl } from '../appUrl';
import { categoryImage } from '../festiveAssets';

const props = defineProps({ pkg: { type: Object, required: true } });

const loaded = ref(false);
const imgSrc = props.pkg.cover_image || categoryImage(props.pkg.category);

const festivalTags = ['festival', 'holi'];
const isFestivalTagged = festivalTags.includes(props.pkg.category) || props.pkg.is_featured;
</script>

<template>
    <div class="package-card fade-in-up">
        <div class="package-card-header lazy-img">
            <span v-if="isFestivalTagged" class="festival-badge">
                <i class="bi bi-stars"></i> Featured
            </span>
            <span class="rating-pill" v-if="pkg.rating">
                <i class="bi bi-star-fill text-warning"></i> {{ pkg.rating }}
            </span>
            <img
                :src="imgSrc"
                :alt="pkg.title"
                loading="lazy"
                :class="{ 'is-loaded': loaded }"
                @load="loaded = true"
            />
            <div class="card-title-wrap">
                <h5 class="mb-0 text-white">{{ pkg.title }}</h5>
            </div>
        </div>
        <div class="package-card-body">
            <p class="text-muted small mb-2">
                <i class="bi bi-calendar3 me-1"></i>{{ pkg.duration_days }} Days / {{ pkg.duration_nights }} Nights
            </p>
            <div class="d-flex align-items-baseline gap-2 mb-3">
                <span v-if="pkg.discounted_price" class="price-tag-strike">₹{{ pkg.price }}</span>
                <span class="price-tag">₹{{ pkg.discounted_price || pkg.price }}</span>
                <span class="small text-muted">/ person</span>
            </div>
            <a :href="`${appUrl('/packages')}/${pkg.slug}`" class="btn btn-svtp w-100">View Itinerary</a>
        </div>
    </div>
</template>
