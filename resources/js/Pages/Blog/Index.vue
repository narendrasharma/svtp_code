<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import PublicPageHero from '../../Components/Public/Content/PublicPageHero.vue';
import SeoHead from '../../Components/SeoHead.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    posts: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout>
        <SeoHead
            title="Travel journal"
            description="Read published travel guides and destination stories from this marketplace."
            :canonical="appUrl('/blog')"
            :noindex="!posts.length"
        />
        <PublicPageHero
            eyebrow="Ideas for the journey"
            title="Travel journal"
            description="Published guides and destination stories will gather here as the journal grows."
        />
        <main class="public-container py-5">
            <div v-if="posts.length" class="row g-4">
                <article v-for="post in posts" :key="post.id || post.slug" class="col-md-6 col-xl-4">
                    <div class="public-content-panel h-100">
                        <img v-if="post.image" :src="post.image" :alt="post.title" class="blog-image" loading="lazy">
                        <p v-if="post.published_at" class="public-eyebrow mt-3 mb-2">{{ post.published_at }}</p>
                        <h2 class="public-heading public-heading--3">{{ post.title }}</h2>
                        <p v-if="post.excerpt" class="text-muted">{{ post.excerpt }}</p>
                        <a v-if="post.url" :href="post.url" class="public-button public-button--outline public-button--sm">Read article</a>
                    </div>
                </article>
            </div>
            <EmptyState v-else title="The journal is being prepared" description="Published articles will appear here when the marketplace has a blog collection to share.">
                <template #icon><i class="bi bi-journal-richtext" aria-hidden="true"></i></template>
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

.blog-image {
    width: 100%;
    height: 12rem;
    border-radius: 0.9rem;
    object-fit: cover;
}
</style>
