<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import { useLocalization } from '../../../i18n';
import { calendarDateValue, parseCalendarDate } from './dateValues';

const props = defineProps({
    start: { type: String, default: '' },
    end: { type: String, default: '' },
    label: { type: String, default: 'Check-in — Check-out' },
    id: { type: String, default: 'public-stay-dates' },
    required: { type: Boolean, default: false },
});
const emit = defineEmits(['update:start', 'update:end']);
const { locale } = useLocalization();
const desktop = ref(false);
const dates = computed(() => {
    const start = parseCalendarDate(props.start);
    const end = parseCalendarDate(props.end);
    return start && end ? [start, end] : null;
});

function displayRange(value) {
    if (!Array.isArray(value) || !value[0] || !value[1]) return '';
    const format = new Intl.DateTimeFormat(locale.value || 'en', { day: 'numeric', month: 'short', year: 'numeric' });
    return `${format.format(value[0])} – ${format.format(value[1])}`;
}

function updateViewport() { desktop.value = window.innerWidth >= 720; }
function update(value) {
    if (!Array.isArray(value) || !value[0] || !value[1]) return;
    emit('update:start', calendarDateValue(value[0]));
    emit('update:end', calendarDateValue(value[1]));
}
onMounted(() => { updateViewport(); window.addEventListener('resize', updateViewport); });
onBeforeUnmount(() => window.removeEventListener('resize', updateViewport));
</script>

<template>
    <div class="public-field public-picker-field public-picker-field--range">
        <label class="public-field__label" :for="id">{{ label }}</label>
        <VueDatePicker
            :model-value="dates"
            :min-date="new Date()"
            :range="{ partialRange: false }"
            :multi-calendars="desktop ? 2 : false"
            :time-config="{ enableTimePicker: false }"
            :formats="{ input: displayRange }"
            :input-attrs="{ id, required, autocomplete: 'off', hideInputIcon: true }"
            :ui="{ input: 'public-picker-input', menu: 'public-picker-menu' }"
            :placeholder="label"
            :teleport="true"
            auto-apply
            @update:model-value="update"
        />
    </div>
</template>
