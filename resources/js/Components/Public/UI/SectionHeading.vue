<script setup>
import { computed } from 'vue';

const props = defineProps({
    eyebrow: { type: String, default: null },
    title: { type: String, required: true },
    description: { type: String, default: null },
    variant: { type: String, default: 'editorial' },
    align: { type: String, default: 'start' },
});

const safeVariant = computed(() => ['editorial', 'hairline', 'minimal', 'heritage', 'immersive'].includes(props.variant)
    ? props.variant
    : 'editorial');
</script>

<template>
    <header
        class="public-section-heading"
        :class="[
            'public-section-heading--' + safeVariant,
            align === 'center' ? 'public-section-heading--center' : null,
        ]"
    >
        <span v-if="eyebrow" class="public-eyebrow">{{ eyebrow }}</span>
        <h2 class="public-section-heading__title">{{ title }}</h2>
        <p v-if="description" class="public-section-heading__description">{{ description }}</p>
        <span class="public-section-heading__rule" aria-hidden="true"></span>
    </header>
</template>
