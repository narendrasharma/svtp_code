<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, required: true },
    id: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="public-check" :for="id">
        <input
            :id="id"
            type="checkbox"
            role="switch"
            :checked="modelValue"
            :disabled="disabled"
            @change="$emit('update:modelValue', $event.target.checked)"
        >
        <span>{{ label }}</span>
    </label>
</template>
