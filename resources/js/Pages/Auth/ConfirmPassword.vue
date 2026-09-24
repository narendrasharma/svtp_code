<script setup>
import { useForm } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import PublicAuthShell from '../../Components/Public/Layout/PublicAuthShell.vue';

const form = useForm({ password: '' });
</script>

<template>
    <PublicAuthShell title="Confirm your password" intro="For your security, enter your password to continue.">
        <form class="public-auth-form" @submit.prevent="form.post(appUrl('/confirm-password'), { onFinish: () => form.reset('password') })">
            <label class="public-auth-field" for="confirm-password">Password<input id="confirm-password" v-model="form.password" class="public-input" type="password" autocomplete="current-password" required autofocus></label>
            <small v-if="form.errors.password" class="public-auth-error">{{ form.errors.password }}</small>
            <button type="submit" class="public-button public-button--primary" :disabled="form.processing">Confirm password</button>
        </form>
    </PublicAuthShell>
</template>
