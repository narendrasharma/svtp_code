<script setup>
import { appUrl } from '../../appUrl';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
</script>

<template>
    <AppLayout>
        <div class="container py-5" style="max-width: 420px;">
            <p class="section-eyebrow">Join us</p>
            <h2 class="brand-heading">Create Account</h2>
            <p class="text-muted small">Book faster, track your trips and manage cancellations.</p>
            <form @submit.prevent="form.post(appUrl('/register'))">
                <label for="register-name" class="form-label small">Full Name</label>
                <input id="register-name" v-model="form.name" class="form-control mb-1" placeholder="Full Name" required maxlength="255" />
                <small class="text-danger d-block mb-2">{{ form.errors.name }}</small>
                <label for="register-email" class="form-label small">Email</label>
                <input id="register-email" v-model="form.email" type="email" class="form-control mb-1" placeholder="Email" required maxlength="255" />
                <small class="text-danger d-block mb-2">{{ form.errors.email }}</small>
                <label for="register-phone" class="form-label small">Phone (optional)</label>
                <input id="register-phone" v-model="form.phone" class="form-control mb-1" placeholder="Phone" maxlength="20" />
                <small class="text-danger d-block mb-2">{{ form.errors.phone }}</small>
                <label for="register-password" class="form-label small">Password</label>
                <input id="register-password" v-model="form.password" type="password" class="form-control mb-1" placeholder="Password" required autocomplete="new-password" />
                <small class="text-danger d-block mb-2">{{ form.errors.password }}</small>
                <label for="register-password-confirm" class="form-label small">Confirm Password</label>
                <input id="register-password-confirm" v-model="form.password_confirmation" type="password" class="form-control mb-3" placeholder="Confirm Password" required autocomplete="new-password" />
                <button class="btn btn-svtp w-100" :disabled="form.processing">{{ form.processing ? 'Creating…' : 'Register' }}</button>
            </form>
            <p class="small text-muted mt-3 mb-0">Already have an account? <a :href="appUrl('/admin')">Sign in</a></p>
        </div>
    </AppLayout>
</template>
