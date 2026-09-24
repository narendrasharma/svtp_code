<script setup>
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';

defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <nav v-if="items.length" class="admin-breadcrumbs" aria-label="Breadcrumb">
        <ol>
            <li v-for="(item, index) in items" :key="`${item.label}-${index}`">
                <Link v-if="item.href && index < items.length - 1" :href="appUrl(item.href)">
                    {{ item.label }}
                </Link>
                <span v-else aria-current="page">{{ item.label }}</span>
                <i v-if="index < items.length - 1" class="bi bi-chevron-right" aria-hidden="true"></i>
            </li>
        </ol>
    </nav>
</template>

<style scoped>
.admin-breadcrumbs {
    padding: 1rem 1.75rem 0;
    color: var(--admin-text-muted);
    font-size: .82rem;
}

.admin-breadcrumbs ol {
    display: flex;
    align-items: center;
    gap: .55rem;
    min-width: 0;
    margin: 0;
    padding: 0;
    list-style: none;
    overflow-x: auto;
    white-space: nowrap;
}

.admin-breadcrumbs li {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
}

.admin-breadcrumbs a {
    color: var(--admin-link);
    text-decoration: none;
}

.admin-breadcrumbs a:hover,
.admin-breadcrumbs a:focus-visible {
    color: var(--admin-link-hover);
    text-decoration: underline;
}

.admin-breadcrumbs i {
    color: var(--admin-text-muted);
    font-size: .68rem;
}

@media (max-width: 991.98px) {
    .admin-breadcrumbs { padding: .85rem 1rem 0; }
}

:dir(rtl) .admin-breadcrumbs i { transform: rotate(180deg); }
</style>
