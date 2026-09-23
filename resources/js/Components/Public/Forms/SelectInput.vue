<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: null },
    id: { type: String, default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Select' },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="public-field">
        <span v-if="label" class="public-field__label">{{ label }}</span>
        <select
            :id="id"
            :value="modelValue"
            :disabled="disabled"
            class="public-select-input"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option value="">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value ?? option" :value="option.value ?? option">
                {{ option.label ?? option }}
            </option>
        </select>
    </label>
</template>
