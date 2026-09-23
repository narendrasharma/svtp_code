<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Date' },
    type: { type: String, default: 'date' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="public-field">
        <span class="public-field__label">{{ label }}</span>
        <input
            :value="modelValue"
            :type="type"
            class="public-input"
            @input="$emit('update:modelValue', $event.target.value)"
        >
    </label>
</template>
