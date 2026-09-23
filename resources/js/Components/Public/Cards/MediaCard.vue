<script setup>
import CardShell from './CardShell.vue';
import ImageWithFallback from '../Media/ImageWithFallback.vue';

defineProps({
    image: { type: String, default: null },
    alt: { type: String, default: '' },
    aspect: { type: String, default: 'editorial' },
    interactive: { type: Boolean, default: true },
});
</script>

<template>
    <CardShell :interactive="interactive">
        <ImageWithFallback :src="image" :alt="alt" :aspect="aspect">
            <template #overlay><slot name="overlay" /></template>
        </ImageWithFallback>
        <div class="p-3 p-md-4">
            <slot />
        </div>
    </CardShell>
</template>
