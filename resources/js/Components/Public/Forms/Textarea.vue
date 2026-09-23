<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: null },
    placeholder: { type: String, default: '' },
    id: { type: String, default: null },
    rows: { type: Number, default: 4 },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="public-field">
        <span v-if="label" class="public-field__label">{{ label }}</span>
        <textarea
            :id="id"
            :value="modelValue"
            :placeholder="placeholder"
            :rows="rows"
            class="public-textarea"
            @input="$emit('update:modelValue', $event.target.value)"
        ></textarea>
    </label>
</template>
