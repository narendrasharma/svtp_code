<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({
    email: { type: String, default: '' },
    marketing_email_opt_in: { type: Boolean, default: true },
});

function confirm() {
    router.post(window.location.href, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <div class="container py-5" style="max-width: 560px;">
            <h1 class="mb-1">Email preferences</h1>
            <p class="text-muted mb-4">{{ email }}</p>

            <div class="card p-4">
                <p v-if="marketing_email_opt_in">You currently receive our promotional emails. Unsubscribing stops <strong>marketing messages only</strong> — booking confirmations, receipts and account emails are unaffected.</p>
                <p v-else class="alert alert-success small">You are already unsubscribed from marketing messages. Booking and account emails are unaffected.</p>
                <button v-if="marketing_email_opt_in" type="button" class="btn btn-svtp" @click="confirm">Unsubscribe from marketing</button>
            </div>
        </div>
    </AppLayout>
</template>
