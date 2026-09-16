<script setup>
import { computed, nextTick, onBeforeUnmount, provide, reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import MenuBuilderTree from '../../../Components/MenuBuilderTree.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ menu: Object, items: Array, pages: Array, menus: Array });
const endpoint = appUrl(`/admin/menus/${props.menu.id}`);
const structure = ref([]);
const drafts = reactive({});
const expanded = reactive(new Set());
const itemErrors = reactive({});
const busy = ref(false);
const dragging = ref(false);
const status = ref('');
const structureError = ref('');
const search = ref('');
const highlighted = reactive(new Set());
let highlightTimer = null;
function markNewItems(knownIds) {
    const fresh = props.items.filter(item => !knownIds.has(item.id));
    if (!fresh.length) return;
    for (const item of fresh) {
        expanded.add(item.id);
        highlighted.add(item.id);
    }
    nextTick(() => {
        document.querySelector(`.menu-node[data-id="${fresh[0].id}"]`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
    clearTimeout(highlightTimer);
    highlightTimer = setTimeout(() => highlighted.clear(), 3000);
}
const settings = useForm({ name: props.menu.name, slug: props.menu.slug, location: props.menu.location ?? '', is_active: !!props.menu.is_active, sort_order: props.menu.sort_order });
const pagesForm = useForm({ page_ids: [] });
const custom = useForm({ title: '', url: '', type: 'custom', page_id: null, parent_id: null, target: '_self', sort_order: 0, is_active: true });
const availablePages = computed(() => props.pages.filter(page => `${page.title} ${page.slug}`.toLowerCase().includes(search.value.toLowerCase())));
watch(() => props.items, items => {
    structure.value = items.map(item => ({ ...item }));
    for (const item of items) {
        if (!drafts[item.id]) drafts[item.id] = { title: item.title, url: item.url, target: item.target, is_active: !!item.is_active };
    }
    for (const id of Object.keys(drafts)) {
        if (!items.some(item => item.id === Number(id))) delete drafts[id];
    }
}, { immediate: true });
const dirty = computed(() => settings.isDirty || custom.isDirty || pagesForm.page_ids.length > 0 || props.items.some(item => drafts[item.id] && Object.keys(drafts[item.id]).some(key => key === 'is_active' ? drafts[item.id][key] !== !!item[key] : drafts[item.id][key] !== item[key])));
function warnBeforeUnload(event) { if (dirty.value || busy.value) { event.preventDefault(); event.returnValue = ''; } }
window.addEventListener('beforeunload', warnBeforeUnload);
const stopNavigationGuard = router.on('before', event => {
    if (event.detail.visit.method === 'get' && dirty.value && !window.confirm('Leave the builder? Unsaved changes will be lost.')) event.preventDefault();
});
onBeforeUnmount(() => { window.removeEventListener('beforeunload', warnBeforeUnload); stopNavigationGuard(); clearTimeout(highlightTimer); });
function options(message, onSuccess = () => {}) {
    busy.value = true;
    status.value = '';
    return { preserveScroll: true, onSuccess: () => { status.value = message; onSuccess(); }, onFinish: () => { busy.value = false; } };
}
function saveSettings() {
    settings.put(endpoint, options('Menu settings saved.', () => settings.defaults()));
}
function addPages() {
    const knownIds = new Set(props.items.map(item => item.id));
    pagesForm.post(`${endpoint}/items/pages`, options('Pages added to the menu.', () => { pagesForm.reset(); markNewItems(knownIds); }));
}
function addCustom() {
    const knownIds = new Set(props.items.map(item => item.id));
    custom.post(`${endpoint}/items`, options('Custom link added.', () => { custom.reset(); markNewItems(knownIds); }));
}
function saveItem(item) {
    itemErrors[item.id] = {};
    router.put(`${endpoint}/items/${item.id}`, { ...drafts[item.id], type: item.type, page_id: item.page_id, parent_id: item.parent_id, sort_order: item.sort_order }, {
        ...options('Link saved.', () => {
            const saved = props.items.find(entry => entry.id === item.id);
            if (saved) drafts[item.id] = { title: saved.title, url: saved.url, target: saved.target, is_active: !!saved.is_active };
        }), onError: errors => { itemErrors[item.id] = errors; },
    });
}
function removeItem(item) {
    if (!window.confirm(`Remove “${item.title}” and all its nested links?`)) return;
    router.delete(`${endpoint}/items/${item.id}`, options('Link removed.'));
}
function move(id, parentId, index) {
    if (busy.value) return;
    const previous = structure.value.map(item => ({ ...item }));
    const item = structure.value.find(item => item.id === id);
    if (!item) return;
    let ancestor = parentId;
    const visited = new Set([id]);
    while (ancestor !== null) {
        if (visited.has(ancestor)) return;
        visited.add(ancestor);
        const parent = structure.value.find(item => item.id === ancestor);
        if (!parent) return;
        ancestor = parent.parent_id;
    }
    const oldParent = item.parent_id;
    item.parent_id = parentId;
    const siblings = structure.value.filter(other => other.id !== id && other.parent_id === parentId).sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);
    siblings.splice(index, 0, item);
    siblings.forEach((sibling, position) => { sibling.sort_order = position; });
    if (oldParent !== parentId) structure.value.filter(other => other.parent_id === oldParent).sort((a, b) => a.sort_order - b.sort_order || a.id - b.id).forEach((sibling, position) => { sibling.sort_order = position; });
    structureError.value = '';
    let saved = false;
    router.put(`${endpoint}/items/reorder`, { items: structure.value.map(({ id, parent_id, sort_order }) => ({ id, parent_id, sort_order })) }, {
        ...options('Menu order saved.'),
        onSuccess: () => { saved = true; status.value = 'Menu order saved.'; },
        onFinish: () => {
            busy.value = false;
            if (!saved) {
                structure.value = previous;
                structureError.value ||= 'The order could not be saved. Please try again.';
            }
        },
        onError: errors => { structure.value = previous; structureError.value = Object.values(errors).join(' '); },
        onCancel: () => { structure.value = previous; },
    });
}
provide('menuBuilder', { busy, dragging, drafts, expanded, highlighted, itemErrors, move, saveItem, removeItem, toggle: id => expanded.has(id) ? expanded.delete(id) : expanded.add(id) });
function switchMenu(event) {
    router.get(appUrl(`/admin/menus/${event.target.value}/items`));
    event.target.value = props.menu.id;
}
</script>

<template>
    <AdminLayout>
        <div class="menu-builder">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div><Link :href="appUrl('/admin/menus')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>All Menus</Link><h2 class="mt-2 mb-1">Menu Builder</h2><p class="text-muted mb-0">Create clear paths through your website.</p></div>
                <div><label for="selected-menu" class="form-label small">Editing menu</label><select id="selected-menu" :value="menu.id" class="form-select" :disabled="busy" @change="switchMenu"><option v-for="entry in menus" :key="entry.id" :value="entry.id">{{ entry.name }}</option></select></div>
            </div>
            <form class="card p-3 mb-4" @submit.prevent="saveSettings">
                <fieldset :disabled="busy" class="row g-3 align-items-end">
                    <div class="col-md-4"><label for="menu-name" class="form-label">Menu name</label><input id="menu-name" v-model="settings.name" class="form-control" required maxlength="255" /></div>
                    <div class="col-md-3"><label for="menu-location" class="form-label">Display location</label><select id="menu-location" v-model="settings.location" class="form-select"><option value="">Not assigned</option><option value="header">Header</option><option value="footer">Footer</option></select></div>
                    <div class="col-md-2"><div class="form-check mb-2"><input id="menu-active" v-model="settings.is_active" type="checkbox" class="form-check-input" /><label for="menu-active" class="form-check-label">Active</label></div></div>
                    <div class="col-md-3"><button class="btn btn-svtp w-100">Save Settings</button></div>
                    <div class="col-12"><details><summary class="small text-muted">Advanced settings</summary><div class="row g-3 mt-1"><div class="col-md-6"><label for="menu-slug" class="form-label">Slug</label><input id="menu-slug" v-model="settings.slug" class="form-control" required /></div><div class="col-md-6"><label for="menu-priority" class="form-label">Display priority</label><input id="menu-priority" v-model.number="settings.sort_order" type="number" min="0" class="form-control" /><small class="text-muted">If several menus share a location, the first usable menu with the lowest priority number is shown.</small></div></div></details></div>
                    <div v-if="settings.hasErrors" class="col-12 text-danger small" role="alert"><div v-for="(error, key) in settings.errors" :key="key">{{ error }}</div></div>
                </fieldset>
            </form>
            <div aria-live="polite" class="d-flex flex-wrap align-items-center gap-3 small mb-3">
                <span v-if="dirty || busy" class="text-warning"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Unsaved changes</span>
                <span v-if="busy" class="text-muted">Saving…</span>
                <span v-else-if="status" class="text-success">{{ status }}</span>
            </div>
            <div class="builder-columns">
                <aside>
                    <h5 class="mb-3">Available Items</h5>
                    <section class="card p-3 mb-3">
                        <h6><i class="bi bi-file-earmark-text me-2"></i>Pages</h6>
                        <form @submit.prevent="addPages"><fieldset :disabled="busy">
                            <label for="page-search" class="visually-hidden">Search pages</label><input id="page-search" v-model="search" type="search" placeholder="Search pages…" class="form-control my-3" />
                            <div class="page-picker"><label v-for="page in availablePages" :key="page.id" class="page-choice"><input v-model="pagesForm.page_ids" type="checkbox" :value="page.id" class="form-check-input flex-shrink-0" /><span>{{ page.title }}<small class="d-block text-muted">/{{ page.slug }}</small></span></label><p v-if="!availablePages.length" class="small text-muted py-3">{{ pages.length ? 'No matching pages.' : 'Create and activate a CMS page to add it here.' }}</p></div>
                            <div class="text-danger small" role="alert"><div v-for="(error, key) in pagesForm.errors" :key="key">{{ error }}</div></div>
                            <button class="btn btn-outline-light btn-sm mt-3 w-100" :disabled="!pagesForm.page_ids.length">Add to Menu <span v-if="pagesForm.page_ids.length">({{ pagesForm.page_ids.length }})</span></button>
                        </fieldset></form>
                    </section>
                    <section class="card p-3">
                        <h6><i class="bi bi-link-45deg me-2"></i>Custom Link</h6>
                        <form @submit.prevent="addCustom"><fieldset :disabled="busy"><label for="custom-url" class="form-label small mt-2">URL</label><input id="custom-url" v-model="custom.url" class="form-control" placeholder="/contact or https://…" required maxlength="255" /><label for="custom-label" class="form-label small mt-3">Navigation Label</label><input id="custom-label" v-model="custom.title" class="form-control" placeholder="e.g. Contact us" required maxlength="255" /><div class="text-danger small mt-2" role="alert"><div v-for="(error, key) in custom.errors" :key="key">{{ error }}</div></div><button class="btn btn-outline-light btn-sm mt-3 w-100">Add to Menu</button></fieldset></form>
                    </section>
                </aside>
                <section class="card structure-panel p-3 p-md-4" :aria-busy="busy">
                    <div class="d-flex justify-content-between align-items-center gap-2"><h5 class="mb-0">Menu Structure</h5><span class="badge bg-secondary">{{ items.length }} links</span></div>
                    <p class="text-muted small mt-2">Drag a handle to reorder. Drop inside a nested area or use the arrow buttons to nest and move links out. Order saves automatically.</p>
                    <div v-if="structureError" class="alert alert-danger" role="alert">{{ structureError }}</div>
                    <div v-if="!items.length" class="builder-empty"><i class="bi bi-menu-button-wide fs-1"></i><h5 class="mt-3">Start building your menu</h5><p class="text-muted small mb-0">Select pages or add a custom link from Available Items.<br />Your website uses its default navigation until this menu is ready.</p></div>
                    <MenuBuilderTree :items="structure" :menu-id="menu.id" />
                </section>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.builder-columns { display: grid; grid-template-columns: 290px minmax(0, 1fr); gap: 24px; align-items: start; }
.menu-builder .card { border-radius: 12px; }
.page-picker { max-height: 280px; overflow-y: auto; }
.page-choice { display: flex; align-items: start; gap: 10px; padding: 10px 4px; cursor: pointer; font-size: .875rem; }
.page-choice + .page-choice { border-top: 1px solid #334155; }
.builder-empty { text-align: center; border: 1px dashed #475569; border-radius: 10px; padding: 48px 16px; margin-top: 20px; color: #aebbd0; }
.structure-panel { min-width: 0; }
@media(max-width: 991px) { .builder-columns { grid-template-columns: 1fr; } }
</style>
