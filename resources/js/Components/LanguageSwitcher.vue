<script setup>
import { useLocalization } from '../i18n';

/**
 * Reusable customer language switcher (Phase 13A).
 * Reads the shared `localization` contract; inactive languages never appear.
 */
const { locale, languages, switchLocale, t } = useLocalization();

function onChange(event) {
    const next = event.target.value;
    if (next && next !== locale.value) switchLocale(next);
}
</script>

<template>
    <label v-if="languages.length > 1" class="public-header__tool">
        <i class="bi bi-translate" aria-hidden="true"></i>
        <span class="visually-hidden">{{ t('common.language', 'Language') }}</span>
        <select :value="locale" class="public-select" :aria-label="t('common.language', 'Language')" @change="onChange">
            <option v-for="lang in languages" :key="lang.code" :value="lang.locale">
                {{ lang.native_name }}
            </option>
        </select>
    </label>
</template>
