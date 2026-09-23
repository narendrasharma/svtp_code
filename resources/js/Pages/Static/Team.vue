<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import PublicPageHero from '../../Components/Public/Content/PublicPageHero.vue';
import SeoHead from '../../Components/SeoHead.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    members: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Our team"
            description="Meet the people who support the marketplace and its traveller experiences."
            :canonical="appUrl('/our-team')"
            :noindex="true"
        />
        <PublicPageHero eyebrow="The people behind the platform" title="Our team" description="Meet the people who support the marketplace and its traveller experiences." />

        <main class="public-container py-5">
            <div v-if="members.length" class="row g-4">
                <div v-for="member in members" :key="member.id || member.name" class="col-sm-6 col-lg-4">
                    <article class="public-content-panel h-100">
                        <div v-if="member.image" class="team-image mb-3"><img :src="member.image" :alt="member.name" loading="lazy"></div>
                        <p class="public-eyebrow mb-2">{{ member.role || 'Marketplace team' }}</p>
                        <h2 class="public-heading public-heading--3">{{ member.name }}</h2>
                        <p v-if="member.bio" class="mb-0 text-muted">{{ member.bio }}</p>
                    </article>
                </div>
            </div>
            <EmptyState v-else :title="'Team profiles are being prepared'" description="Public team profiles will appear here when they are published by the marketplace.">
                <template #icon><i class="bi bi-people" aria-hidden="true"></i></template>
            </EmptyState>
        </main>
    </AppLayout>
</template>

<style scoped>
.public-content-panel {
    padding: 1.5rem;
    border: 1px solid rgba(31, 72, 67, 0.1);
    border-radius: 1.25rem;
    background: #fffdf8;
}

.team-image img {
    display: block;
    width: 100%;
    height: 15rem;
    object-fit: cover;
}
</style>
