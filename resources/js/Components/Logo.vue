<script setup>
import faviconUrl from '../../images/favicon.svg';
import logoUrl from '../../images/logo.svg';

defineProps({ compact: { type: Boolean, default: false } });
</script>

<template>
    <span class="svtp-logo">
        <img
            :src="compact ? faviconUrl : logoUrl"
            :class="compact ? 'svtp-logo-compact' : 'svtp-logo-image'"
            alt="Shree Vrindavan Tour Packages"
        >
    </span>
</template>
