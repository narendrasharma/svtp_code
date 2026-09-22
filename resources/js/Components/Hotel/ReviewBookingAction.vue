<script setup>
import { Link } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
defineProps({ bookingId: Number, eligibility: Object, enabled: Boolean });
</script>

<template>
    <div v-if="enabled && (eligibility?.can_review || eligibility?.has_review)" class="d-flex flex-wrap align-items-center gap-2">
        <Link :href="appUrl(`/account/hotel-bookings/${bookingId}/review`)" class="btn btn-sm btn-outline-primary">{{ eligibility.has_review ? 'View Review' : 'Write Review' }}</Link>
        <span v-if="eligibility.status" class="small text-muted">Review: {{ eligibility.status === 'pending' ? 'Pending moderation' : eligibility.status === 'approved' ? 'Published' : 'Not approved' }}</span>
    </div>
</template>
