<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Location' },
    placeholder: { type: String, default: 'City or destination' },
    id: { type: String, default: 'public-location' },
    suggestions: { type: Array, default: () => [] },
    expanded: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    activeIndex: { type: Number, default: -1 },
});

const emit = defineEmits(['update:modelValue', 'focus', 'keydown', 'select']);
const listId = computed(() => `${props.id}-suggestions`);
</script>

<template>
    <div class="public-field public-location-field">
        <label class="public-field__label" :for="id">{{ label }}</label>
        <span class="public-location-field__control">
            <i class="bi bi-geo-alt public-location-field__icon" aria-hidden="true"></i>
            <input
                :id="id"
                :value="modelValue"
                :placeholder="placeholder"
                class="public-input public-location-field__input"
                type="search"
                autocomplete="off"
                :aria-controls="listId"
                :aria-expanded="expanded"
                aria-autocomplete="list"
                role="combobox"
                @input="emit('update:modelValue', $event.target.value)"
                @focus="emit('focus')"
                @keydown="emit('keydown', $event)"
            >
            <span v-if="loading" class="spinner-border spinner-border-sm public-location-field__spinner" aria-label="Loading"></span>
            <ul v-if="expanded && suggestions.length" :id="listId" class="public-location-field__suggestions" role="listbox">
                <li v-for="(suggestion, index) in suggestions" :key="`${suggestion.type}-${suggestion.id}`" role="option">
                    <button type="button" :class="{ 'is-active': index === activeIndex }" @click="emit('select', suggestion)">
                        <span class="public-location-field__suggestion-icon" aria-hidden="true"><i class="bi bi-geo-alt"></i></span>
                        <span>
                            <strong>{{ suggestion.name }}</strong>
                            <small>{{ suggestion.label }}<span v-if="suggestion.subtitle"> · {{ suggestion.subtitle }}</span></small>
                        </span>
                    </button>
                </li>
            </ul>
        </span>
    </div>
</template>
