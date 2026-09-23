<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'brand' },
});

const safeVariant = computed(() => ['brand', 'accent', 'neutral'].includes(props.variant) ? props.variant : 'neutral');
</script>

<template>
    <span class="public-chip" :class="'public-chip--' + safeVariant">
        <slot />
    </span>
</template>
