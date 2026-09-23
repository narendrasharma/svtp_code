<script setup>
import { computed } from 'vue';

const props = defineProps({
    rating: { type: [Number, String], default: null },
    reviewCount: { type: [Number, String], default: 0 },
    label: { type: String, default: null },
});

const numericRating = computed(() => {
    const value = Number(props.rating);
    return Number.isFinite(value) && value > 0 ? Math.min(5, value) : null;
});

const stars = computed(() => Array.from({ length: 5 }, (_, index) => numericRating.value !== null && index < Math.round(numericRating.value)));
</script>

<template>
    <span class="public-rating">
        <span v-if="numericRating !== null" class="public-rating__stars" aria-hidden="true">
            <span v-for="(filled, index) in stars" :key="index">{{ filled ? '★' : '☆' }}</span>
        </span>
        <span v-if="numericRating !== null" class="public-rating__value">{{ numericRating.toFixed(1) }}</span>
        <span v-else class="public-rating__count">{{ label || 'Not rated' }}</span>
        <span v-if="numericRating !== null && Number(reviewCount) > 0" class="public-rating__count">
            ({{ reviewCount }})
        </span>
    </span>
</template>
