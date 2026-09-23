<script setup>
defineProps({
    title: { type: String, default: 'Nothing here yet' },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="public-empty" role="status">
        <div class="public-empty__icon"><slot name="icon"><i class="bi bi-compass" aria-hidden="true"></i></slot></div>
        <h2 class="public-empty__title">{{ title }}</h2>
        <p v-if="description" class="public-empty__description">{{ description }}</p>
        <slot />
    </div>
</template>
