<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import PublicPageHero from '../../Components/Public/Content/PublicPageHero.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    page: { type: Object, default: () => ({}) },
});
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="page.meta_title || page.title || 'Frequently asked questions'"
            :description="page.meta_description || 'Answers about using this travel marketplace.'"
            :canonical="$page.props.localizedSeo?.canonical"
            :noindex="!page.content_available"
        />
        <PublicPageHero
            eyebrow="Help and guidance"
            :title="page.title || 'Frequently asked questions'"
            :description="page.excerpt || 'Answers will appear here as the marketplace publishes its help content.'"
        />
        <main class="public-container py-5">
            <article v-if="page.content_available && page.content" class="public-rich-content" v-html="page.content"></article>
            <EmptyState
                v-else
                title="Help content is being prepared"
                description="Published answers will appear here when this marketplace has FAQ content to share."
            >
                <template #icon><i class="bi bi-chat-square-text" aria-hidden="true"></i></template>
            </EmptyState>
        </main>
    </AppLayout>
</template>
