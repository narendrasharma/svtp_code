<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ sections: { type: Array, default: () => [] }, merchandisingSections: { type: Array, default: () => [] } });
const endpoint = appUrl('/admin/homepage-sections');
const isMerchandising = computed(() => props.merchandisingSections.length > 0);
const managedSections = computed(() => isMerchandising.value ? props.merchandisingSections : props.sections);
const order = ref([]);
const drafts = reactive({});
const expanded = reactive(new Set());
const errors = reactive({});
const pickerQuery = reactive({});
const pickerResults = reactive({});
const pickerBusy = reactive({});
const busy = ref(false);
const dragging = ref(false);
const orderError = ref('');
const search = ref('');
const list = ref(null);

const visibleSections = computed(() => {
    const term = search.value.trim().toLowerCase();
    const byId = new Map(managedSections.value.map(section => [section.id, section]));
    return order.value
        .map(id => byId.get(id))
        .filter(Boolean)
        .filter(section => !term || `${section.label} ${section.description}`.toLowerCase().includes(term));
});

watch(managedSections, sections => {
    order.value = [...sections].sort((a, b) => a.sort_order - b.sort_order || a.id - b.id).map(section => section.id);
    for (const section of sections) {
        if (!drafts[section.id]) drafts[section.id] = {
            is_active: !!section.is_active,
            settings: { ...section.settings },
            source_mode: section.source_mode,
            item_limit: section.item_limit,
            manual_items: (section.manual_items ?? []).map(item => ({ ...item })),
        };
    }
    for (const id of Object.keys(drafts)) {
        if (!sections.some(section => section.id === Number(id))) delete drafts[id];
    }
}, { immediate: true });

function isDirty(section) {
    const draft = drafts[section.id];
    if (!draft) return false;
    return draft.is_active !== !!section.is_active
        || JSON.stringify(draft.settings) !== JSON.stringify(section.settings)
        || draft.source_mode !== section.source_mode
        || draft.item_limit !== section.item_limit
        || JSON.stringify(draft.manual_items) !== JSON.stringify(section.manual_items ?? []);
}
const anyDirty = computed(() => managedSections.value.some(isDirty));
function warnBeforeUnload(event) { if (anyDirty.value || busy.value) { event.preventDefault(); event.returnValue = ''; } }
window.addEventListener('beforeunload', warnBeforeUnload);
const stopNavigationGuard = router.on('before', event => {
    if (event.detail.visit.method === 'get' && anyDirty.value && !window.confirm('Leave this page? Unsaved section changes will be lost.')) event.preventDefault();
});

let sortable;
let originalNext;
function initSortable() {
    if (!list.value || sortable) return;
    sortable = Sortable.create(list.value, {
        handle: '.section-drag-handle',
        draggable: '.section-node',
        animation: 150,
        fallbackOnBody: true,
        onStart(event) {
            originalNext = event.item.nextSibling;
            dragging.value = true;
        },
        onMove() {
            // Dragging a filtered list would corrupt positions: reorder only the full list.
            return !busy.value && !search.value.trim();
        },
        onEnd(event) {
            event.from.insertBefore(event.item, originalNext?.parentNode === event.from ? originalNext : null);
            dragging.value = false;
            persistOrder(Number(event.item.dataset.id), event.newDraggableIndex);
        },
    });
}
watch(list, () => initSortable(), { flush: 'post' });

function put(id, payload, onSuccessCallback = () => {}, onErrorCallback = null) {
    if (busy.value) return;
    busy.value = true;
    errors[id] = {};
    router.put(`${endpoint}/${id}`, payload, {
        preserveScroll: true,
        onSuccess: () => { onSuccessCallback(); },
        onError: errs => { errors[id] = errs; if (onErrorCallback) onErrorCallback(); },
        onFinish: () => { busy.value = false; },
    });
}
function toggleActive(section) {
    const draft = drafts[section.id];
    if (draft) draft.is_active = !draft.is_active;
    put(section.id, { is_active: !section.is_active });
}
function saveSettings(section) {
    const draft = drafts[section.id];
    const payload = isMerchandising.value
        ? {
            is_active: draft.is_active,
            settings: { ...draft.settings },
            source_mode: draft.source_mode,
            item_limit: draft.item_limit,
            items: (draft.manual_items ?? []).map((item, position) => ({ entity_type: item.entity_type, entity_id: item.entity_id, sort_order: position })),
        }
        : { is_active: draft.is_active, settings: { ...draft.settings } };
    put(section.id, payload, () => {
        const saved = managedSections.value.find(entry => entry.id === section.id);
        if (saved) drafts[section.id] = {
            is_active: !!saved.is_active,
            settings: { ...saved.settings },
            source_mode: saved.source_mode,
            item_limit: saved.item_limit,
            manual_items: (saved.manual_items ?? []).map(item => ({ ...item })),
        };
    });
}
function persistOrder(movedId, newIndex) {
    if (busy.value) return;
    const previous = [...order.value];
    const without = order.value.filter(id => id !== movedId);
    without.splice(Math.max(0, Math.min(newIndex, without.length)), 0, movedId);
    order.value = without;
    orderError.value = '';
    busy.value = true;
    let saved = false;
    const reorderEndpoint = isMerchandising.value ? `${endpoint}/merchandising/reorder` : `${endpoint}/reorder`;
    router.put(reorderEndpoint, { sections: order.value.map((id, position) => ({ id, sort_order: position })) }, {
        preserveScroll: true,
        onSuccess: () => { saved = true; },
        onError: errs => { order.value = previous; orderError.value = Object.values(errs).join(' '); },
        onFinish: () => {
            busy.value = false;
            if (!saved && !orderError.value) {
                order.value = previous;
                orderError.value = 'The order could not be saved. Please try again.';
            }
        },
        onCancel: () => { order.value = previous; },
    });
}

