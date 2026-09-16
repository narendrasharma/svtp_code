<script setup>
import { computed, inject, onBeforeUnmount, onMounted, ref } from 'vue';
import Sortable from 'sortablejs';

const props = defineProps({ items: Array, parentId: { default: null }, menuId: Number });
const builder = inject('menuBuilder');
const list = ref(null);
const siblings = computed(() => props.items.filter(item => item.parent_id === props.parentId).sort((a, b) => a.sort_order - b.sort_order || a.id - b.id));
let sortable;
let originalNext;
onMounted(() => {
    sortable = Sortable.create(list.value, {
        group: `menu-${props.menuId}`,
        handle: '.menu-drag-handle',
        draggable: '.menu-node',
        animation: 150,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        emptyInsertThreshold: 14,
        onStart(event) {
            originalNext = event.item.nextSibling;
            builder.dragging.value = true;
        },
        onMove(event) {
            return !builder.busy.value && !event.dragged.contains(event.to);
        },
        onEnd(event) {
            const parentId = event.to.dataset.parent ? Number(event.to.dataset.parent) : null;
            event.from.insertBefore(event.item, originalNext?.parentNode === event.from ? originalNext : null);
            builder.dragging.value = false;
            builder.move(Number(event.item.dataset.id), parentId, event.newDraggableIndex);
        },
    });
});
onBeforeUnmount(() => sortable?.destroy());

function indent(item, index) {
    if (index <= 0) return;
    const parent = siblings.value[index - 1];
    builder.move(item.id, parent.id, props.items.filter(child => child.parent_id === parent.id).length);
}
function outdent(item) {
    const parent = props.items.find(candidate => candidate.id === props.parentId);
    const peers = props.items.filter(candidate => candidate.parent_id === parent.parent_id).sort((a, b) => a.sort_order - b.sort_order || a.id - b.id);
    builder.move(item.id, parent.parent_id, peers.findIndex(candidate => candidate.id === parent.id) + 1);
}
</script>

<template>
    <div ref="list" class="menu-tree" :class="{ 'child-tree': parentId !== null, 'drop-ready': builder.dragging.value }" :data-parent="parentId ?? ''">
        <article v-for="(item, index) in siblings" :key="item.id" class="menu-node" :class="{ 'is-new': builder.highlighted?.has(item.id) }" :data-id="item.id">
            <div class="menu-card">
                <div class="menu-card-heading">
                    <button type="button" class="menu-drag-handle" :disabled="builder.busy.value" :aria-label="`Drag ${item.title}`" title="Drag to reorder or nest"><i class="bi bi-grip-vertical" aria-hidden="true"></i></button>
                    <button type="button" class="menu-card-toggle" :aria-expanded="builder.expanded.has(item.id)" :aria-controls="`menu-fields-${item.id}`" @click="builder.toggle(item.id)">
                        <span class="menu-card-title"><strong>{{ builder.drafts[item.id]?.title || item.title }}</strong><small>{{ item.type === 'page' ? (item.page ? `Page: ${item.page.title}` : 'Page unavailable') : item.url }}</small></span>
                        <span class="badge bg-secondary">{{ item.type === 'page' ? 'Page' : 'Custom Link' }}</span>
                        <span v-if="!item.is_active" class="badge bg-dark">Inactive</span>
                        <i class="bi" :class="builder.expanded.has(item.id) ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="menu-move-controls">
                    <button type="button" :disabled="builder.busy.value || index === 0" @click="builder.move(item.id, parentId, index - 1)" :aria-label="`Move ${item.title} up`" title="Move up"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" :disabled="builder.busy.value || index === siblings.length - 1" @click="builder.move(item.id, parentId, index + 1)" :aria-label="`Move ${item.title} down`" title="Move down"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" :disabled="builder.busy.value || parentId === null" @click="outdent(item)" :aria-label="`Move ${item.title} out one level`" title="Move out one level"><i class="bi bi-arrow-return-left"></i></button>
                    <button type="button" :disabled="builder.busy.value || index === 0" @click="indent(item, index)" :aria-label="`Nest ${item.title} under previous item`" title="Nest under previous item"><i class="bi bi-arrow-return-right"></i></button>
                    <small v-if="parentId !== null" class="text-muted ms-1">Sub-item</small>
                </div>
                <form v-if="builder.expanded.has(item.id)" :id="`menu-fields-${item.id}`" class="menu-fields" @submit.prevent="builder.saveItem(item)">
                    <fieldset :disabled="builder.busy.value">
                        <label :for="`label-${item.id}`" class="form-label">Navigation Label</label>
                        <input :id="`label-${item.id}`" v-model="builder.drafts[item.id].title" class="form-control" maxlength="255" required />
                        <template v-if="item.type === 'custom'">
                            <label :for="`url-${item.id}`" class="form-label mt-3">URL</label>
                            <input :id="`url-${item.id}`" v-model="builder.drafts[item.id].url" class="form-control" maxlength="255" required />
                        </template>
                        <p v-else class="small text-muted mt-3 mb-0"><i class="bi bi-file-earmark-text me-1"></i>{{ item.page ? `${item.page.title} / ${item.page.slug}` : 'This page was removed.' }}<span v-if="item.page && !item.page.is_active" class="d-block text-warning">Inactive page — hidden from public navigation.</span></p>
                        <label :for="`target-${item.id}`" class="form-label mt-3">Open link in</label>
                        <select :id="`target-${item.id}`" v-model="builder.drafts[item.id].target" class="form-select"><option value="_self">Same tab</option><option value="_blank">New tab</option></select>
                        <div class="form-check mt-3"><input :id="`active-${item.id}`" v-model="builder.drafts[item.id].is_active" type="checkbox" class="form-check-input" /><label :for="`active-${item.id}`" class="form-check-label">Active</label></div>
                        <div v-if="builder.itemErrors[item.id]" class="text-danger small mt-2" role="alert"><div v-for="(error, field) in builder.itemErrors[item.id]" :key="field">{{ error }}</div></div>
                        <div class="d-flex justify-content-between gap-2 mt-3"><button class="btn btn-sm btn-svtp">Save Link</button><button type="button" class="btn btn-sm btn-outline-danger" @click="builder.removeItem(item)"><i class="bi bi-trash me-1"></i>Remove</button></div>
                    </fieldset>
                </form>
            </div>
            <MenuBuilderTree :items="items" :parent-id="item.id" :menu-id="menuId" />
        </article>
        <span v-if="!siblings.length && parentId !== null && builder.dragging.value" class="drop-hint">Drop here to nest</span>
    </div>
