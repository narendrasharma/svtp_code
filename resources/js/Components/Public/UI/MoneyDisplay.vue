<script setup>
import { computed } from 'vue';

const props = defineProps({
    money: { type: Object, default: null },
    showApprox: { type: Boolean, default: true },
});

const displayValue = computed(() => props.money?.display_formatted || props.money?.formatted || '');
const isApproximate = computed(() => props.showApprox && !!props.money?.conversion_applied);
</script>

<template>
    <span v-if="money" class="public-money">
        <span class="public-money__value">{{ displayValue }}</span>
        <span v-if="isApproximate" class="public-money__approx">approx.</span>
    </span>
    <span v-else class="text-muted">—</span>
</template>
