<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { appUrl } from '../appUrl';

defineProps({ open: { type: Boolean, default: false } });
const emit = defineEmits(['close']);

const submitted = ref(false);
const form = useForm({
    enquiry_type: 'quick',
    full_name: '',
    phone: '',
});

function submit() {
    submitted.value = false;
    form.post(appUrl('/enquiries'), {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
            form.reset('full_name', 'phone');
        },
    });
}

function close() {
    submitted.value = false;
    form.clearErrors();
    emit('close');
}
</script>

<template>
    <div v-if="open" class="enquiry-modal-backdrop" role="presentation" @click.self="close">
        <section class="enquiry-modal glass-card" role="dialog" aria-modal="true" aria-labelledby="quick-enquiry-title">
            <button type="button" class="btn-close enquiry-modal-close" aria-label="Close" @click="close"></button>
            <h3 id="quick-enquiry-title" class="brand-heading mb-2">Quick Enquiry</h3>
            <p class="small text-muted">Share your details and our tour team will call you back.</p>

            <div v-if="submitted" class="alert alert-success py-2">Thank you. Your enquiry has been received.</div>

            <form @submit.prevent="submit">
                <label for="quick-enquiry-name" class="form-label">Name*</label>
                <input id="quick-enquiry-name" v-model="form.full_name" class="form-control" required />
                <div v-if="form.errors.full_name" class="text-danger small mt-1">{{ form.errors.full_name }}</div>

                <label for="quick-enquiry-phone" class="form-label mt-3">Mobile*</label>
                <input id="quick-enquiry-phone" v-model="form.phone" type="tel" class="form-control" required />
                <div v-if="form.errors.phone" class="text-danger small mt-1">{{ form.errors.phone }}</div>

                <button type="submit" class="btn btn-svtp w-100 mt-4" :disabled="form.processing">
                    {{ form.processing ? 'Submitting...' : 'Submit Enquiry' }}
                </button>
            </form>
        </section>
    </div>
</template>