</template>

<style scoped>
.menu-tree { min-height: 12px; }
.child-tree { margin-left: 26px; border-left: 1px solid #334155; padding-left: 10px; }
.child-tree:empty { min-height: 8px; border-color: transparent; }
.drop-ready.child-tree { min-height: 34px; border: 1px dashed #64748b; border-radius: 6px; margin-top: 6px; }
.drop-hint { display: block; color: #aebbd0; font-size: .75rem; padding: 6px; pointer-events: none; }
.menu-node { margin: 8px 0; }
.menu-node.is-new .menu-card { border-color: #f59e0b; box-shadow: 0 0 0 1px #f59e0b; transition: border-color .3s ease, box-shadow .3s ease; }
.menu-card { border: 1px solid #3b4b64; background: #162235; border-radius: 10px; overflow: hidden; }
.menu-card-heading { display: flex; align-items: stretch; }
.menu-drag-handle { padding: 12px 8px; color: #aebbd0; background: transparent; border: 0; cursor: grab; touch-action: none; font-size: 1.2rem; }
.menu-card-toggle { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; text-align: left; padding: 14px 12px 14px 0; color: #f8fafc; background: transparent; border: 0; }
.menu-card-title { flex: 1; min-width: 0; }
.menu-card-title strong,.menu-card-title small { display: block; overflow-wrap: anywhere; }
.menu-card-title small { color: #aebbd0; font-size: .75rem; margin-top: 3px; }
.menu-move-controls { display: flex; align-items: center; gap: 4px; padding: 0 12px 9px 34px; }
.menu-move-controls button { border: 1px solid #475569; background: transparent; color: #e5e7eb; border-radius: 4px; width: 30px; height: 28px; }
button:disabled { opacity: .35; cursor: default; }
button:focus-visible { outline: 2px solid #f59e0b; outline-offset: -2px; }
.menu-fields { border-top: 1px solid #334155; padding: 16px; }
.sortable-ghost { opacity: .35; }
@media(max-width: 575px) { .child-tree { margin-left: 12px; padding-left: 5px; } .menu-card-toggle { flex-wrap: wrap; gap: 6px; } .menu-card-title { flex-basis: 70%; } }
</style>
