<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/Public/States/EmptyState.vue';
import PublicPageHero from '../../Components/Public/Content/PublicPageHero.vue';
import SeoHead from '../../Components/SeoHead.vue';

const props = defineProps({
    page: { type: Object, default: () => ({}) },
    noindex: { type: Boolean, default: true },
});
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="page.meta_title || page.title || 'Privacy policy'"
            :description="page.meta_description || 'The privacy policy for this marketplace has not been published yet.'"
            :canonical="$page.props.localizedSeo?.canonical"
            :noindex="noindex"
        />
        <PublicPageHero
            eyebrow="Marketplace information"
            :title="page.title || 'Privacy policy'"
            :description="page.excerpt || 'The privacy policy for this marketplace has not been published yet.'"
        />
        <main class="public-container py-5">
            <article v-if="page.content_available && page.content" class="public-rich-content" v-html="page.content"></article>
            <EmptyState v-else title="Content is not published yet" description="The privacy policy for this marketplace has not been published yet.">
                <template #icon><i class="bi bi-shield-check" aria-hidden="true"></i></template>
            </EmptyState>
        </main>
    </AppLayout>
</template>
