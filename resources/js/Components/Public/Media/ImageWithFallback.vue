<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    aspect: { type: String, default: 'default' },
    loading: { type: String, default: 'lazy' },
    kind: { type: String, default: 'travel' },
    label: { type: String, default: '' },
});

const failed = ref(!props.src);
function onError() {
    failed.value = true;
}

watch(() => props.src, (src) => {
    failed.value = !src;
});
</script>

<template>
    <div class="public-media-frame" :class="{
        'public-media-frame--portrait': aspect === 'portrait',
        'public-media-frame--square': aspect === 'square',
        'public-media-frame--editorial': aspect === 'editorial',
        [`public-media-frame--${kind}`]: kind,
    }">
        <img v-if="!failed" :src="src" :alt="alt" :loading="loading" @error="onError">
        <span v-else class="public-media-frame__fallback" aria-hidden="true">
            <i class="bi" :class="{
                'bi-building': kind === 'hotel',
                'bi-compass': kind === 'tour',
                'bi-signpost-2': kind === 'place' || kind === 'destination',
                'bi-taxi-front-fill': kind === 'taxi',
                'bi-globe2': !['hotel', 'tour', 'place', 'destination', 'taxi'].includes(kind),
            }"></i>
            <span v-if="label" class="public-media-frame__fallback-label">{{ label }}</span>
        </span>
        <div v-if="$slots.overlay" class="public-media-frame__overlay">
            <slot name="overlay" />
        </div>
    </div>
</template>
