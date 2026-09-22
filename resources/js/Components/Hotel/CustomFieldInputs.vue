<script setup>
import { computed } from 'vue';

const props = defineProps({
    fields: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) },
    prefix: { type: String, default: 'cf' },
});

const emit = defineEmits(['update:modelValue']);

function set(id, value) {
    emit('update:modelValue', { ...props.modelValue, [id]: value });
}

function get(id, type) {
    const value = props.modelValue?.[id];
    if (value === undefined || value === null) {
        return type === 'multiselect' ? [] : type === 'boolean' ? false : '';
    }
    return value;
}

function toggleMulti(id, optionValue, checked) {
    const current = [...get(id, 'multiselect')];
    const next = checked ? [...new Set([...current, optionValue])] : current.filter(v => v !== optionValue);
    set(id, next);
}

const groups = computed(() => {
    const out = [];
    const seen = {};
    for (const field of props.fields) {
        const group = field.group || 'Additional Information';
        if (!seen[group]) { seen[group] = []; out.push({ name: group, fields: seen[group] }); }
        seen[group].push(field);
    }
    return out;
});
</script>
<template>
<div v-if="fields.length">
<div v-for="group in groups" :key="group.name" class="mb-3">
<h6 class="text-muted text-uppercase small">{{ group.name }}</h6>
<div class="row g-3">
<div v-for="field in group.fields" :key="field.id" class="col-md-6">
<label class="form-label" :for="`${prefix}-${field.id}`">{{ field.label }}<span v-if="field.required" class="text-danger"> *</span></label>
<input v-if="field.type === 'text'" :id="`${prefix}-${field.id}`" :value="get(field.id, field.type)" :placeholder="field.placeholder ?? ''" maxlength="500" class="form-control" @input="set(field.id, $event.target.value)" />
<textarea v-else-if="field.type === 'textarea'" :id="`${prefix}-${field.id}`" :value="get(field.id, field.type)" :placeholder="field.placeholder ?? ''" rows="3" maxlength="2000" class="form-control" @input="set(field.id, $event.target.value)" />
<input v-else-if="field.type === 'number'" :id="`${prefix}-${field.id}`" :value="get(field.id, field.type)" type="number" step="any" class="form-control" @input="set(field.id, $event.target.value)" />
<select v-else-if="field.type === 'select'" :id="`${prefix}-${field.id}`" :value="get(field.id, field.type)" class="form-select" @change="set(field.id, $event.target.value)">
<option value="">—</option><option v-for="o in field.options" :key="o.value" :value="o.value">{{ o.label }}</option>
</select>
<div v-else-if="field.type === 'multiselect'" class="border rounded p-2">
<label v-for="o in field.options" :key="o.value" class="form-check"><input :checked="get(field.id, field.type).includes(o.value)" type="checkbox" class="form-check-input" @change="toggleMulti(field.id, o.value, $event.target.checked)" /> {{ o.label }}</label>
</div>
<div v-else-if="field.type === 'boolean'" class="form-check"><input :id="`${prefix}-${field.id}`" :checked="!!get(field.id, field.type)" type="checkbox" class="form-check-input" @change="set(field.id, $event.target.checked)" /><label class="form-check-label" :for="`${prefix}-${field.id}`">Yes</label></div>
<input v-else-if="field.type === 'date'" :id="`${prefix}-${field.id}`" :value="get(field.id, field.type)" type="date" class="form-control" @change="set(field.id, $event.target.value)" />
<input v-else-if="field.type === 'url'" :id="`${prefix}-${field.id}`" :value="get(field.id, field.type)" type="url" maxlength="1000" placeholder="https://" class="form-control" @input="set(field.id, $event.target.value)" />
<input v-else-if="field.type === 'email'" :id="`${prefix}-${field.id}`" :value="get(field.id, field.type)" type="email" maxlength="150" class="form-control" @input="set(field.id, $event.target.value)" />
<div v-if="field.help" class="form-text">{{ field.help }}</div>
</div>
</div>
</div>
</div>
</template>
