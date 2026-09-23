<script setup>
defineProps({
    label: { type: String, default: 'Search' },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <button type="submit" class="public-button public-button--primary public-button--lg" :disabled="disabled || loading">
        <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        <i v-else class="bi bi-search" aria-hidden="true"></i>
        <span>{{ label }}</span>
    </button>
</template>
