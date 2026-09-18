<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    groups: { type: Array, default: () => [] },
});

const page = usePage();
const search = ref('');
const openGroups = ref(new Set());

try {
    const stored = JSON.parse(localStorage.getItem('admin-sidebar-open') || '[]');
    if (Array.isArray(stored)) openGroups.value = new Set(stored);
} catch (e) { /* fresh sidebar */ }

function persist() {
    try {
        localStorage.setItem('admin-sidebar-open', JSON.stringify([...openGroups.value]));
    } catch (e) { /* private mode */ }
}

function currentPath() {
    return (page.url || '').split('?')[0];
}

function isActive(url) {
    const current = currentPath();
    if (!url) return false;
    return current === url || current.startsWith(url.endsWith('/') ? url : `${url}/`);
}

function activeGroupKey() {
    let best = null;
    let bestLength = 0;
    props.groups.forEach((group) => {
        (group.items || []).forEach((item) => {
            if (isActive(item.url) && item.url.length > bestLength) {
                bestLength = item.url.length;
                best = group.key;
            }
        });
    });
    return best;
}

function ensureActiveOpen() {
    const active = activeGroupKey();
    if (active && !openGroups.value.has(active)) {
        openGroups.value.add(active);
        persist();
    }
}

ensureActiveOpen();
watch(() => page.url, () => ensureActiveOpen());

function toggleGroup(key) {
    if (openGroups.value.has(key)) openGroups.value.delete(key);
    else openGroups.value.add(key);
    persist();
}

function matches(item, groupLabel) {
    const q = search.value.trim().toLowerCase();
    if (!q) return true;
    return `${item.label} ${groupLabel} ${(item.keywords || []).join(' ')}`.toLowerCase().includes(q);
}

const visibleGroups = computed(() => {
    const q = search.value.trim();
    return props.groups
        .map((group) => ({
            ...group,
            items: (group.items || []).filter((item) => matches(item, group.label)),
        }))
        .filter((group) => group.items.length > 0);
});

// While searching, expand every group that still has matches.
const expanded = computed(() => {
    if (search.value.trim() === '') return openGroups.value;
    return new Set(visibleGroups.value.map((g) => g.key));
});

function isSingleLinkGroup(group) {
    return group.key === 'dashboard' && group.items.length === 1;
}
</script>

<template>
    <div class="admin-sidebar-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input v-model="search" type="search" placeholder="Search menu..." aria-label="Search menu" />
        <kbd v-if="!search">/</kbd>
    </div>

    <nav class="admin-navigation" aria-label="Admin navigation">
        <p v-if="!visibleGroups.length" class="admin-nav-empty">No menu items match "{{ search }}".</p>
        <div v-for="group in visibleGroups" :key="group.key" class="admin-nav-group" :class="{ 'is-open': expanded.has(group.key), 'is-single': isSingleLinkGroup(group) }">
            <template v-if="isSingleLinkGroup(group)">
                <Link :href="appUrl(group.items[0].url)" class="admin-nav-link" :class="{ 'is-active': isActive(group.items[0].url) }">
                    <i class="bi" :class="group.items[0].icon"></i><span>{{ group.items[0].label }}</span>
                </Link>
            </template>
            <template v-else>
                <button type="button" class="admin-nav-group-toggle" :aria-expanded="expanded.has(group.key)" @click="toggleGroup(group.key)">
                    <span class="admin-nav-group-label">{{ group.label }}</span>
                    <span class="admin-nav-group-meta">
                        <span class="admin-nav-count">{{ group.items.length }}</span>
                        <i class="bi" :class="expanded.has(group.key) ? 'bi-chevron-down' : 'bi-chevron-right'"></i>
                    </span>
                </button>
                <div v-show="expanded.has(group.key)" class="admin-nav-group-items">
                    <Link
                        v-for="item in group.items"
                        :key="item.id"
                        :href="appUrl(item.url)"
                        class="admin-nav-link"
                        :class="{ 'is-active': isActive(item.url) }"
                    >
                        <i class="bi" :class="item.icon"></i><span>{{ item.label }}</span>
                    </Link>
                </div>
            </template>
        </div>
    </nav>
</template>

<style scoped>
.admin-sidebar-search { position: relative; margin: 1rem .85rem .5rem; }
.admin-sidebar-search i { position: absolute; left: .7rem; top: 50%; transform: translateY(-50%); color: #64748b; font-size: .85rem; }
.admin-sidebar-search input { width: 100%; padding: .5rem .7rem .5rem 2.1rem; border: 1px solid #263247; border-radius: .6rem; background: #0b1322; color: #e5e7eb; font-size: .85rem; outline: none; }
.admin-sidebar-search input::placeholder { color: #64748b; }
.admin-sidebar-search input:focus { border-color: #f59e0b; box-shadow: 0 0 0 .15rem rgba(245,158,11,.18); }
.admin-sidebar-search kbd { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); padding: 0 .4rem; border: 1px solid #334155; border-radius: .35rem; background: #1e293b; color: #94a3b8; font-size: .7rem; }
.admin-navigation { display: flex; flex: 1; flex-direction: column; gap: .35rem; padding: .5rem .85rem 1rem; overflow-y: auto; }
.admin-nav-empty { padding: .75rem; color: #94a3b8; font-size: .85rem; }
.admin-nav-group { display: flex; flex-direction: column; }
.admin-nav-group-toggle { display: flex; align-items: center; justify-content: space-between; width: 100%; padding: .45rem .8rem; border: 0; background: transparent; color: #94a3b8; font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; border-radius: .5rem; }
.admin-nav-group-toggle:hover { color: #e5e7eb; background: rgba(30,41,59,.5); }
.admin-nav-group-meta { display: inline-flex; align-items: center; gap: .4rem; }
.admin-nav-count { font-size: .68rem; font-weight: 600; padding: 0 .45rem; border-radius: 999px; background: #1e293b; color: #94a3b8; }
.admin-nav-group-items { display: flex; flex-direction: column; gap: .15rem; margin-top: .15rem; }
.admin-nav-link { display: flex; width: 100%; align-items: center; gap: .7rem; padding: .55rem .8rem; border: 0; border-radius: .55rem; background: transparent; color: #cbd5e1; font-size: .88rem; text-align: left; text-decoration: none; transition: background-color .15s ease, color .15s ease; }
.admin-nav-link i { width: 1.2rem; color: #94a3b8; text-align: center; font-size: .95rem; }
.admin-nav-link:hover, .admin-nav-link.is-active { background: #1e293b; color: #fff; }
.admin-nav-link.is-active { box-shadow: inset 3px 0 #f59e0b; }
.admin-nav-link:hover i, .admin-nav-link.is-active i { color: #fbbf24; }
</style>