async function searchItems(section) {
    const query = (pickerQuery[section.id] ?? '').trim();
    if (query.length < 2 || !section.entity_types?.length) return;
    pickerBusy[section.id] = true;
    try {
        const response = await axios.get(appUrl(`/admin/homepage-sections/${section.id}/items/search`), { params: { q: query, entity_type: section.entity_types[0] } });
        pickerResults[section.id] = response.data.items ?? [];
    } catch (error) {
        errors[section.id] = { picker: error.response?.data?.message ?? 'Could not search items.' };
    } finally {
        pickerBusy[section.id] = false;
    }
}

function addManualItem(section, item) {
    const draft = drafts[section.id];
    if (!draft.manual_items.some(selected => selected.entity_type === item.entity_type && Number(selected.entity_id) === Number(item.entity_id))) {
        draft.manual_items.push({ ...item, sort_order: draft.manual_items.length });
    }
}

function removeManualItem(section, index) {
    drafts[section.id].manual_items.splice(index, 1);
}

function moveManualItem(section, index, direction) {
    const items = drafts[section.id].manual_items;
    const target = index + direction;
    if (target < 0 || target >= items.length) return;
    [items[index], items[target]] = [items[target], items[index]];
}

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warnBeforeUnload);
    stopNavigationGuard();
    sortable?.destroy();
    sortable = null;
});
</script>

