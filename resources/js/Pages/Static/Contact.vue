<script setup>
import { appUrl } from '../../appUrl';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ name: '', email: '', message: '' });
</script>

<template>
    <AppLayout>
        <div class="container py-5" style="max-width: 640px;">
            <h1 class="brand-heading">Contact Us</h1>
            <address>
                <strong>Head Office:</strong> House No. 1, Sec No. 4, Shree Radhapuram Estate,
                Near Goverdhan Chauraha, Mathura, Uttar Pradesh<br />
                <strong>Alternate Address:</strong> Behind ATK, Near Pawan Kunj, Jay Gurudev Temple, Mathura<br />
                <strong>Email:</strong> info@shreevrindavantourpackages.com
            </address>

            <form class="mt-4" @submit.prevent="form.post(appUrl('/contact'))">
                <input v-model="form.name" class="form-control mb-2" placeholder="Your Name" required />
                <input v-model="form.email" type="email" class="form-control mb-2" placeholder="Your Email" required />
                <textarea v-model="form.message" class="form-control mb-2" rows="4" placeholder="Message" required></textarea>
                <button class="btn btn-svtp">Send Message</button>
            </form>
        </div>
    </AppLayout>
</template>
