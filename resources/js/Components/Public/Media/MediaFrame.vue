<script setup>
import { computed } from 'vue';

const props = defineProps({
    aspect: { type: String, default: 'default' },
});

const classes = computed(() => ({
    'public-media-frame--portrait': props.aspect === 'portrait',
    'public-media-frame--square': props.aspect === 'square',
    'public-media-frame--editorial': props.aspect === 'editorial',
}));
</script>

<template>
    <div class="public-media-frame" :class="classes">
        <slot />
    </div>
</template>
