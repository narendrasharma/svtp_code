<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    value: { type: [String, Number], required: true },
    label: { type: String, required: true },
    name: { type: String, required: true },
    id: { type: String, required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="public-check" :for="id">
        <input
            :id="id"
            :name="name"
            type="radio"
            :value="value"
            :checked="modelValue === value"
            @change="$emit('update:modelValue', value)"
        >
        <span>{{ label }}</span>
    </label>
</template>
