<script setup>
import { computed, ref } from 'vue';
const props = defineProps({ modelValue: { type: String, default: '' }, label: { type: String, default: 'Icon' }, allowNone: { type: Boolean, default: true } });
const emit = defineEmits(['update:modelValue']);
const query = ref('');
const icons = [
    ['bi-building', 'Building'], ['bi-buildings', 'Buildings'], ['bi-house', 'House'], ['bi-house-heart', 'Home stay'], ['bi-shop', 'Shop'], ['bi-bank', 'Bank'], ['bi-stars', 'Stars'], ['bi-star', 'Star'], ['bi-heart', 'Heart'], ['bi-wifi', 'Wi-Fi'], ['bi-water', 'Pool / water'], ['bi-snow', 'Air conditioning'], ['bi-tv', 'TV'], ['bi-car-front', 'Car'], ['bi-taxi-front', 'Taxi'], ['bi-p-square', 'Parking'], ['bi-cup-hot', 'Dining'], ['bi-egg-fried', 'Breakfast'], ['bi-person-wheelchair', 'Accessible'], ['bi-tree', 'Garden'], ['bi-door-open', 'Room'], ['bi-lamp', 'Bedside'], ['bi-shield-check', 'Safety'], ['bi-briefcase', 'Business'], ['bi-geo-alt', 'Location'], ['bi-calendar-check', 'Calendar'], ['bi-clock', 'Clock'], ['bi-phone', 'Phone'], ['bi-person', 'Guest'], ['bi-people', 'Family'], ['bi-camera', 'Photography'], ['bi-music-note', 'Music'], ['bi-fire', 'Fireplace'], ['bi-bicycle', 'Bicycle'], ['bi-badge-check', 'Verified'],
];
const filteredIcons = computed(() => { const needle = query.value.trim().toLowerCase(); return icons.filter(([value, name]) => !needle || `${value} ${name}`.includes(needle)); });
</script>
<template>
    <div class="icon-picker">
        <label class="form-label">{{ label }}</label>
        <div class="input-group mb-2"><span class="input-group-text"><i class="bi" :class="modelValue || 'bi-dash-circle'" aria-hidden="true"></i></span><input v-model="query" type="search" class="form-control" placeholder="Search icons…" aria-label="Search icons" /><button v-if="allowNone && modelValue" type="button" class="btn btn-outline-secondary" @click="emit('update:modelValue', '')">None</button></div>
        <div class="icon-picker-grid" role="listbox" :aria-label="label"><button v-for="([value, name]) in filteredIcons" :key="value" type="button" class="icon-picker-option" :class="{ selected: modelValue === value }" :title="name" :aria-label="name" @click="emit('update:modelValue', value)"><i class="bi" :class="value" aria-hidden="true"></i><span>{{ name }}</span></button><span v-if="!filteredIcons.length" class="small text-muted p-2">No matching icons.</span></div>
    </div>
</template>
<style scoped>
.icon-picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: .35rem; max-height: 180px; overflow-y: auto; padding: .25rem; border: 1px solid var(--bs-border-color); border-radius: .375rem; }
.icon-picker-option { display: flex; flex-direction: column; align-items: center; gap: .15rem; border: 1px solid transparent; border-radius: .375rem; background: transparent; color: inherit; padding: .45rem .25rem; font-size: .7rem; }
.icon-picker-option i { font-size: 1.15rem; }
.icon-picker-option:hover, .icon-picker-option:focus-visible, .icon-picker-option.selected { border-color: var(--bs-primary); background: rgba(13, 110, 253, .1); }
</style>
