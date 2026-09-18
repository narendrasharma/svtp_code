<script setup>
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, ref, watch } from 'vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    open: { type: Boolean, default: false },
    navigation: { type: Array, default: () => [] },
    quickActions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const query = ref('');
const serverGroups = ref([]);
const loading = ref(false);
const activeIndex = ref(0);
const input = ref(null);
let debounce = null;
let requestSeq = 0;

const navMatches = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (q.length < 1) return [];
    const matches = [];
    props.navigation.forEach((group) => {
        (group.items || []).forEach((item) => {
            if (matches.length >= 6) return;
            const haystack = `${item.label} ${item.group} ${(item.keywords || []).join(' ')}`.toLowerCase();
            if (haystack.includes(q)) {
                matches.push({ type: 'navigation', label: item.label, subtitle: `${item.group} → ${item.label}`, url: item.url, icon: item.icon || 'bi-link' });
            }
        });
    });
    return matches;
});

const flatResults = computed(() => {
    const flat = [];
    if (query.value.trim() === '') {
        props.quickActions.forEach((action) => flat.push({ section: 'Quick actions', type: 'action', label: action.label, subtitle: 'Create new', url: action.url, icon: action.icon || 'bi-plus-circle' }));
        return flat;
    }
    if (navMatches.value.length) {
        navMatches.value.forEach((item) => flat.push({ section: 'Navigation', ...item }));
    }
    serverGroups.value.forEach((group) => {
        (group.items || []).forEach((item) => flat.push({ section: group.label, ...item }));
    });
    return flat.slice(0, 30);
});

async function runSearch(q) {
    const trimmed = q.trim();
    if (trimmed.length < 2) {
        serverGroups.value = [];
        return;
    }
    clearTimeout(debounce);
    debounce = setTimeout(async () => {
        const seq = ++requestSeq;
        loading.value = true;
        try {
            const response = await axios.get(appUrl('/admin/search'), { params: { q: trimmed } });
            if (seq !== requestSeq) return;
            // Navigation already matches locally — keep server payload
            // focused on entity results.
            serverGroups.value = (response.data.groups || []).filter((g) => g.key !== 'navigation');
            activeIndex.value = 0;
        } catch (e) {
            if (seq !== requestSeq) return;
            serverGroups.value = [];
        } finally {
            if (seq === requestSeq) loading.value = false;
        }
    }, 250);
}

watch(query, (q) => {
    activeIndex.value = 0;
    runSearch(q);
});

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        serverGroups.value = [];
        activeIndex.value = 0;
        nextTick(() => input.value?.focus());
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

function close() {
    emit('close');
}

function go(item) {
    if (!item) return;
    close();
    router.visit(appUrl(item.url));
}

function onKeydown(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (flatResults.value.length) activeIndex.value = (activeIndex.value + 1) % flatResults.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (flatResults.value.length) activeIndex.value = (activeIndex.value - 1 + flatResults.value.length) % flatResults.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        go(flatResults.value[activeIndex.value]);
    } else if (event.key === 'Escape') {
        close();
    }
}

function groupedResults() {
    const groups = [];
    const bySection = new Map();
    flatResults.value.forEach((item, index) => {
        if (!bySection.has(item.section)) {
            bySection.set(item.section, []);
            groups.push({ section: item.section, items: bySection.get(item.section) });
        }
        bySection.get(item.section).push({ ...item, flatIndex: index });
    });
    return groups;
}
</script>

<template>
    <div v-if="open" class="cmd-backdrop" @click.self="close">
        <div class="cmd-palette" role="dialog" aria-modal="true" aria-label="Global admin search">
            <div class="cmd-input-row">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input
                    ref="input"
                    v-model="query"
                    type="text"
                    placeholder="Search anything — try a booking reference, a name, or “SEO”..."
                    aria-label="Search anything"
                    autocomplete="off"
                    @keydown="onKeydown"
                />
                <span v-if="loading" class="spinner-border spinner-border-sm text-muted" role="status" aria-label="Searching"></span>
                <kbd v-else>ESC</kbd>
            </div>
            <div class="cmd-results">
                <p v-if="query.trim() !== '' && query.trim().length < 2" class="cmd-hint">Keep typing — entity search starts at 2 characters.</p>
                <p v-else-if="!flatResults.length && !loading" class="cmd-hint">No results for "{{ query }}".</p>
                <div v-for="group in groupedResults()" :key="group.section" class="cmd-group">
                    <p class="cmd-section">{{ group.section }}</p>
                    <button
                        v-for="item in group.items"
                        :key="`${item.section}-${item.label}-${item.url}`"
                        type="button"
                        class="cmd-item"
                        :class="{ 'is-active': item.flatIndex === activeIndex }"
                        @mouseenter="activeIndex = item.flatIndex"
                        @click="go(item)"
                    >
                        <i class="bi" :class="item.icon" aria-hidden="true"></i>
                        <span class="cmd-item-text">
                            <span class="cmd-item-label">{{ item.label }}</span>
                            <small class="cmd-item-sub">{{ item.subtitle }}</small>
                        </span>
                        <i class="bi bi-arrow-return-left cmd-enter" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="cmd-footer">
                <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
                <span><kbd>↵</kbd> open</span>
                <span><kbd>esc</kbd> close</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.cmd-backdrop { position: fixed; inset: 0; z-index: 2000; display: flex; justify-content: center; align-items: flex-start; padding: 12vh 1rem 1rem; background: rgba(2,6,23,.7); backdrop-filter: blur(2px); }
.cmd-palette { width: 100%; max-width: 620px; max-height: 70vh; display: flex; flex-direction: column; overflow: hidden; border: 1px solid #334155; border-radius: .9rem; background: #0f172a; box-shadow: 0 24px 70px rgba(0,0,0,.6); color: #e5e7eb; }
.cmd-input-row { display: flex; align-items: center; gap: .7rem; padding: .9rem 1rem; border-bottom: 1px solid #263247; }
.cmd-input-row i { color: #64748b; }
.cmd-input-row input { flex: 1; border: 0; outline: 0; background: transparent; color: #fff; font-size: 1rem; }
.cmd-input-row input::placeholder { color: #64748b; }
.cmd-input-row kbd { padding: .1rem .45rem; border: 1px solid #334155; border-radius: .35rem; background: #1e293b; color: #94a3b8; font-size: .7rem; }
.cmd-results { overflow-y: auto; padding: .5rem; min-height: 120px; }
.cmd-hint { padding: 1rem; color: #94a3b8; font-size: .88rem; }
.cmd-section { margin: .6rem .5rem .3rem; color: #64748b; font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
.cmd-item { display: flex; align-items: center; gap: .75rem; width: 100%; padding: .55rem .7rem; border: 0; border-radius: .55rem; background: transparent; color: #e5e7eb; text-align: left; }
.cmd-item i { color: #94a3b8; }
.cmd-item.is-active { background: #1e293b; }
.cmd-item.is-active i { color: #fbbf24; }
.cmd-item-text { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.cmd-item-label { font-weight: 600; font-size: .92rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cmd-item-sub { color: #94a3b8; }
.cmd-enter { opacity: 0; }
.cmd-item.is-active .cmd-enter { opacity: 1; }
.cmd-footer { display: flex; gap: 1rem; padding: .6rem 1rem; border-top: 1px solid #263247; color: #64748b; font-size: .75rem; }
.cmd-footer kbd { padding: 0 .35rem; border: 1px solid #334155; border-radius: .3rem; background: #1e293b; margin-right: .25rem; }
</style>
