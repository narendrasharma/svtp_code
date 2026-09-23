<script setup>
defineProps({
    interactive: { type: Boolean, default: false },
});
</script>

<template>
    <article class="public-card-shell" :class="{ 'public-card-shell--interactive': interactive }">
        <slot />
    </article>
</template>
