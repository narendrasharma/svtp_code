<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';

const props = defineProps({
    panelLabel: { type: String, required: true },
});

const page = usePage();
const siteSettings = computed(() => page.props.siteSettings ?? {});
const siteName = computed(() => siteSettings.value.site_name || 'Travel Platform');
const siteLogo = computed(() => siteSettings.value.site_logo || null);
const logoUrl = computed(() => {
    if (!siteLogo.value) return null;
    return `${appUrl('/storage')}/${siteLogo.value}`;
});
</script>

<template>
    <div class="dashboard-brand" :title="`${siteName} — ${panelLabel}`">
        <div class="dashboard-brand-logo">
            <img
                v-if="logoUrl"
                :src="logoUrl"
                :alt="siteName"
                class="dashboard-brand-img"
            />
            <span v-else class="dashboard-brand-fallback" aria-hidden="true">
                {{ siteName.charAt(0).toUpperCase() }}
            </span>
        </div>
        <div class="dashboard-brand-text">
            <span class="dashboard-brand-name">{{ siteName }}</span>
            <span class="dashboard-brand-panel">{{ panelLabel }}</span>
        </div>
    </div>
</template>

<style scoped>
.dashboard-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    text-decoration: none;
}
.dashboard-brand-logo {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    overflow: hidden;
}
.dashboard-brand-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}
.dashboard-brand-fallback {
    font-weight: 700;
    color: #fbbf24;
    font-size: 1rem;
}
.dashboard-brand-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    line-height: 1.1;
}
.dashboard-brand-name {
    font-size: 0.92rem;
    font-weight: 700;
    color: #f8fafc;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dashboard-brand-panel {
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
}
</style>
