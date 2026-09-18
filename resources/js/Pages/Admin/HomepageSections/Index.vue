<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ sections: Array });
const endpoint = appUrl('/admin/homepage-sections');
const order = ref([]);
const drafts = reactive({});
const expanded = reactive(new Set());
const errors = reactive({});
const busy = ref(false);
const dragging = ref(false);
const orderError = ref('');
const search = ref('');
const list = ref(null);

const visibleSections = computed(() => {
    const term = search.value.trim().toLowerCase();
    const byId = new Map(props.sections.map(section => [section.id, section]));
    return order.value
        .map(id => byId.get(id))
        .filter(Boolean)
        .filter(section => !term || `${section.label} ${section.description}`.toLowerCase().includes(term));
});

watch(() => props.sections, sections => {
    order.value = [...sections].sort((a, b) => a.sort_order - b.sort_order || a.id - b.id).map(section => section.id);
    for (const section of sections) {
        if (!drafts[section.id]) drafts[section.id] = { is_active: !!section.is_active, settings: { ...section.settings } };
    }
    for (const id of Object.keys(drafts)) {
        if (!sections.some(section => section.id === Number(id))) delete drafts[id];
    }
}, { immediate: true });

function isDirty(section) {
    const draft = drafts[section.id];
    if (!draft) return false;
    return draft.is_active !== !!section.is_active || JSON.stringify(draft.settings) !== JSON.stringify(section.settings);
}
const anyDirty = computed(() => props.sections.some(isDirty));
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
    put(section.id, { is_active: draft.is_active, settings: { ...draft.settings } }, () => {
        const saved = props.sections.find(entry => entry.id === section.id);
        if (saved) drafts[section.id] = { is_active: !!saved.is_active, settings: { ...saved.settings } };
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
    router.put(`${endpoint}/reorder`, { sections: order.value.map((id, position) => ({ id, sort_order: position })) }, {
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
                <span class="badge bg-secondary">{{ sections.length }} sections</span>
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
                            <div class="form-check form-switch section-switch">
                                <input :id="`active-${section.id}`" :checked="drafts[section.id]?.is_active" :disabled="busy" type="checkbox" role="switch" class="form-check-input" :aria-label="`Show ${section.label} on homepage`" @change="toggleActive(section)" />
                            </div>
                        </div>
                        <form v-if="expanded.has(section.id)" :id="`section-fields-${section.id}`" class="section-fields" @submit.prevent="saveSettings(section)">
                            <fieldset :disabled="busy">
                                <template v-if="section.schema.heading">
                                    <label :for="`heading-${section.id}`" class="form-label">Section heading</label>
                                    <input :id="`heading-${section.id}`" v-model="drafts[section.id].settings.heading" class="form-control" maxlength="120" :placeholder="`Default: ${section.defaults.heading ?? ''}`" />
                                </template>
                                <template v-if="section.schema.max_items">
                                    <label :for="`max-${section.id}`" class="form-label mt-3">Maximum items to show</label>
                                    <input :id="`max-${section.id}`" v-model.number="drafts[section.id].settings.max_items" type="number" :min="section.schema.max_items.min" :max="section.schema.max_items.max" class="form-control" style="max-width: 160px;" />
                                </template>
                                <template v-if="section.schema.source">
                                    <label :for="`source-${section.id}`" class="form-label mt-3">Which packages to show</label>
                                    <select :id="`source-${section.id}`" v-model="drafts[section.id].settings.source" class="form-select" style="max-width: 280px;">
                                        <option value="featured">Featured packages</option>
                                        <option value="latest">Latest packages</option>
                                    </select>
                                </template>
                                <div v-if="section.schema.show_search" class="form-check mt-3">
                                    <input :id="`search-${section.id}`" v-model="drafts[section.id].settings.show_search" type="checkbox" class="form-check-input" />
                                    <label :for="`search-${section.id}`" class="form-check-label">Show destination search in the hero</label>
                                </div>
                                <p v-if="!section.schema.heading && !section.schema.max_items && !section.schema.source && !section.schema.show_search" class="small text-muted mb-0">This section has no extra settings — use the switch to show or hide it.</p>
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
.section-card { border: 1px solid #3b4b64; background: #162235; border-radius: 10px; overflow: hidden; transition: opacity .2s ease; }
.section-node.is-inactive .section-card { opacity: .62; }
.section-heading { display: flex; align-items: center; gap: 4px; }
.section-drag-handle { padding: 14px 8px; color: #aebbd0; background: transparent; border: 0; cursor: grab; touch-action: none; font-size: 1.2rem; }
.section-toggle { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; text-align: left; padding: 14px 8px 14px 0; color: #f8fafc; background: transparent; border: 0; }
.hsec-title { flex: 1; min-width: 0; }
.hsec-title strong, .hsec-title small { display: block; overflow-wrap: anywhere; }
.hsec-title strong { color: #f1f5f9; font-size: .95rem; }
.hsec-title small { color: #aebbd0; font-size: .75rem; margin-top: 3px; }
.section-switch { margin: 0 12px 0 4px; }
.section-fields { border-top: 1px solid #334155; padding: 16px; }
button:disabled { opacity: .35; cursor: default; }
button:focus-visible { outline: 2px solid #f59e0b; outline-offset: -2px; }
.sortable-ghost { opacity: .35; }
@media(max-width: 575px) { .section-toggle { flex-wrap: wrap; gap: 6px; } .hsec-title { flex-basis: 65%; } }
</style>
