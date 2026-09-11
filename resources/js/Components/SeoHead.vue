<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';

const props = defineProps({
    title: {
        type: String,
        default: null,
    },

    description: {
        type: String,
        default: null,
    },

    image: {
        type: String,
        default: null,
    },

    canonical: {
        type: String,
        default: null,
    },

    type: {
        type: String,
        default: 'website',
    },

    noindex: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const settings = computed(() => {
    return page.props.siteSettings ?? {};
});

const finalTitle = computed(() => {
    const siteName = settings.value.site_name ?? '';

    if (props.title) {
        return siteName
            ? `${props.title} | ${siteName}`
            : props.title;
    }

    return (
        settings.value.seo_meta_title ||
        siteName
    );
});

const finalDescription = computed(() => {
    return (
        props.description ||
        settings.value.seo_meta_description ||
        ''
    );
});



function normalizeImage(image) {
    if (!image) {
        return null;
    }

    if (
        image.startsWith('http://') ||
        image.startsWith('https://')
    ) {
        return image;
    }

    const cleanPath = image.replace(/^\/?storage\//, '');

    return `${appUrl('/storage')}/${cleanPath}`;
}

const finalImage = computed(() => {
    if (props.image) {
        return normalizeImage(props.image);
    }

    if (settings.value.seo_og_image) {
        return normalizeImage(settings.value.seo_og_image);
    }

    if (settings.value.site_logo) {
        return normalizeImage(settings.value.site_logo);
    }

    return null;
});

const robots = computed(() => {
    if (props.noindex) {
        return 'noindex,nofollow';
    }

    const index = settings.value.seo_index === false
        ? 'noindex'
        : 'index';

    const follow = settings.value.seo_follow === false
        ? 'nofollow'
        : 'follow';

    return `${index},${follow}`;
});
</script>

<template>
    <Head>
        <title>{{ finalTitle }}</title>

        <meta
            v-if="finalDescription"
            head-key="description"
            name="description"
            :content="finalDescription"
        >

        <meta
            head-key="robots"
            name="robots"
            :content="robots"
        >

        <!-- Open Graph -->
        <meta
            head-key="og:title"
            property="og:title"
            :content="finalTitle"
        >

        <meta
            v-if="finalDescription"
            head-key="og:description"
            property="og:description"
            :content="finalDescription"
        >

        <meta
            head-key="og:type"
            property="og:type"
            :content="type"
        >

        <meta
            v-if="finalImage"
            head-key="og:image"
            property="og:image"
            :content="finalImage"
        >

        <!-- Twitter/X -->
        <meta
            head-key="twitter:card"
            name="twitter:card"
            content="summary_large_image"
        >

        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="finalTitle"
        >

        <meta
            v-if="finalDescription"
            head-key="twitter:description"
            name="twitter:description"
            :content="finalDescription"
        >

        <meta
            v-if="finalImage"
            head-key="twitter:image"
            name="twitter:image"
            :content="finalImage"
        >

        <link
            v-if="canonical"
            head-key="canonical"
            rel="canonical"
            :href="canonical"
        >
    </Head>
</template>
