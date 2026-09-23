<script setup>
import { useLocalization } from '../../../../i18n';
import MoneyDisplay from '../../UI/MoneyDisplay.vue';

const props = defineProps({
    refunds: { type: Array, default: () => [] },
});

const { locale, t } = useLocalization();

function statusLabel(status) {
    const labels = {
        pending: t('common.refund_pending', 'Refund pending'),
        processing: t('common.refund_processing', 'Refund processing'),
        completed: t('common.refund_recorded', 'Refund recorded'),
        failed: t('common.refund_failed', 'Refund failed'),
    };

    return labels[status] || String(status || '').replaceAll('_', ' ');
}

function formatDate(value) {
    if (!value) return '';

    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { dateStyle: 'medium' }).format(new Date(value));
    } catch {
        return value;
    }
}
</script>

<template>
    <section v-if="props.refunds.length" class="hotel-customer-history" aria-labelledby="refund-history-heading">
        <div class="hotel-customer-section-heading">
            <div>
                <span class="public-eyebrow">{{ t('common.financial_history', 'Financial history') }}</span>
                <h2 id="refund-history-heading" class="public-heading public-heading--3">{{ t('common.refund_history', 'Refund history') }}</h2>
            </div>
            <i class="bi bi-receipt-cutoff hotel-customer-section-heading__icon" aria-hidden="true"></i>
        </div>
        <div class="hotel-customer-history__list">
            <div v-for="refund in props.refunds" :key="refund.refund_number" class="hotel-customer-history__row">
                <div>
                    <strong>{{ statusLabel(refund.status) }}</strong>
                    <span v-if="refund.requested_at" class="hotel-customer-history__meta">{{ formatDate(refund.requested_at) }}</span>
                    <span v-if="refund.refund_number" class="hotel-customer-history__meta">{{ refund.refund_number }}</span>
                </div>
                <MoneyDisplay :money="refund.display_money?.amount" />
            </div>
        </div>
        <p class="hotel-customer-note">{{ t('common.refund_record_note', 'This records the accounting status of your refund. It does not confirm a gateway or bank settlement.') }}</p>
    </section>
</template>
