<script setup>
import { useCurrency } from '../currency';
import { useLocalization } from '../i18n';

/**
 * Reusable visitor display-currency switcher (Phase 13B).
 * Reads the shared `currency` contract; inactive codes never appear.
 * RTL-safe: logical margin, no manual string reversal.
 */
const { selected, currencies, switchCurrency } = useCurrency();
const { t } = useLocalization();

function onChange(event) {
    const next = event.target.value;
    if (next && next !== selected.value) switchCurrency(next);
}
</script>

<template>
    <label v-if="currencies.length > 1" class="public-header__tool">
        <i class="bi bi-currency-exchange" aria-hidden="true"></i>
        <span class="visually-hidden">{{ t('common.display_currency', 'Display currency') }}</span>
        <select :value="selected" class="public-select" :aria-label="t('common.display_currency', 'Display currency')" @change="onChange">
            <option v-for="cur in currencies" :key="cur.code" :value="cur.code">
                {{ cur.code }} ({{ cur.symbol }})
            </option>
        </select>
    </label>
</template>
