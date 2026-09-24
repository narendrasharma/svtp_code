<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: 0 },
    label: { type: String, default: 'Quantity' },
    id: { type: String, default: 'public-quantity' },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 99 },
    step: { type: Number, default: 1 },
    decreaseLabel: { type: String, default: 'Decrease' },
    increaseLabel: { type: String, default: 'Increase' },
});

const emit = defineEmits(['update:modelValue']);
const numericValue = computed(() => Number.isFinite(Number(props.modelValue)) ? Number(props.modelValue) : props.min);
const canDecrease = computed(() => numericValue.value > props.min);
const canIncrease = computed(() => numericValue.value < props.max);

function clamp(value) {
    return Math.min(props.max, Math.max(props.min, Math.round(value / props.step) * props.step));
}

function setValue(value) {
    const next = Number(value);
    emit('update:modelValue', clamp(Number.isFinite(next) ? next : props.min));
}

function adjust(delta) {
    setValue(numericValue.value + delta * props.step);
}
</script>

<template>
    <div class="public-field public-field--quantity">
        <span class="public-field__label" :id="`${id}-label`">{{ label }}</span>
        <div class="public-quantity" role="group" :aria-labelledby="`${id}-label`">
            <button type="button" class="public-quantity__button" :disabled="!canDecrease" :aria-label="`${decreaseLabel} ${label}`" @click="adjust(-1)">−</button>
            <input :id="id" class="public-quantity__input" type="number" :value="numericValue" :min="min" :max="max" :step="step" inputmode="numeric" :aria-label="label" @input="setValue($event.target.value)">
            <button type="button" class="public-quantity__button" :disabled="!canIncrease" :aria-label="`${increaseLabel} ${label}`" @click="adjust(1)">+</button>
        </div>
    </div>
</template>
