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
            :title="page.meta_title || page.title || 'Terms and conditions'"
            :description="page.meta_description || 'The terms and conditions for this marketplace have not been published yet.'"
            :canonical="$page.props.localizedSeo?.canonical"
            :noindex="noindex"
        />
        <PublicPageHero
            eyebrow="Marketplace information"
            :title="page.title || 'Terms and conditions'"
            :description="page.excerpt || 'The terms and conditions for this marketplace have not been published yet.'"
        />
        <main class="public-container py-5">
            <article v-if="page.content_available && page.content" class="public-rich-content" v-html="page.content"></article>
            <EmptyState v-else title="Content is not published yet" description="The terms and conditions for this marketplace have not been published yet.">
                <template #icon><i class="bi bi-file-earmark-text" aria-hidden="true"></i></template>
            </EmptyState>
        </main>
    </AppLayout>
</template>
