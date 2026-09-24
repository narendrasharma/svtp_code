<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import QuantitySelector from './QuantitySelector.vue';
import { useLocalization } from '../../../i18n';

const props = defineProps({
    id: { type: String, required: true },
    mode: { type: String, default: 'hotel' },
    adults: { type: [Number, String], default: 2 },
    children: { type: [Number, String], default: 0 },
    rooms: { type: [Number, String], default: 1 },
    label: { type: String, default: 'Guests and rooms' },
});
const emit = defineEmits(['update:adults', 'update:children', 'update:rooms']);
const { t } = useLocalization();
const root = ref(null);
const trigger = ref(null);
const panel = ref(null);
const open = ref(false);
const summary = computed(() => {
    const adults = Number(props.adults);
    const children = Number(props.children);
    if (props.mode === 'tour') return `${adults + children} ${t('common.travellers', 'travellers')}`;
    return `${adults} ${t('common.adults', 'adults')} · ${children} ${t('common.children', 'children')} · ${Number(props.rooms)} ${t('common.rooms', 'rooms')}`;
});

async function toggle() {
    open.value = !open.value;
    if (open.value) { await nextTick(); panel.value?.querySelector('button')?.focus(); }
}
function onPointerDown(event) {
    if (open.value && !root.value?.contains(event.target)) open.value = false;
}
function onKeyDown(event) {
    if (open.value && event.key === 'Escape') {
        open.value = false;
        trigger.value?.focus();
    }
}
onMounted(() => {
    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
});
onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown);
    document.removeEventListener('keydown', onKeyDown);
});
</script>

<template>
    <div ref="root" class="public-field public-occupancy-field">
        <span :id="`${id}-label`" class="public-field__label">{{ label }}</span>
        <button ref="trigger" type="button" class="public-occupancy-trigger" :aria-labelledby="`${id}-label ${id}-summary`" :aria-expanded="open" :aria-controls="`${id}-panel`" @click="toggle">
            <span :id="`${id}-summary`">{{ summary }}</span><i class="bi bi-chevron-down" aria-hidden="true"></i>
        </button>
        <div v-if="open" :id="`${id}-panel`" ref="panel" class="public-occupancy-panel" :aria-label="label">
            <QuantitySelector :id="`${id}-adults`" :model-value="adults" :label="t('common.adults', 'Adults')" :min="1" :max="mode === 'hotel' ? 20 : 60" @update:model-value="emit('update:adults', $event)" />
            <QuantitySelector :id="`${id}-children`" :model-value="children" :label="t('common.children', 'Children')" :min="0" :max="mode === 'hotel' ? 20 : 60" @update:model-value="emit('update:children', $event)" />
            <QuantitySelector v-if="mode === 'hotel'" :id="`${id}-rooms`" :model-value="rooms" :label="t('common.rooms', 'Rooms')" :min="1" :max="10" @update:model-value="emit('update:rooms', $event)" />
            <button type="button" class="public-button public-button--outline public-button--sm" @click="open = false; trigger?.focus()">{{ t('common.done', 'Done') }}</button>
        </div>
    </div>
</template>
