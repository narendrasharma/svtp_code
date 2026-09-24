<script setup>
import { ref, watch } from 'vue';
import DateField from './DateField.vue';
import TimeField from './TimeField.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, required: true },
    dateLabel: { type: String, default: 'Pickup date' },
    timeLabel: { type: String, default: 'Pickup time' },
    required: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);
const date = ref(props.modelValue.split('T')[0] || '');
const time = ref(props.modelValue.split('T')[1]?.slice(0, 5) || '');
watch(() => props.modelValue, (value) => {
    if (value === `${date.value}T${time.value}`) return;
    date.value = value.split('T')[0] || '';
    time.value = value.split('T')[1]?.slice(0, 5) || '';
});
function updateDate(value) { date.value = value; emitValue(); }
function updateTime(value) { time.value = value; emitValue(); }
function emitValue() { emit('update:modelValue', date.value && time.value ? `${date.value}T${time.value}` : ''); }
</script>

<template>
    <div class="public-local-date-time">
        <DateField :id="`${id}-date`" :model-value="date" :label="dateLabel" min="today" :required="required" @update:model-value="updateDate" />
        <TimeField :id="`${id}-time`" :model-value="time" :label="timeLabel" :required="required" @update:model-value="updateTime" />
    </div>
</template>
