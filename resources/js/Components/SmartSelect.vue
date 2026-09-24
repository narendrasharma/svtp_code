<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    placeholder: { type: String, default: 'Select an option' },
    searchPlaceholder: { type: String, default: 'Type to search...' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
    emptyText: { type: String, default: 'No matching options.' },
    // Async mode for large datasets (users, vendors, tours, bookings):
    // pass a function (query: string) => Promise<[{value,label,meta}]>.
    // Nothing is preloaded — options resolve from the server.
    fetchOptions: { type: Function, default: null },
    minChars: { type: Number, default: 1 },
    id: { type: String, default: () => `smart-select-${Math.random().toString(36).slice(2, 8)}` },
});

const emit = defineEmits(['update:modelValue', 'change']);

const open = ref(false);
const query = ref('');
const highlighted = ref(-1);
const remoteOptions = ref([]);
const remoteLoading = ref(false);
const remoteError = ref('');
const root = ref(null);
const input = ref(null);
let debounce = null;
let requestSeq = 0;

const isAsync = computed(() => typeof props.fetchOptions === 'function');

const normalizedLocal = computed(() => props.options.map(normalize));

function normalize(option) {
    if (option && typeof option === 'object') {
        return {
            value: option.value ?? option.id,
            label: option.label ?? option.name ?? String(option.value ?? option.id),
            meta: option.meta ?? null,
        };
    }
    return { value: option, label: String(option), meta: null };
}

const pool = computed(() => (isAsync.value ? remoteOptions.value.map(normalize) : normalizedLocal.value));

const filtered = computed(() => {
    if (isAsync.value) return pool.value;
    const q = query.value.trim().toLowerCase();
    if (!q) return pool.value;
    return pool.value.filter((o) => `${o.label} ${o.meta || ''}`.toLowerCase().includes(q));
});

const selected = computed(() => {
    if (props.modelValue === null || props.modelValue === '') return null;
    const needle = String(props.modelValue);
    return pool.value.find((o) => String(o.value) === needle)
        || normalizedLocal.value.find((o) => String(o.value) === needle)
        || null;
});

const display = computed(() => {
    if (open.value) return query.value;
    return selected.value ? selected.value.label : '';
});

function toggle(force) {
    if (props.disabled) return;
    open.value = force !== undefined ? force : !open.value;
    if (open.value) {
        query.value = '';
        highlighted.value = filtered.value.length ? 0 : -1;
        if (isAsync.value) runFetch('');
        nextTick(() => input.value?.focus());
    }
}

function runFetch(q) {
    if (q.trim().length < props.minChars && q.trim() !== '') return;
    clearTimeout(debounce);
    debounce = setTimeout(async () => {
        const seq = ++requestSeq;
        remoteLoading.value = true;
        remoteError.value = '';
        try {
            const result = await props.fetchOptions(q);
            if (seq !== requestSeq) return;
            remoteOptions.value = Array.isArray(result) ? result : [];
            highlighted.value = remoteOptions.value.length ? 0 : -1;
        } catch (e) {
            if (seq !== requestSeq) return;
            remoteError.value = 'Could not load options.';
            remoteOptions.value = [];
        } finally {
            if (seq === requestSeq) remoteLoading.value = false;
        }
    }, 250);
}

watch(query, (q) => {
    if (!open.value) return;
    if (isAsync.value) runFetch(q);
    else highlighted.value = filtered.value.length ? 0 : -1;
});

function choose(option) {
    if (!option) return;
    emit('update:modelValue', option.value);
    emit('change', option);
    open.value = false;
    query.value = '';
}

function clear(event) {
    event?.stopPropagation();
    if (props.disabled) return;
    emit('update:modelValue', null);
    emit('change', null);
    query.value = '';
    nextTick(() => input.value?.focus());
}

function onKeydown(event) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (!open.value) { toggle(true); return; }
        const list = filtered.value;
        if (!list.length) return;
        const delta = event.key === 'ArrowDown' ? 1 : -1;
        highlighted.value = ((highlighted.value + delta) % list.length + list.length) % list.length;
        document.getElementById(`${props.id}-opt-${highlighted.value}`)?.scrollIntoView({ block: 'nearest' });
    } else if (event.key === 'Enter') {
        if (open.value && highlighted.value >= 0 && filtered.value[highlighted.value]) {
            event.preventDefault();
            choose(filtered.value[highlighted.value]);
        }
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}

