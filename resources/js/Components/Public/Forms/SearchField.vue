<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: null },
    placeholder: { type: String, default: 'Search' },
    id: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="public-field">
        <span v-if="label" class="public-field__label">{{ label }}</span>
        <span class="position-relative">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" aria-hidden="true"></i>
            <input
                :id="id"
                :value="modelValue"
                :placeholder="placeholder"
                class="public-input ps-5"
                type="search"
                @input="$emit('update:modelValue', $event.target.value)"
            >
        </span>
    </label>
</template>