<template>
    <AdminLayout>
        <div class="sections-manager">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <h2 class="mt-2 mb-1">Homepage Sections</h2>
                    <p class="text-muted mb-0">Control which homepage sections are visible, their order and headings.</p>
                </div>
                <span class="badge bg-secondary">{{ managedSections.length }} sections</span>
            </div>
            <div aria-live="polite" class="d-flex flex-wrap align-items-center gap-3 small mb-3">
                <span v-if="anyDirty || busy" class="text-warning"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Unsaved changes</span>
                <span v-if="busy" class="text-muted">Saving…</span>
            </div>
            <div class="card p-3 mb-3">
                <label for="section-search" class="visually-hidden">Search sections</label>
                <input id="section-search" v-model="search" type="search" placeholder="Search sections…" class="form-control" style="max-width: 320px;" />
            </div>
            <div v-if="orderError" class="alert alert-danger" role="alert">{{ orderError }}</div>
            <div ref="list" class="section-list">
                <article v-for="(section, index) in visibleSections" :key="section.id" class="section-node" :class="{ 'is-inactive': !drafts[section.id]?.is_active }" :data-id="section.id">
                    <div class="section-card">
                        <div class="section-heading">
                            <button type="button" class="section-drag-handle" :disabled="busy || !!search.trim()" :aria-label="`Drag ${section.label}`" :title="search.trim() ? 'Clear the search to reorder' : 'Drag to reorder'"><i class="bi bi-grip-vertical" aria-hidden="true"></i></button>
                            <button type="button" class="section-toggle" :aria-expanded="expanded.has(section.id)" :aria-controls="`section-fields-${section.id}`" @click="expanded.has(section.id) ? expanded.delete(section.id) : expanded.add(section.id)">
                                <span class="hsec-title">
                                    <strong>{{ section.label }}</strong>
                                    <small>{{ section.description }}</small>
                                </span>
                                <span class="badge bg-secondary">#{{ index + 1 }}</span>
                                <span v-if="!drafts[section.id]?.is_active" class="badge bg-dark">Hidden</span>
                                <span v-if="isDirty(section)" class="badge bg-warning text-dark">Unsaved</span>
                                <i class="bi" :class="expanded.has(section.id) ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i>
                            </button>
                            <div class="section-meta d-none d-md-flex align-items-center gap-2 small text-muted">
                                <span v-if="section.source_mode">{{ section.source_mode }}</span>
                                <span v-if="section.item_limit">up to {{ section.item_limit }}</span>
                            </div>
                            <div class="form-check form-switch section-switch">
                                <input :id="`active-${section.id}`" :checked="drafts[section.id]?.is_active" :disabled="busy" type="checkbox" role="switch" class="form-check-input" :aria-label="`Show ${section.label} on homepage`" @change="toggleActive(section)" />
                            </div>
                        </div>
                        <form v-if="expanded.has(section.id)" :id="`section-fields-${section.id}`" class="section-fields" @submit.prevent="saveSettings(section)">
                            <fieldset :disabled="busy">
                                <template v-if="isMerchandising && section.schema.title">
                                    <label :for="`title-${section.id}`" class="form-label">Section title</label>
                                    <input :id="`title-${section.id}`" v-model="drafts[section.id].settings.title" class="form-control" maxlength="120" />
                                </template>
                                <template v-if="isMerchandising && section.schema.subtitle">
                                    <label :for="`subtitle-${section.id}`" class="form-label mt-3">Subtitle</label>
                                    <textarea :id="`subtitle-${section.id}`" v-model="drafts[section.id].settings.subtitle" class="form-control" rows="2" maxlength="240"></textarea>
                                </template>
                                <template v-if="isMerchandising && section.schema.cta_label">
                                    <label :for="`cta-label-${section.id}`" class="form-label mt-3">Button label</label>
                                    <input :id="`cta-label-${section.id}`" v-model="drafts[section.id].settings.cta_label" class="form-control" maxlength="80" />
                                </template>
                                <template v-if="isMerchandising && section.schema.cta_url">
                                    <label :for="`cta-url-${section.id}`" class="form-label mt-3">Button URL</label>
                                    <input :id="`cta-url-${section.id}`" v-model="drafts[section.id].settings.cta_url" class="form-control" maxlength="500" placeholder="/destinations or https://…" />
                                    <small class="form-text text-muted">Use a public page URL such as /destinations. API/autocomplete endpoints are not navigation targets.</small>
                                </template>
                                <template v-if="isMerchandising && section.schema.default_tab">
                                    <label :for="`default-tab-${section.id}`" class="form-label mt-3">Default search tab</label>
                                    <select :id="`default-tab-${section.id}`" v-model="drafts[section.id].settings.default_tab" class="form-select" style="max-width: 280px;">
                                        <option value="hotels">Hotels</option>
                                        <option value="tours">Tours</option>
                                        <option value="taxi">Taxi</option>
                                    </select>
                                </template>
                                <template v-if="isMerchandising && section.source_modes?.length">
                                    <label :for="`source-mode-${section.id}`" class="form-label mt-3">Source</label>
                                    <select :id="`source-mode-${section.id}`" v-model="drafts[section.id].source_mode" class="form-select" style="max-width: 280px;">
                                        <option v-for="mode in section.source_modes" :key="mode" :value="mode">{{ mode }}</option>
                                    </select>
                                    <label :for="`item-limit-${section.id}`" class="form-label mt-3">Item limit</label>
                                    <input :id="`item-limit-${section.id}`" v-model.number="drafts[section.id].item_limit" type="number" min="1" max="24" class="form-control" style="max-width: 160px;" />
                                </template>
                                <template v-if="isMerchandising && drafts[section.id].source_mode === 'manual'">
                                    <label :for="`item-search-${section.id}`" class="form-label mt-3">Find {{ section.entity_types?.[0] }}</label>
                                    <div class="input-group">
                                        <input :id="`item-search-${section.id}`" v-model="pickerQuery[section.id]" type="search" class="form-control" minlength="2" placeholder="Type at least two characters" @keyup.enter="searchItems(section)" />
                                        <button type="button" class="btn btn-outline-secondary" :disabled="pickerBusy[section.id]" @click="searchItems(section)">{{ pickerBusy[section.id] ? 'Searching…' : 'Search' }}</button>
                                    </div>
                                    <div v-if="pickerResults[section.id]?.length" class="picker-results mt-2">
                                        <button v-for="item in pickerResults[section.id]" :key="`${item.entity_type}-${item.entity_id}`" type="button" class="picker-result" @click="addManualItem(section, item)">
                                            <span>{{ item.label }}</span><small v-if="item.context">{{ item.context }}</small>
                                        </button>
                                    </div>
                                    <div v-if="drafts[section.id].manual_items?.length" class="manual-items mt-3">
                                        <div v-for="(item, itemIndex) in drafts[section.id].manual_items" :key="`${item.entity_type}-${item.entity_id}`" class="manual-item">
                                            <span>{{ item.label ?? `${item.entity_type} #${item.entity_id}` }}</span>
                                            <span class="d-flex gap-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="itemIndex === 0" @click="moveManualItem(section, itemIndex, -1)">↑</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="itemIndex === drafts[section.id].manual_items.length - 1" @click="moveManualItem(section, itemIndex, 1)">↓</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeManualItem(section, itemIndex)">Remove</button>
                                            </span>
                                        </div>
                                    </div>
                                </template>
                                <template v-if="!isMerchandising && section.schema.heading">
                                    <label :for="`heading-${section.id}`" class="form-label">Section heading</label>
                                    <input :id="`heading-${section.id}`" v-model="drafts[section.id].settings.heading" class="form-control" maxlength="120" :placeholder="`Default: ${section.defaults.heading ?? ''}`" />
                                </template>
                                <template v-if="!isMerchandising && section.schema.max_items">
                                    <label :for="`max-${section.id}`" class="form-label mt-3">Maximum items to show</label>
                                    <input :id="`max-${section.id}`" v-model.number="drafts[section.id].settings.max_items" type="number" :min="section.schema.max_items.min" :max="section.schema.max_items.max" class="form-control" style="max-width: 160px;" />
                                </template>
                                <template v-if="!isMerchandising && section.schema.source">
                                    <label :for="`source-${section.id}`" class="form-label mt-3">Which packages to show</label>
                                    <select :id="`source-${section.id}`" v-model="drafts[section.id].settings.source" class="form-select" style="max-width: 280px;">
                                        <option value="featured">Featured packages</option>
                                        <option value="latest">Latest packages</option>
                                    </select>
                                </template>
                                <div v-if="!isMerchandising && section.schema.show_search" class="form-check mt-3">
                                    <input :id="`search-${section.id}`" v-model="drafts[section.id].settings.show_search" type="checkbox" class="form-check-input" />
                                    <label :for="`search-${section.id}`" class="form-check-label">Show destination search in the hero</label>
                                </div>
                                <p v-if="!isMerchandising && !section.schema.heading && !section.schema.max_items && !section.schema.source && !section.schema.show_search" class="small text-muted mb-0">This section has no extra settings — use the switch to show or hide it.</p>
                                <div v-if="errors[section.id]" class="text-danger small mt-2" role="alert"><div v-for="(error, field) in errors[section.id]" :key="field">{{ error }}</div></div>
                                <div class="mt-3"><button class="btn btn-sm btn-svtp" :disabled="!isDirty(section)">Save Section</button></div>
                            </fieldset>
                        </form>
                    </div>
                </article>
                <p v-if="!visibleSections.length" class="small text-muted py-3">No sections match your search.</p>
            </div>
            <p class="text-muted small mt-3 mb-0">Drag a handle to reorder — the order saves automatically. Hidden sections keep their settings and stay out of the public homepage.</p>
        </div>
    </AdminLayout>