function onClickOutside(event) {
    if (root.value && !root.value.contains(event.target)) open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) document.addEventListener('click', onClickOutside);
    else document.removeEventListener('click', onClickOutside);
});

function onInputFocus() {
    if (!open.value) toggle(true);
}
</script>

<template>
    <div ref="root" class="smart-select">
        <label v-if="label" :for="id" class="form-label">{{ label }}<span v-if="required" class="text-danger">*</span></label>
        <div class="smart-select-box form-control d-flex align-items-center gap-2" :class="{ 'is-open': open, 'is-invalid': error, 'is-disabled': disabled }" @click="toggle()">
            <input
                :id="id"
                ref="input"
                :value="display"
                type="text"
                class="smart-select-input"
                :placeholder="selected && !open ? selected.label : placeholder"
                :disabled="disabled"
                role="combobox"
                :aria-expanded="open"
                aria-autocomplete="list"
                :aria-activedescendant="highlighted >= 0 ? `${id}-opt-${highlighted}` : undefined"
                autocomplete="off"
                @focus="onInputFocus"
                @input="query = $event.target.value"
                @keydown="onKeydown"
            />
            <span v-if="loading || remoteLoading" class="spinner-border spinner-border-sm text-muted" role="status" aria-label="Loading"></span>
            <button v-else-if="clearable && selected && !disabled" type="button" class="smart-select-clear" aria-label="Clear selection" @click="clear"><i class="bi bi-x-lg"></i></button>
            <i class="bi bi-chevron-down smart-select-caret" aria-hidden="true"></i>
        </div>
        <div v-if="open" class="smart-select-menu" role="listbox" :aria-label="searchPlaceholder">
            <div v-if="isAsync" class="smart-select-hint">{{ searchPlaceholder }}</div>
            <div v-if="remoteError" class="smart-select-empty">{{ remoteError }}</div>
            <button
                v-for="(option, index) in filtered"
                :id="`${id}-opt-${index}`"
                :key="option.value"
                type="button"
                role="option"
                :aria-selected="selected && String(selected.value) === String(option.value)"
                class="smart-select-option"
                :class="{ 'is-highlighted': index === highlighted, 'is-selected': selected && String(selected.value) === String(option.value) }"
                @mouseenter="highlighted = index"
                @click="choose(option)"
            >
                <span class="d-block">{{ option.label }}</span>
                <small v-if="option.meta" class="text-muted">{{ option.meta }}</small>
            </button>
            <p v-if="!filtered.length && !remoteLoading" class="smart-select-empty">{{ emptyText }}</p>
        </div>
        <small v-if="error" class="text-danger">{{ error }}</small>
    </div>
</template>

<style scoped>
.smart-select { position: relative; }
.smart-select-box { cursor: pointer; min-height: 38px; }
.smart-select-box.is-disabled { opacity: .65; cursor: not-allowed; }
.smart-select-box.is-open { border-color: #f59e0b; box-shadow: 0 0 0 .2rem rgba(245,158,11,.18); }
.smart-select-input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; color: inherit; }
.smart-select-input::placeholder { color: var(--admin-input-placeholder, #94a3b8); }
.smart-select-clear { border: 0; background: transparent; color: var(--admin-text-muted, #94a3b8); padding: 0 .15rem; line-height: 1; }
.smart-select-clear:hover { color: var(--admin-text, #fff); }
.smart-select-caret { color: var(--admin-text-muted, #94a3b8); font-size: .8rem; }
.smart-select-menu { position: absolute; z-index: 1050; left: 0; right: 0; top: calc(100% + 4px); max-height: 240px; overflow-y: auto; border: 1px solid var(--admin-popover-border, #475569); border-radius: .5rem; background: var(--admin-popover-bg, #162235); color: var(--admin-text, #e5e7eb); box-shadow: var(--admin-shadow-md, 0 12px 32px rgba(0,0,0,.45)); padding: .35rem; }
.smart-select-hint { padding: .35rem .6rem; font-size: .75rem; color: var(--admin-text-muted, #94a3b8); }
.smart-select-option { display: block; width: 100%; text-align: left; border: 0; border-radius: .4rem; background: transparent; color: var(--admin-text, #e5e7eb); padding: .45rem .6rem; }
.smart-select-option.is-highlighted { background: var(--admin-surface-sunken, #1e293b); }
.smart-select-option.is-selected { color: var(--admin-primary, #fbbf24); }
.smart-select-empty { margin: 0; padding: .5rem .6rem; font-size: .85rem; color: var(--admin-text-muted, #94a3b8); }
</style>
