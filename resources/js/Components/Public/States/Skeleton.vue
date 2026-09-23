<script setup>
defineProps({
    variant: { type: String, default: 'line' },
});
</script>

<template>
    <span class="public-skeleton" :class="{
        'public-skeleton--circle': variant === 'circle',
        'public-skeleton--media': variant === 'media',
    }" aria-hidden="true"></span>
</template>
