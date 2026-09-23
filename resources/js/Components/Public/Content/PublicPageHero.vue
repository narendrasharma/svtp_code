<script setup>
defineProps({
    eyebrow: { type: String, default: 'Travel marketplace' },
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <section class="public-content-hero">
        <div class="public-container">
            <p class="public-eyebrow">{{ eyebrow }}</p>
            <h1 class="public-heading public-heading--1">{{ title }}</h1>
            <p v-if="description" class="public-content-hero__description">{{ description }}</p>
        </div>
    </section>
</template>

<style scoped>
.public-content-hero {
    padding: clamp(3.75rem, 8vw, 7rem) 0 clamp(2.5rem, 5vw, 4.5rem);
    background:
        radial-gradient(circle at 10% 0%, rgba(203, 126, 74, 0.16), transparent 34%),
        linear-gradient(135deg, #f8f1e8 0%, #fffdf8 62%, #edf3ef 100%);
}

.public-content-hero__description {
    max-width: 42rem;
    margin: 1rem 0 0;
    color: var(--public-muted, #65716d);
    font-size: 1.05rem;
    line-height: 1.7;
}
</style>
