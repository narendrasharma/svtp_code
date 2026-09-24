<script setup>
import { computed } from 'vue';
import { useLocalization } from '../../../i18n';

const props = defineProps({
    status: { type: String, required: true },
    kind: { type: String, default: 'booking' },
});

const { t } = useLocalization();

const labels = {
    pending: ['common.pending', 'Pending'],
    confirmed: ['common.confirmed', 'Confirmed'],
    checked_in: ['common.checked_in', 'In stay'],
    checked_out: ['common.checked_out', 'Checked out'],
    completed: ['common.completed', 'Completed'],
    cancelled: ['common.cancelled', 'Cancelled'],
    no_show: ['common.no_show', 'No show'],
    unpaid: ['common.unpaid', 'Unpaid'],
    partially_paid: ['common.partially_paid', 'Partially paid'],
    paid: ['common.paid', 'Paid'],
    refunded: ['common.refunded', 'Refunded'],
    partially_refunded: ['common.partially_refunded', 'Partially refunded'],
    failed: ['common.failed', 'Failed'],
};

const label = computed(() => {
    const [key, fallback] = labels[props.status] || [null, props.status?.replaceAll('_', ' ') || 'Unknown'];

    return key ? t(key, fallback) : fallback;
});

const tone = computed(() => {
    if (['cancelled', 'failed', 'no_show'].includes(props.status)) return 'danger';
    if (['confirmed', 'checked_in', 'completed', 'paid', 'refunded'].includes(props.status)) return 'positive';
    if (['pending', 'partially_paid', 'partially_refunded'].includes(props.status)) return 'attention';

    return 'neutral';
});
</script>

<template>
    <span class="hotel-customer-status" :class="[`hotel-customer-status--${tone}`, `hotel-customer-status--${kind}`]">
        <span class="hotel-customer-status__dot" aria-hidden="true"></span>
        <span class="visually-hidden">{{ kind === 'payment' ? t('common.payment_status', 'Payment status') : t('common.booking_status', 'Booking status') }}: </span>{{ label }}
    </span>
</template>
