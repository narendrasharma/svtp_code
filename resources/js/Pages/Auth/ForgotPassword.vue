<script setup>
import { useForm } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import PublicAuthShell from '../../Components/Public/Layout/PublicAuthShell.vue';

defineProps({ status: { type: String, default: null } });
const form = useForm({ email: '' });
</script>

<template>
    <PublicAuthShell title="Reset your password" intro="Enter your email and we’ll send you a secure password reset link.">
        <p v-if="status" class="public-auth-status" role="status">{{ status }}</p>
        <form class="public-auth-form" @submit.prevent="form.post(appUrl('/forgot-password'))">
            <label class="public-auth-field" for="forgot-email">Email address<input id="forgot-email" v-model="form.email" class="public-input" type="email" autocomplete="email" required autofocus></label>
            <small v-if="form.errors.email" class="public-auth-error">{{ form.errors.email }}</small>
            <button type="submit" class="public-button public-button--primary" :disabled="form.processing">{{ form.processing ? 'Sending…' : 'Email reset link' }}</button>
        </form>
        <p class="public-auth-footer"><a :href="appUrl('/login')">Back to sign in</a></p>
    </PublicAuthShell>
</template>
