<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SeoHead from '@/Components/SeoHead.vue';
import { appUrl } from '@/appUrl';

// Props are passed from the controller via Inertia.
const props = defineProps({
    page: {
        type: Object,
        required: true,
    },
});

/**
 * Determine which template to use.
 * At the moment we only have a "default" template, but the switch
 * makes it trivial to add more (full-width, sidebar, landing, …)
 */
const template = computed(() => {
    const allowed = ['default']; // extend this array when new templates are added
    return allowed.includes(props.page.template) ? props.page.template : 'default';
});

/**
 * Canonical URL for SEO – respects any base‑path the app may be served from.
 */
const canonical = computed(() => appUrl(`/${props.page.slug}`));

/**
 * SEO title & description fallback logic.
 * - meta_title → title → page.title
 * - meta_description → excerpt → page.excerpt
 */
const seoTitle = computed(() => {
    return props.page.meta_title || props.page.title;
});

const seoDescription = computed(() => {
    return props.page.meta_description || props.page.excerpt || '';
});
</script>

<template>
    <AppLayout>
        <SeoHead
            :title="seoTitle"
            :description="seoDescription"
            :canonical="canonical"
            :type="'website'"
        />

        <!-- Default template – can be expanded later -->
        <section v-if="template === 'default'" class="container py-5">
            <h1 class="mb-4">{{ props.page.title }}</h1>
            <div v-html="props.page.content"></div>
        </section>

        <!-- Placeholder for future templates -->
        <!--
        <section v-else-if="template === 'full-width'"> … </section>
        <section v-else-if="template === 'sidebar'"> … </section>
        -->
    </AppLayout>
</template>

<style scoped>
/* The project already has global typography styles; we only add minimal spacing. */
.container {
    max-width: 1140px;
}
</style>
