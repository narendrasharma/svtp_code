<script setup>
import { useForm } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import PublicAuthShell from '../../Components/Public/Layout/PublicAuthShell.vue';

const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
</script>

<template>
    <PublicAuthShell title="Create your account" intro="Book faster, track your trips and manage cancellations.">
        <form class="public-auth-form" @submit.prevent="form.post(appUrl('/register'))">
            <label class="public-auth-field" for="register-name">Full name<input id="register-name" v-model="form.name" class="public-input" autocomplete="name" required maxlength="255"></label><small v-if="form.errors.name" class="public-auth-error">{{ form.errors.name }}</small>
            <label class="public-auth-field" for="register-email">Email address<input id="register-email" v-model="form.email" class="public-input" type="email" autocomplete="email" required maxlength="255"></label><small v-if="form.errors.email" class="public-auth-error">{{ form.errors.email }}</small>
            <label class="public-auth-field" for="register-phone">Phone (optional)<input id="register-phone" v-model="form.phone" class="public-input" autocomplete="tel" maxlength="20"></label><small v-if="form.errors.phone" class="public-auth-error">{{ form.errors.phone }}</small>
            <label class="public-auth-field" for="register-password">Password<input id="register-password" v-model="form.password" class="public-input" type="password" autocomplete="new-password" required></label><small v-if="form.errors.password" class="public-auth-error">{{ form.errors.password }}</small>
            <label class="public-auth-field" for="register-password-confirm">Confirm password<input id="register-password-confirm" v-model="form.password_confirmation" class="public-input" type="password" autocomplete="new-password" required></label><small v-if="form.errors.password_confirmation" class="public-auth-error">{{ form.errors.password_confirmation }}</small>
            <button type="submit" class="public-button public-button--primary" :disabled="form.processing">{{ form.processing ? 'Creating…' : 'Create account' }}</button>
        </form>
        <p class="public-auth-footer">Already have an account? <a :href="appUrl('/login')">Sign in</a></p>
    </PublicAuthShell>
</template>
