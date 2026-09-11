<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import faviconUrl from '../../images/favicon.svg';
import logoUrl from '../../images/logo.svg';
import { appUrl } from '../appUrl';

const props = defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const settings = computed(() => page.props.siteSettings ?? {});

const uploadedLogo = computed(() => {
    if (!settings.value.site_logo) {
        return null;
    }

    return `${appUrl('/storage')}/${settings.value.site_logo}`;
});

const uploadedFavicon = computed(() => {
    if (!settings.value.site_favicon) {
        return null;
    }

    return `${appUrl('/storage')}/${settings.value.site_favicon}`;
});

const imageUrl = computed(() => {
    if (props.compact) {
        return uploadedFavicon.value || faviconUrl;
    }

    return uploadedLogo.value || logoUrl;
});

const siteName = computed(() => {
    return settings.value.site_name || 'Your Website Name';
});
</script>

<template>
    <span class="svtp-logo">
        <img
            :src="imageUrl"
            :class="compact ? 'svtp-logo-compact' : 'svtp-logo-image'"
            :alt="siteName"
        >
    </span>
</template>
