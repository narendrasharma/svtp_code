<script setup>
import { useLocalization } from '../../../../i18n';

const props = defineProps({
    entries: { type: Array, default: () => [] },
});

const { locale, t } = useLocalization();

const labels = {
    'Booking created': ['common.timeline_booked', 'Booking created'],
    'Booking status updated': ['common.timeline_status_updated', 'Booking status updated'],
    'Booking cancelled': ['common.timeline_cancelled', 'Booking cancelled'],
    'Refund accounting record created': ['common.timeline_refund_recorded', 'Refund recorded'],
    'Booking dates rescheduled': ['common.timeline_rescheduled', 'Booking dates rescheduled'],
};

function labelFor(entry) {
    const [key, fallback] = labels[entry.event] || [null, entry.event || 'Booking activity'];

    return key ? t(key, fallback) : fallback;
}

function formatDateTime(value) {
    if (!value) return '';

    try {
        return new Intl.DateTimeFormat(locale.value || 'en', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
    } catch {
        return value;
    }
}
</script>

<template>
    <ol v-if="props.entries.length" class="hotel-customer-timeline">
        <li v-for="(entry, index) in props.entries" :key="`${entry.event}-${entry.created_at}-${index}`" class="hotel-customer-timeline__entry">
            <span class="hotel-customer-timeline__marker" aria-hidden="true"><i class="bi bi-check2"></i></span>
            <div class="hotel-customer-timeline__content">
                <strong>{{ labelFor(entry) }}</strong>
                <time v-if="entry.created_at" :datetime="entry.created_at">{{ formatDateTime(entry.created_at) }}</time>
            </div>
        </li>
    </ol>
    <p v-else class="hotel-customer-muted">{{ t('common.no_timeline', 'Your booking activity will appear here.') }}</p>
</template>
