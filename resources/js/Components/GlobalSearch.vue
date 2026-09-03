<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { appUrl } from '../appUrl';

const searchRoot = ref(null);
const searchInput = ref(null);
const isOpen = ref(false);
const isLoading = ref(false);
const query = ref('');
const results = ref({ tours: [], destinations: [], places: [] });
let debounceTimer;
let activeRequest;

const resultGroups = computed(() => [
    { key: 'tours', label: 'Tours', icon: 'bi-map', items: results.value.tours },
    { key: 'destinations', label: 'Destinations', icon: 'bi-geo-alt', items: results.value.destinations },
    { key: 'places', label: 'Places & Attractions', icon: 'bi-bank', items: results.value.places },
].filter((group) => group.items.length));

const hasSearched = computed(() => query.value.trim().length >= 2);

async function openSearch() {
    isOpen.value = true;
    await nextTick();
    searchInput.value?.focus();
}

function closeSearch() {
    isOpen.value = false;
    activeRequest?.abort();
}

function handleFocusOut(event) {
    if (!searchRoot.value?.contains(event.relatedTarget)) {
        closeSearch();
    }
}

function handleOutsidePointer(event) {
    if (isOpen.value && !searchRoot.value?.contains(event.target)) {
        closeSearch();
    }
}

function imageUrl(image) {
    if (!image) return null;
    if (/^(https?:|data:|blob:)/i.test(image)) return image;

    return appUrl(image.startsWith('/') ? image : `/${image}`);
}

function hideBrokenImage(event) {
    event.currentTarget.hidden = true;
}

function scheduleSearch() {
    window.clearTimeout(debounceTimer);
    activeRequest?.abort();

    if (!hasSearched.value) {
        results.value = { tours: [], destinations: [], places: [] };
        isLoading.value = false;
        return;
    }

    debounceTimer = window.setTimeout(runSearch, 250);
}

async function runSearch() {
    const requestController = new AbortController();
    activeRequest = requestController;
    isLoading.value = true;

    try {
        const response = await fetch(`${appUrl('/search')}?q=${encodeURIComponent(query.value.trim())}`, {
            headers: { Accept: 'application/json' },
            signal: requestController.signal,
        });

        if (response.ok) {
            results.value = await response.json();
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            results.value = { tours: [], destinations: [], places: [] };
        }
    } finally {
        if (activeRequest === requestController) {
            isLoading.value = false;
        }
    }
}

onMounted(() => document.addEventListener('pointerdown', handleOutsidePointer));
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleOutsidePointer);
    window.clearTimeout(debounceTimer);
    activeRequest?.abort();
});
</script>

<template>
    <div ref="searchRoot" class="global-search ms-1" @focusout="handleFocusOut">
        <button
            v-if="!isOpen"
            type="button"
            class="global-search-trigger"
            aria-label="Open website search"
            @click="openSearch"
        >
            <i class="bi bi-search" aria-hidden="true"></i>
        </button>

        <Transition name="search-expand">
            <div v-if="isOpen" class="global-search-panel">
                <div class="global-search-input-wrap">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="search"
                        placeholder="Search tours, destinations, places..."
                        aria-label="Search the website"
                        autocomplete="off"
                        @input="scheduleSearch"
                        @keydown.escape="closeSearch"
                    />
                    <span v-if="isLoading" class="spinner-border spinner-border-sm" aria-label="Searching"></span>
                    <button v-else type="button" aria-label="Close search" @click="closeSearch">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>

                <div v-if="hasSearched" class="global-search-results" aria-live="polite">
                    <template v-if="resultGroups.length">
                        <section v-for="group in resultGroups" :key="group.key" class="global-search-group">
                            <p>
                                <span><i class="bi me-2" :class="group.icon" aria-hidden="true"></i>{{ group.label }}</span>
                                <small>{{ group.items.length }}</small>
                            </p>
                            <Link
                                v-for="item in group.items"
                                :key="`${group.key}-${item.id}`"
                                :href="appUrl(item.url)"
                                class="global-search-result"
                                @click="closeSearch"
                            >
                                <img
                                    v-if="item.image"
                                    :src="imageUrl(item.image)"
                                    :alt="item.title"
                                    loading="lazy"
                                    @error="hideBrokenImage"
                                />
                                <span v-else class="global-search-result-icon" aria-hidden="true">
                                    <i class="bi" :class="group.icon"></i>
                                </span>
                                <span class="global-search-result-copy">
                                    <span class="global-search-result-heading">
                                        <strong>{{ item.title }}</strong>
                                        <small>{{ item.type }}</small>
                                    </span>
                                    <span v-if="item.context" class="global-search-result-context">{{ item.context }}</span>
                                    <span v-if="item.subtitle" class="global-search-result-meta">{{ item.subtitle }}</span>
                                </span>
                            </Link>
                        </section>
                    </template>
                    <p v-else-if="!isLoading" class="global-search-empty mb-0">No matching tours or places found.</p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.global-search { position: relative; z-index: 1050; }