</template>

<style scoped>
.sections-manager .card { border-radius: 12px; }
.section-list { display: flex; flex-direction: column; gap: 10px; }
.section-node { margin: 0; }
.section-card { border: 1px solid var(--admin-border); background: var(--admin-surface); border-radius: 10px; overflow: hidden; transition: opacity .2s ease; }
.section-node.is-inactive .section-card { opacity: .62; }
.section-heading { display: flex; align-items: center; gap: 4px; }
.section-drag-handle { padding: 14px 8px; color: var(--admin-text-muted); background: transparent; border: 0; cursor: grab; touch-action: none; font-size: 1.2rem; }
.section-toggle { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; text-align: left; padding: 14px 8px 14px 0; color: var(--admin-text); background: transparent; border: 0; }
.hsec-title { flex: 1; min-width: 0; }
.hsec-title strong, .hsec-title small { display: block; overflow-wrap: anywhere; }
.hsec-title strong { color: var(--admin-text); font-size: .95rem; }
.hsec-title small { color: var(--admin-text-muted); font-size: .75rem; margin-top: 3px; }
.section-switch { margin: 0 12px 0 4px; }
.section-fields { border-top: 1px solid var(--admin-border); padding: 16px; }
.picker-results { display: grid; gap: 4px; }
.picker-result, .manual-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .5rem .65rem; border: 1px solid var(--admin-border); border-radius: .45rem; background: var(--admin-surface-sunken); color: var(--admin-text); text-align: left; }
.picker-result { width: 100%; }
.picker-result:hover { border-color: var(--admin-primary); }
.picker-result small { color: var(--admin-text-muted); }
.manual-items { display: grid; gap: 5px; }
button:disabled { opacity: .35; cursor: default; }
button:focus-visible { outline: 2px solid #f59e0b; outline-offset: -2px; }
.sortable-ghost { opacity: .35; }
@media(max-width: 575px) { .section-toggle { flex-wrap: wrap; gap: 6px; } .hsec-title { flex-basis: 65%; } }
</style>
