<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';

defineProps({ compact: { type: Boolean, default: false } });

const page = usePage();
const settings = computed(() => page.props.siteSettings ?? {});
const logoFailed = ref(false);

watch(() => settings.value.site_logo, () => {
    logoFailed.value = false;
});

const logoUrl = computed(() => {
    const logo = String(settings.value.site_logo ?? '').trim();

    if (!logo || logoFailed.value) return null;
    if (/^https?:\/\//i.test(logo)) return logo;

    return appUrl(`/storage/${logo.replace(/^\/?(?:storage\/)?/, '')}`);
});
</script>

<template>
    <Link :href="appUrl('/')" class="public-brand" aria-label="Triparo home">
        <img v-if="logoUrl" :src="logoUrl" alt="Site logo" class="public-brand__image" @error="logoFailed = true">
        <template v-else>
            <span class="public-brand__mark" aria-hidden="true">T</span>
            <span v-if="!compact" class="public-brand__name">Triparo</span>
        </template>
    </Link>
</template>
