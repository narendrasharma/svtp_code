<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const settings = computed(() => page.props.siteSettings ?? {});
const siteName = computed(() => settings.value.site_name || 'Travel marketplace');
const logoFailed = ref(false);

watch(() => settings.value.site_logo, () => {
    logoFailed.value = false;
});

const logoUrl = computed(() => {
    const logo = settings.value.site_logo;

    if (!logo || logoFailed.value) {
        return null;
    }

    if (/^https?:\/\//i.test(logo)) {
        return logo;
    }

    return appUrl('/storage/' + String(logo).replace(/^\/?storage\//, ''));
});

const initials = computed(() => siteName.value
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase());
</script>

<template>
    <Link :href="appUrl('/')" class="public-brand" :aria-label="siteName">
        <span v-if="logoUrl" class="public-brand__mark" :class="{ 'public-brand__mark--compact': compact }">
            <img :src="logoUrl" :alt="siteName" class="public-brand__image" @error="logoFailed = true">
        </span>
        <span v-else class="public-brand__mark" aria-hidden="true">{{ initials }}</span>
        <span v-if="!compact" class="public-brand__name">{{ siteName }}</span>
    </Link>
</template>
