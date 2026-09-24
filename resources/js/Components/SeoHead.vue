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

    privatePage: {
        type: Boolean,
        default: false,
    },

    structuredData: {
        type: Object,
        default: null,
    },

    // Phase 13A locale contract: explicit overrides win, otherwise the
    // shared localization prop / localizedSeo payload supplies them.
    locale: {
        type: String,
        default: null,
    },

    alternates: {
        type: Array,
        default: () => [],
    },
});

const structuredJson = computed(() => props.structuredData
    ? JSON.stringify(props.structuredData).replace(/</g, '\\u003c').replace(/>/g, '\\u003e').replace(/&/g, '\\u0026')
    : null);

const page = usePage();

const localization = computed(() => page.props.localization ?? {});
const localizedSeo = computed(() => page.props.localizedSeo ?? {});

const currentLocale = computed(() => {
    return props.locale
        || localizedSeo.value.locale
        || localization.value.locale
        || 'en';
});

const hreflangLinks = computed(() => {
    // Only emit hreflang for real, validated alternate URLs. Phase 13A
    // defers locale-prefixed routes, so this is [] until then.
    const fromProp = Array.isArray(props.alternates) ? props.alternates : [];
    const fromSeo = Array.isArray(localizedSeo.value.hreflang) ? localizedSeo.value.hreflang : [];
    const merged = [...fromProp, ...fromSeo];

    return merged.filter((entry) => entry
        && typeof entry.locale === 'string'
        && typeof entry.url === 'string'
        && entry.locale !== ''
        && /^(https?:\/\/|\/)/i.test(entry.url));
});

const settings = computed(() => {
    return page.props.siteSettings ?? {};
});

const finalTitle = computed(() => {
    const siteName = props.privatePage ? 'Triparo' : (settings.value.site_name ?? '');

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
        <component :is="'script'" v-if="structuredJson" type="application/ld+json" head-key="structured-data" v-html="structuredJson" />

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

        <meta
            v-if="privatePage"
            head-key="referrer"
            name="referrer"
            content="no-referrer"
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

        <meta
            head-key="og:locale"
            property="og:locale"
            :content="currentLocale"
        >

        <link
            v-for="entry in hreflangLinks"
            :key="`hreflang-${entry.locale}`"
            :head-key="`hreflang-${entry.locale}`"
            rel="alternate"
            :hreflang="entry.locale"
            :href="entry.url"
        >
    </Head>
</template>
