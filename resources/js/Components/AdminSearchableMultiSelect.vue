<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Search options...' },
    emptyText: { type: String, default: 'No matching options.' },
});

const emit = defineEmits(['update:modelValue']);
const search = ref('');

const selectedIds = computed(() => props.modelValue.map(Number));
const selectedOptions = computed(() => props.options.filter((option) => selectedIds.value.includes(Number(option.id))));
const filteredOptions = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (!query) return props.options;

    return props.options.filter((option) => `${option.label} ${option.meta || ''}`.toLowerCase().includes(query));
});

function toggle(optionId) {
    const normalizedId = Number(optionId);
    const nextValue = selectedIds.value.includes(normalizedId)
        ? selectedIds.value.filter((id) => id !== normalizedId)
        : [...selectedIds.value, normalizedId];

    emit('update:modelValue', nextValue);
}
</script>

<template>
    <div class="searchable-multi-select border rounded p-2">
        <div v-if="selectedOptions.length" class="d-flex flex-wrap gap-1 mb-2">
            <button
                v-for="option in selectedOptions"
                :key="option.id"
                type="button"
                class="badge rounded-pill border-0 bg-secondary-subtle text-dark"
                :aria-label="`Remove ${option.label}`"
                @click="toggle(option.id)"
            >
                {{ option.label }} ×
            </button>
        </div>
        <input v-model="search" type="search" class="form-control form-control-sm mb-2" :placeholder="placeholder">
        <div class="option-list">
            <label v-for="option in filteredOptions" :key="option.id" class="d-flex gap-2 align-items-start px-2 py-1 rounded">
                <input
                    type="checkbox"
                    class="form-check-input mt-1"
                    :checked="selectedIds.includes(Number(option.id))"
                    @change="toggle(option.id)"
                >
                <span>
                    <span class="d-block">{{ option.label }}</span>
                    <small v-if="option.meta" class="text-muted">{{ option.meta }}</small>
                </span>
            </label>
            <p v-if="!filteredOptions.length" class="small text-muted mb-0 px-2 py-1">{{ emptyText }}</p>
        </div>
    </div>
</template>

<style scoped>
.option-list { max-height: 190px; overflow-y: auto; }
.option-list label { cursor: pointer; }
.option-list label:hover { background: var(--bs-light); }
</style>
