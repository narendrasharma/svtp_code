<script setup>
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import SeoHead from '../../SeoHead.vue';

defineProps({
    title: { type: String, required: true },
    intro: { type: String, default: '' },
});
</script>

<template>
    <PublicLayout :show-floating-actions="false" main-class="public-auth-page">
        <SeoHead :title="title" :noindex="true" :private-page="true" />
        <div class="public-container public-auth-shell">
            <aside class="public-auth-story" aria-label="Triparo account">
                <span class="public-eyebrow">TRIPARO ACCOUNT</span>
                <h2>More of the journey, all in one place.</h2>
                <p>Keep your bookings, travel plans and support together wherever you go next.</p>
                <span class="public-auth-story__mark" aria-hidden="true"><i class="bi bi-compass"></i></span>
            </aside>
            <section class="public-auth-card" :aria-label="title">
                <span class="public-eyebrow">YOUR TRIPARO ACCOUNT</span>
                <h1>{{ title }}</h1>
                <p v-if="intro" class="public-auth-card__intro">{{ intro }}</p>
                <slot />
            </section>
        </div>
    </PublicLayout>
</template>
