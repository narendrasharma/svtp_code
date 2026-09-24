<script setup>
import { useForm } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import PublicAuthShell from '../../Components/Public/Layout/PublicAuthShell.vue';

const props = defineProps({ email: { type: String, required: true }, token: { type: String, required: true } });
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
</script>

<template>
    <PublicAuthShell title="Choose a new password" intro="Set a password for your Triparo account.">
        <form class="public-auth-form" @submit.prevent="form.post(appUrl('/reset-password'), { onFinish: () => form.reset('password', 'password_confirmation') })">
            <label class="public-auth-field" for="reset-email">Email address<input id="reset-email" v-model="form.email" class="public-input" type="email" autocomplete="email" required></label><small v-if="form.errors.email" class="public-auth-error">{{ form.errors.email }}</small>
            <label class="public-auth-field" for="reset-password">New password<input id="reset-password" v-model="form.password" class="public-input" type="password" autocomplete="new-password" required></label><small v-if="form.errors.password" class="public-auth-error">{{ form.errors.password }}</small>
            <label class="public-auth-field" for="reset-confirm">Confirm password<input id="reset-confirm" v-model="form.password_confirmation" class="public-input" type="password" autocomplete="new-password" required></label><small v-if="form.errors.password_confirmation" class="public-auth-error">{{ form.errors.password_confirmation }}</small>
            <button type="submit" class="public-button public-button--primary" :disabled="form.processing">{{ form.processing ? 'Updating…' : 'Reset password' }}</button>
        </form>
    </PublicAuthShell>
</template>
