<script setup>
defineProps({ rating: { type: Number, default: 0 } });
</script>

<template>
    <span>
        <i v-for="n in 5" :key="n" class="bi" :class="n <= Math.round(rating) ? 'bi-star-fill text-warning' : 'bi-star text-muted'"></i>
    </span>
</template>
