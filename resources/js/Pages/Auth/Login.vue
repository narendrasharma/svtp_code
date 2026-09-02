<script setup>
import { appUrl } from '../../appUrl';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '' });
</script>

<template>
    <div class="d-flex min-vh-100 align-items-center justify-content-center bg-cream-warm px-3">
        <div class="container py-5" style="max-width: 420px;">
            <div class="glass-card p-4">
                <h2 class="brand-heading">Admin Login</h2>
                <p class="text-muted small">Sign in to manage Shree Vrindavan Tour Packages.</p>
                <form @submit.prevent="form.post(appUrl('/admin/login'))">
                <input v-model="form.email" type="email" class="form-control mb-2" placeholder="Email" required />
                <div v-if="form.errors.email" class="text-danger small mb-2">{{ form.errors.email }}</div>
                <input v-model="form.password" type="password" class="form-control mb-3" placeholder="Password" required />
                <button class="btn btn-svtp w-100" :disabled="form.processing">Login</button>
                </form>
            </div>
        </div>
    </div>
</template>
