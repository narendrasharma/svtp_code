<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Location' },
    placeholder: { type: String, default: 'City or destination' },
    id: { type: String, default: 'public-location' },
    required: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'select', 'input']);
const listId = computed(() => `${props.id}-suggestions`);
const suggestions = ref([]);
const expanded = ref(false);
const activeIndex = ref(-1);
const loading = ref(false);
let debounceTimer;
let requestController;

function search(value) {
    window.clearTimeout(debounceTimer);
    requestController?.abort();
    suggestions.value = [];
    expanded.value = false;
    activeIndex.value = -1;
    if (value.trim().length < 2) return;

    debounceTimer = window.setTimeout(async () => {
        const controller = new AbortController();
        requestController = controller;
        loading.value = true;
        try {
            const response = await fetch(`${appUrl('/discover/locations')}?q=${encodeURIComponent(value.trim())}&limit=8`, {
                headers: { Accept: 'application/json' }, signal: controller.signal,
            });
            if (response.ok && requestController === controller) {
                suggestions.value = (await response.json()).suggestions || [];
                expanded.value = suggestions.value.length > 0;
            }
        } catch (error) {
            if (error.name !== 'AbortError') suggestions.value = [];
        } finally {
            if (requestController === controller) loading.value = false;
        }
    }, 220);
}

function onInput(event) {
    const value = event.target.value;
    emit('update:modelValue', value);
    emit('input', value);
    search(value);
}

function select(suggestion) {
    emit('update:modelValue', suggestion.name);
    emit('select', suggestion);
    expanded.value = false;
    activeIndex.value = -1;
}

function onKeydown(event) {
    if (event.key === 'Escape') { expanded.value = false; return; }
    if (!expanded.value || !suggestions.value.length) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const step = event.key === 'ArrowDown' ? 1 : -1;
        activeIndex.value = (activeIndex.value + step + suggestions.value.length) % suggestions.value.length;
    } else if (event.key === 'Enter' && activeIndex.value >= 0) {
        event.preventDefault();
        select(suggestions.value[activeIndex.value]);
    }
}

onBeforeUnmount(() => { window.clearTimeout(debounceTimer); requestController?.abort(); });
</script>

<template>
    <div class="public-field public-location-field">
        <label class="public-field__label" :for="id">{{ label }}</label>
        <span class="public-location-field__control">
            <i class="bi bi-geo-alt public-location-field__icon" aria-hidden="true"></i>
            <input :id="id" :value="modelValue" :placeholder="placeholder" class="public-input public-location-field__input"
                type="search" autocomplete="off" :required="required" :aria-controls="listId" :aria-expanded="expanded"
                aria-autocomplete="list" :aria-activedescendant="activeIndex >= 0 ? `${listId}-${activeIndex}` : undefined"
                role="combobox" @input="onInput" @focus="modelValue.trim().length >= 2 && search(modelValue)" @keydown="onKeydown">
            <span v-if="loading" class="spinner-border spinner-border-sm public-location-field__spinner" aria-label="Loading"></span>
            <ul v-if="expanded && suggestions.length" :id="listId" class="public-location-field__suggestions" role="listbox">
                <li v-for="(suggestion, index) in suggestions" :id="`${listId}-${index}`" :key="`${suggestion.type}-${suggestion.id}`" role="option" :aria-selected="index === activeIndex">
                    <button type="button" :class="{ 'is-active': index === activeIndex }" @mousedown.prevent @click="select(suggestion)">
                        <span class="public-location-field__suggestion-icon" aria-hidden="true"><i class="bi bi-geo-alt"></i></span>
                        <span><strong>{{ suggestion.name }}</strong><small>{{ suggestion.label }}<span v-if="suggestion.subtitle"> · {{ suggestion.subtitle }}</span></small></span>
                    </button>
                </li>
            </ul>
        </span>
    </div>
</template>
