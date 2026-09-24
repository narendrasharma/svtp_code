<script setup>
import { computed } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import { useLocalization } from '../../../i18n';
import { calendarDateValue, parseCalendarDate } from './dateValues';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Date' },
    id: { type: String, default: 'public-date' },
    min: { type: String, default: '' },
    max: { type: String, default: '' },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);
const { locale } = useLocalization();
const date = computed(() => parseCalendarDate(props.modelValue));
const minDate = computed(() => props.min === 'today' ? new Date() : parseCalendarDate(props.min));
const maxDate = computed(() => parseCalendarDate(props.max));
const displayDate = (value) => value instanceof Date
    ? new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' }).format(value)
    : '';
</script>

<template>
    <div class="public-field public-picker-field">
        <label class="public-field__label" :for="id">{{ label }}</label>
        <VueDatePicker
            :model-value="date"
            :min-date="minDate"
            :max-date="maxDate"
            :time-config="{ enableTimePicker: false }"
            :formats="{ input: displayDate }"
            :input-attrs="{ id, required, autocomplete: 'off', hideInputIcon: true }"
            :ui="{ input: 'public-picker-input', menu: 'public-picker-menu' }"
            :placeholder="label"
            :teleport="true"
            auto-apply
            @update:model-value="emit('update:modelValue', calendarDateValue($event))"
        />
        <small v-if="error" class="public-field__error">{{ error }}</small>
    </div>
</template>
