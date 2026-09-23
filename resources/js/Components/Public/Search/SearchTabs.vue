<script setup>
defineProps({
    tabs: { type: Array, default: () => [] },
    modelValue: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="public-search-tabs" role="tablist">
        <button
            v-for="tab in tabs"
            :key="tab.key ?? tab"
            type="button"
            role="tab"
            :id="`homepage-tab-${tab.key ?? tab}`"
            :aria-controls="`homepage-panel-${tab.key ?? tab}`"
            class="public-search-tab"
            :class="{ 'is-active': modelValue === (tab.key ?? tab) }"
            :aria-selected="modelValue === (tab.key ?? tab)"
            @click="$emit('update:modelValue', tab.key ?? tab)"
        >
            {{ tab.label ?? tab }}
        </button>
    </div>
</template>
