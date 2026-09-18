<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    placeholder: { type: String, default: 'Search options...' },
    emptyText: { type: String, default: 'No matching options.' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    allowSelectAll: { type: Boolean, default: false },
    id: { type: String, default: () => `smart-multi-${Math.random().toString(36).slice(2, 8)}` },
});

const emit = defineEmits(['update:modelValue', 'change']);

const query = ref('');
const highlighted = ref(-1);
const listbox = ref(null);

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

const pool = computed(() => props.options.map(normalize));

const selectedValues = computed(() => props.modelValue.map((v) => String(v)));

const selectedOptions = computed(() => pool.value.filter((o) => selectedValues.value.includes(String(o.value))));

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return pool.value;
    return pool.value.filter((o) => `${o.label} ${o.meta || ''}`.toLowerCase().includes(q));
});

const allFilteredSelected = computed(() => filtered.value.length > 0 && filtered.value.every((o) => selectedValues.value.includes(String(o.value))));

function emitValue(next) {
    emit('update:modelValue', next);
    emit('change', next);
}

function toggle(option) {
    if (props.disabled || !option) return;
    const needle = String(option.value);
    const current = props.modelValue.slice();
    const index = current.findIndex((v) => String(v) === needle);
    if (index >= 0) current.splice(index, 1);
    else current.push(option.value);
    emitValue(current);
}

function remove(option) {
    toggle(option);
}

function clearAll() {
    if (props.disabled || !props.modelValue.length) return;
    emitValue([]);
}

function toggleSelectAll() {
    if (props.disabled) return;
    if (allFilteredSelected.value) {
        const removing = new Set(filtered.value.map((o) => String(o.value)));
        emitValue(props.modelValue.filter((v) => !removing.has(String(v))));
    } else {
        const existing = new Set(selectedValues.value);
        const next = props.modelValue.slice();
        filtered.value.forEach((o) => {
            if (!existing.has(String(o.value))) next.push(o.value);
        });
        emitValue(next);
    }
}

function onKeydown(event) {
    const list = filtered.value;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        if (!list.length) return;
        const delta = event.key === 'ArrowDown' ? 1 : -1;
        highlighted.value = highlighted.value < 0
            ? (delta > 0 ? 0 : list.length - 1)
            : ((highlighted.value + delta) % list.length + list.length) % list.length;
        nextTick(() => listbox.value?.querySelector(`[data-index="${highlighted.value}"]`)?.scrollIntoView({ block: 'nearest' }));
    } else if (event.key === 'Enter') {
        if (highlighted.value >= 0 && list[highlighted.value]) {
            event.preventDefault();
            toggle(list[highlighted.value]);
        }
    } else if (event.key === 'Backspace' && query.value === '' && props.modelValue.length) {
        const last = selectedOptions.value[selectedOptions.value.length - 1] || normalize(props.modelValue[props.modelValue.length - 1]);
        toggle(last);
    }
}

watch(filtered, (list) => {
    if (highlighted.value >= list.length) highlighted.value = list.length - 1;
});
</script>

<template>
    <div class="smart-multi">
        <label v-if="label" :for="id" class="form-label">{{ label }}</label>
        <div class="smart-multi-box border rounded p-2" :class="{ 'is-invalid': error, 'is-disabled': disabled }">
            <div v-if="selectedOptions.length" class="d-flex flex-wrap gap-1 mb-2" aria-live="polite">
                <button
                    v-for="option in selectedOptions"
                    :key="option.value"
                    type="button"
                    class="badge rounded-pill border-0 bg-secondary-subtle text-dark"
                    :aria-label="`Remove ${option.label}`"
                    :disabled="disabled"
                    @click="remove(option)"
                >
                    {{ option.label }} ×
                </button>
                <button type="button" class="badge rounded-pill border-0 bg-transparent text-muted text-decoration-underline" :disabled="disabled" @click="clearAll">
                    Clear all
                </button>
            </div>
            <input
                :id="id"
                v-model="query"
                type="search"
                class="form-control form-control-sm mb-2"
                :placeholder="placeholder"
                :disabled="disabled"
                role="combobox"
                aria-expanded="true"
                aria-autocomplete="list"
                autocomplete="off"
                @keydown="onKeydown"
            />
            <div v-if="allowSelectAll && filtered.length" class="px-2 pb-1">
                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" :disabled="disabled" @click="toggleSelectAll">
                    {{ allFilteredSelected ? 'Deselect all matches' : `Select all ${filtered.length} matches` }}
                </button>
            </div>
            <div ref="listbox" class="smart-multi-list" role="listbox" aria-multiselectable="true">
                <label
                    v-for="(option, index) in filtered"
                    :key="option.value"
                    :data-index="index"
                    class="d-flex gap-2 align-items-start px-2 py-1 rounded"
                    :class="{ 'is-highlighted': index === highlighted }"
                    @mouseenter="highlighted = index"
                >
                    <input
                        type="checkbox"
                        class="form-check-input mt-1"
                        :checked="selectedValues.includes(String(option.value))"
                        :disabled="disabled"
                        @change="toggle(option)"
                    />
                    <span>
                        <span class="d-block">{{ option.label }}</span>
                        <small v-if="option.meta" class="text-muted">{{ option.meta }}</small>
                    </span>
                </label>
                <p v-if="!filtered.length && !loading" class="small text-muted mb-0 px-2 py-1">{{ emptyText }}</p>
                <p v-if="loading" class="small text-muted mb-0 px-2 py-1"><span class="spinner-border spinner-border-sm" role="status"></span> Loading...</p>
            </div>
        </div>
        <small v-if="error" class="text-danger">{{ error }}</small>
    </div>
</template>

<style scoped>
.smart-multi-box.is-disabled { opacity: .65; }
.smart-multi-box.is-invalid { border-color: #dc3545 !important; }
.smart-multi-list { max-height: 190px; overflow-y: auto; }
.smart-multi-list label { cursor: pointer; }
.smart-multi-list label:hover, .smart-multi-list label.is-highlighted { background: rgba(148,163,184,.12); }
</style>
