<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    iconOnly: { type: Boolean, default: false },
});

const safeVariant = computed(() => ['primary', 'secondary', 'outline', 'ghost', 'text'].includes(props.variant) ? props.variant : 'primary');
const safeSize = computed(() => ['sm', 'md', 'lg'].includes(props.size) ? props.size : 'md');
</script>

<template>
    <button
        :type="type"
        class="public-button"
        :class="[
            'public-button--' + safeVariant,
            'public-button--' + safeSize,
            iconOnly ? 'public-button--icon' : null,
            loading ? 'is-loading' : null,
        ]"
        :disabled="disabled || loading"
    >
        <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <slot v-else />
        <span v-if="loading" class="visually-hidden">Loading</span>
    </button>
</template>
