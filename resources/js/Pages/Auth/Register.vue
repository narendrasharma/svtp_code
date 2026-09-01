<script setup>
import { appUrl } from '../../appUrl';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', email: '', phone: '', password: '' });
</script>

<template>
    <AppLayout>
        <div class="container py-5" style="max-width: 420px;">
            <h2 class="brand-heading">Create Account</h2>
            <form @submit.prevent="form.post(appUrl('/register'))">
                <input v-model="form.name" class="form-control mb-2" placeholder="Full Name" required />
                <input v-model="form.email" type="email" class="form-control mb-2" placeholder="Email" required />
                <input v-model="form.phone" class="form-control mb-2" placeholder="Phone" />
                <input v-model="form.password" type="password" class="form-control mb-3" placeholder="Password" required />
                <button class="btn btn-svtp w-100" :disabled="form.processing">Register</button>
            </form>
        </div>
    </AppLayout>
</template>
