<script setup>
const props = defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <button :type="type" class="public-icon-button" :aria-label="label" :disabled="disabled">
        <slot />
    </button>
</template>
