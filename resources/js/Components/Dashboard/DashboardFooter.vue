<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'admin' }, // admin | vendor
});

const page = usePage();
const siteName = computed(() => page.props.siteSettings?.site_name || 'Travel Platform');
const year = computed(() => new Date().getFullYear());
// Config-driven version; fallback to hardcoded if not shared
const version = computed(() => page.props.platformVersion || '1.0.0');
const productName = computed(() => page.props.platformName || 'Travel Platform');
</script>

<template>
    <footer class="dashboard-footer">
        <div class="dashboard-footer-inner">
            <span>© {{ year }} {{ siteName }}. All rights reserved.</span>
            <span class="dashboard-footer-version">{{ productName }} v{{ version }}</span>
        </div>
    </footer>
</template>

<style scoped>
.dashboard-footer {
    border-top: 1px solid #263247;
    background: #0c1220;
    padding: 0.85rem 1.5rem;
    margin-top: auto;
}
.dashboard-footer-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    font-size: 0.72rem;
    color: #94a3b8;
}
.dashboard-footer-version {
    color: #cbd5e1;
    font-weight: 500;
    white-space: nowrap;
}
@media (max-width: 575px) {
    .dashboard-footer-inner {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 0.25rem;
    }
}
</style>
