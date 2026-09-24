<script setup>
import BookingStatus from '../../UI/BookingStatus.vue';

defineProps({
    status: { type: String, required: true },
    kind: { type: String, default: 'booking' },
});
</script>

<template>
    <BookingStatus :status="status" :kind="kind" />
</template>
