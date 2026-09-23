<script setup>
defineProps({
    title: { type: String, default: 'Something went wrong' },
    description: { type: String, default: 'Please try again in a moment.' },
});
</script>

<template>
    <div class="public-error" role="alert">
        <div class="public-error__icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
        <h2 class="public-error__title">{{ title }}</h2>
        <p class="public-error__description">{{ description }}</p>
        <slot />
    </div>
</template>
