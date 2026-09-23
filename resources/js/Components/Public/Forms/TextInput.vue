<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: null },
    id: { type: String, default: null },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="public-field">
        <span v-if="label" class="public-field__label">{{ label }}</span>
        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            class="public-input"
            @input="$emit('update:modelValue', $event.target.value)"
        >
    </label>
</template>
