<script setup>
import { computed } from 'vue';

const props = defineProps({
    size: { type: String, default: 'content' },
    tag: { type: String, default: 'div' },
});

const classes = computed(() => [
    'public-container',
    props.size === 'wide' ? 'public-container--wide' : null,
    props.size === 'full' ? 'public-container--full' : null,
]);
</script>

<template>
    <component :is="tag" :class="classes">
        <slot />
    </component>
</template>
