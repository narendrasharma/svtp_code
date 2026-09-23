<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, required: true },
    id: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="public-check" :for="id">
        <input
            :id="id"
            type="checkbox"
            :checked="modelValue"
            @change="$emit('update:modelValue', $event.target.checked)"
        >
        <span>{{ label }}</span>
    </label>
</template>
