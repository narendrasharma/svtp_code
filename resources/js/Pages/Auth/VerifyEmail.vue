<script setup>
import { computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';
import PublicAuthShell from '../../Components/Public/Layout/PublicAuthShell.vue';

const props = defineProps({ status: { type: String, default: null } });
const form = useForm({});
const sent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <PublicAuthShell title="Verify your email" intro="Open the link we sent to your email address to finish setting up your account.">
        <p v-if="sent" class="public-auth-status" role="status">A new verification link has been sent.</p>
        <form class="public-auth-form" @submit.prevent="form.post(appUrl('/email/verification-notification'))">
            <button type="submit" class="public-button public-button--primary" :disabled="form.processing">Resend verification email</button>
        </form>
        <p class="public-auth-footer"><Link :href="appUrl('/admin/logout')" method="post" as="button" class="public-button public-button--outline">Sign out</Link></p>
    </PublicAuthShell>
</template>
