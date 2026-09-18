<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const props = defineProps({
    token: { type: String, required: true },
    expired: { type: Boolean, default: false },
});

const form = useForm({ password: '', password_confirmation: '' });

function submit() {
    form.post(`/invitation/${props.token}`);
}
</script>

<template>
    <GuestLayout>
        <h1 class="h4 mb-1">Claim your account</h1>
        <p class="text-muted small mb-4">Set a password to activate your account. This link works once and expires after 72 hours.</p>

        <form v-if="!expired" @submit.prevent="submit">
            <div class="mb-3">
                <label for="password" class="form-label small fw-semibold">New password</label>
                <input id="password" v-model="form.password" type="password" class="form-control" required autocomplete="new-password" />
                <div v-if="form.errors.password" class="text-danger small">{{ form.errors.password }}</div>
            </div>
            <div class="mb-3">
                <label for="password-confirmation" class="form-label small fw-semibold">Confirm password</label>
                <input id="password-confirmation" v-model="form.password_confirmation" type="password" class="form-control" required autocomplete="new-password" />
            </div>
            <div v-if="form.errors.token" class="alert alert-danger small">{{ form.errors.token }}</div>
            <button class="btn btn-svtp w-100" :disabled="form.processing">Set Password &amp; Continue</button>
        </form>
        <div v-else class="alert alert-warning small">This invitation link has expired or was already used. Please ask our team for a new one.</div>
    </GuestLayout>
</template>
