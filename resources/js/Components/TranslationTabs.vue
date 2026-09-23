<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';

/**
 * Reusable admin translation editor pattern (Phase 13A).
 *
 * Props:
 *  - model: 'destination' | 'page' (whitelisted server-side)
 *  - recordId: entity id
 *  - fields: [{ key, label, type: 'text'|'textarea'|'rich', max }]
 *  - locales: active languages from `translationLocales` prop
 *  - existing: { [locale]: { [field]: value } } from `translations` prop
 *
 * Inactive-locale translations stay stored but are not publicly selectable.
 */
const props = defineProps({
    model: { type: String, required: true },
    recordId: { type: [Number, String], default: null },
    fields: { type: Array, default: () => [] },
    locales: { type: Array, default: () => [] },
    existing: { type: Object, default: () => ({}) },
});

const activeLocale = ref(props.locales[0]?.locale ?? 'en');

const form = useForm({
    model: props.model,
    id: props.recordId,
    locale: activeLocale.value,
    translations: {},
});

const currentValues = computed(() => props.existing?.[activeLocale.value] ?? {});

function editValue(field) {
    return form.translations[field] ?? currentValues.value[field] ?? '';
}

function setValue(field, value) {
    form.translations = { ...form.translations, [field]: value };
}

function switchTab(locale) {
    activeLocale.value = locale;
    form.locale = locale;
    form.translations = {};
}

function submit() {
    form.locale = activeLocale.value;
    form.transform((data) => ({
        model: props.model,
        id: props.recordId,
        locale: activeLocale.value,
        translations: data.translations,
    })).post(appUrl('/admin/translations'), { preserveScroll: true });
}
</script>

<template>
    <div v-if="recordId && locales.length" class="card mt-4">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-translate"></i>
            <strong>Translations</strong>
            <span class="badge text-bg-secondary ms-1">{{ locales.length }} languages</span>
        </div>
        <div class="card-body">
            <div class="btn-group btn-group-sm mb-3" role="tablist" aria-label="Translation languages">
                <button
                    v-for="lang in locales"
                    :key="lang.code"
                    type="button"
                    role="tab"
                    :aria-selected="activeLocale === lang.locale"
                    class="btn"
                    :class="activeLocale === lang.locale ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="switchTab(lang.locale)"
                >
                    {{ lang.native_name }}
                    <span v-if="lang.is_rtl" class="badge text-bg-info ms-1">RTL</span>
                </button>
            </div>

            <div v-for="field in fields" :key="field.key" class="mb-3">
                <label class="form-label fw-semibold">{{ field.label }}</label>
                <textarea
                    v-if="field.type !== 'text'"
                    class="form-control"
                    rows="3"
                    :value="editValue(field.key)"
                    :placeholder="`Leave blank to fall back to default language`"
                    @input="setValue(field.key, $event.target.value)"
                ></textarea>
                <input
                    v-else
                    type="text"
                    class="form-control"
                    :value="editValue(field.key)"
                    :maxlength="field.max ?? 255"
                    placeholder="Leave blank to fall back to default language"
                    @input="setValue(field.key, $event.target.value)"
                />
            </div>

            <button type="button" class="btn btn-primary btn-sm" :disabled="form.processing" @click="submit">
                <span v-if="form.processing">Saving…</span>
                <span v-else><i class="bi bi-check-lg me-1"></i>Save {{ activeLocale }} translation</span>
            </button>
            <div v-if="form.errors.translations || form.errors.locale" class="text-danger small mt-2">
                {{ form.errors.translations || form.errors.locale }}
            </div>
        </div>
    </div>
</template>
