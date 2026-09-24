<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({
    categories: { type: Array, default: () => [] },
    bookings: { type: Array, default: () => [] },
});

const form = useForm({
    subject: '',
    body: '',
    category_id: '',
    priority: 'normal',
    booking_id: '',
    attachments: [],
});

function onFiles(event) {
    form.attachments = Array.from(event.target.files ?? []).slice(0, 5);
}

function submit() {
    form.transform((data) => ({ ...data, category_id: data.category_id || null, booking_id: data.booking_id || null }))
        .post(appUrl('/account/support'), { forceFormData: true });
}
</script>

<template>
    <AccountLayout>
        <div class="container py-5" style="max-width: 720px;">
            <Link :href="appUrl('/account/support')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Support Tickets</Link>
            <h1 class="section-title mt-2 mb-1">Open a Support Ticket</h1>


            <form class="glass-card p-3 p-md-4" @submit.prevent="submit">
                <div class="mb-3">
                    <label for="ticket-subject" class="form-label small fw-semibold">Subject*</label>
                    <input id="ticket-subject" v-model="form.subject" class="form-control" required maxlength="255" placeholder="e.g. Need to change my travel date" />
                    <div v-if="form.errors.subject" class="text-danger small">{{ form.errors.subject }}</div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="ticket-category" class="form-label small fw-semibold">Category</label>
                        <select id="ticket-category" v-model="form.category_id" class="form-select">
                            <option value="">General</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="ticket-priority" class="form-label small fw-semibold">Priority</label>
                        <select id="ticket-priority" v-model="form.priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="ticket-booking" class="form-label small fw-semibold">Related booking</label>
                        <select id="ticket-booking" v-model="form.booking_id" class="form-select">
                            <option value="">None</option>
                            <option v-for="b in bookings" :key="b.id" :value="b.id">{{ b.booking_reference_id }}</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="ticket-body" class="form-label small fw-semibold">Message*</label>
                    <textarea id="ticket-body" v-model="form.body" class="form-control" rows="5" required maxlength="10000" placeholder="Describe the issue in detail…"></textarea>
                    <div v-if="form.errors.body" class="text-danger small">{{ form.errors.body }}</div>
                </div>
                <div class="mb-3">
                    <label for="ticket-files" class="form-label small fw-semibold">Attachments <span class="text-muted fw-normal">(PDF/JPG/PNG/WebP, max 5 MB each, up to 5)</span></label>
                    <input id="ticket-files" type="file" multiple class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="onFiles" />
                    <div v-if="form.errors.attachments" class="text-danger small">{{ form.errors.attachments }}</div>
                </div>
                <button class="btn btn-svtp" :disabled="form.processing">Submit Ticket</button>
            </form>
        </div>
    </AccountLayout>
</template>