.global-search-trigger { display: grid; width: 2.5rem; height: 2.5rem; place-items: center; border: 1px solid rgba(107, 16, 41, 0.16); border-radius: 50%; background: #fff; color: var(--maroon); transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease; }
.global-search-trigger:hover { border-color: var(--gulal); box-shadow: 0 0.4rem 1rem rgba(107, 16, 41, 0.12); transform: translateY(-1px); }
.global-search-panel { position: absolute; top: 50%; right: 0; width: min(28rem, calc(100vw - 1.5rem)); transform: translateY(-50%); }
.global-search-input-wrap { display: flex; align-items: center; gap: 0.65rem; padding: 0.65rem 0.85rem; border: 1px solid rgba(107, 16, 41, 0.18); border-radius: 999px; background: #fff; box-shadow: 0 0.75rem 2rem rgba(36, 6, 19, 0.17); }
.global-search-input-wrap > i { color: var(--gulal-deep); }
.global-search-input-wrap input { min-width: 0; flex: 1; border: 0; outline: 0; background: transparent; color: var(--ink); }
.global-search-input-wrap button { border: 0; background: transparent; color: var(--maroon); }
.global-search-results { position: absolute; top: calc(100% + 0.55rem); right: 0; width: 100%; max-height: min(65vh, 30rem); overflow-y: auto; padding: 0.45rem; border: 1px solid rgba(107, 16, 41, 0.1); border-radius: 1rem; background: #fff; box-shadow: 0 1.25rem 3rem rgba(36, 6, 19, 0.2); }
.global-search-group + .global-search-group { border-top: 1px solid var(--cream-warm); }
.global-search-group { padding: 0.55rem; }
.global-search-group > p { display: flex; align-items: center; justify-content: space-between; margin: 0 0 0.25rem; color: var(--gulal-deep); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; }
.global-search-group > p small { display: grid; width: 1.35rem; height: 1.35rem; place-items: center; border-radius: 50%; background: var(--cream-warm); font-size: 0.68rem; }
.global-search-result { display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem; border-radius: 0.75rem; color: var(--ink); text-decoration: none; }
.global-search-result:hover, .global-search-result:focus { background: var(--cream-warm); color: var(--maroon); outline: 0; }
.global-search-result > img, .global-search-result-icon { width: 3.25rem; height: 3.25rem; flex: 0 0 3.25rem; border-radius: 0.7rem; }
.global-search-result > img { object-fit: cover; background: var(--cream-warm); }
.global-search-result-icon { display: grid; place-items: center; background: linear-gradient(145deg, var(--cream), var(--cream-warm)); color: var(--gulal-deep); font-size: 1.15rem; }
.global-search-result-copy { display: block; min-width: 0; flex: 1; }
.global-search-result-heading { display: flex; align-items: center; justify-content: space-between; gap: 0.65rem; }
.global-search-result-heading strong { overflow: hidden; font-size: 0.92rem; text-overflow: ellipsis; white-space: nowrap; }
.global-search-result-heading small { flex-shrink: 0; border-radius: 999px; background: rgba(30, 58, 138, 0.08); color: var(--yamuna); font-size: 0.62rem; font-weight: 700; letter-spacing: 0.04em; padding: 0.18rem 0.45rem; text-transform: uppercase; }
.global-search-result-context, .global-search-result-meta { display: block; overflow: hidden; color: #74625b; font-size: 0.76rem; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
.global-search-result-meta { margin-top: 0.15rem; color: var(--gulal-deep); font-size: 0.7rem; font-weight: 600; }
.global-search-empty { padding: 1rem; color: #74625b; font-size: 0.9rem; text-align: center; }
.search-expand-enter-active, .search-expand-leave-active { transition: opacity 0.2s ease, transform 0.24s ease; transform-origin: right center; }
.search-expand-enter-from, .search-expand-leave-to { opacity: 0; transform: translateY(-50%) scaleX(0.75); }

@media (max-width: 767.98px) {
    .global-search-panel { position: fixed; top: 4.75rem; right: 0.75rem; transform: none; }
    .search-expand-enter-from, .search-expand-leave-to { transform: translateY(-0.5rem) scale(0.98); }
}

@media (prefers-reduced-motion: reduce) {
    .global-search-trigger, .search-expand-enter-active, .search-expand-leave-active { transition: none; }
}
</style>
