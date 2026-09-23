<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Guests' },
    placeholder: { type: String, default: 'Guests and rooms' },
});

defineEmits(['update:modelValue', 'focus']);
</script>

<template>
    <label class="public-field">
        <span class="public-field__label">{{ label }}</span>
        <input
            :value="modelValue"
            :placeholder="placeholder"
            class="public-input"
            type="text"
            readonly
            @click="$emit('focus')"
        >
    </label>
</template>
