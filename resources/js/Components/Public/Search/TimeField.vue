<script setup>
import { computed } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Time' },
    id: { type: String, default: 'public-time' },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);
const time = computed(() => {
    if (!/^\d{2}:\d{2}$/.test(props.modelValue)) return null;
    const [hours, minutes] = props.modelValue.split(':').map(Number);
    return hours < 24 && minutes < 60 ? { hours, minutes } : null;
});

function update(value) {
    if (!value) return emit('update:modelValue', '');
    emit('update:modelValue', `${String(value.hours).padStart(2, '0')}:${String(value.minutes).padStart(2, '0')}`);
}
</script>

<template>
    <div class="public-field public-picker-field">
        <label class="public-field__label" :for="id">{{ label }}</label>
        <VueDatePicker
            :model-value="time"
            :time-config="{ is24: false, enableSeconds: false }"
            :input-attrs="{ id, required, autocomplete: 'off', hideInputIcon: true }"
            :ui="{ input: 'public-picker-input', menu: 'public-picker-menu' }"
            :placeholder="label"
            :teleport="true"
            time-picker
            auto-apply
            @update:model-value="update"
        />
        <small v-if="error" class="public-field__error">{{ error }}</small>
    </div>
</template>
