<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AccountLayout from '../../../Layouts/AccountLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    ticket: Object,
});

const form = useForm({ body: '', attachments: [] });

function onFiles(event) {
    form.attachments = Array.from(event.target.files ?? []).slice(0, 5);
}

function submit() {
    form.post(appUrl(`/account/support/${props.ticket.id}/replies`), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('body', 'attachments'),
    });
}
</script>

<template>
    <AccountLayout>
        <div class="container py-5" style="max-width: 800px;">
            <Link :href="appUrl('/account/support')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Support Tickets</Link>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-1">
                <h1 class="section-title mb-0">{{ ticket.reference }}</h1>
                <span class="badge bg-secondary">{{ ticket.status }}</span>
            </div>
            <p class="text-muted">{{ ticket.subject }}</p>


            <div class="d-flex flex-column gap-3 mb-4">
                <div v-for="message in ticket.messages" :key="message.id" class="glass-card p-3" :class="{ 'ms-md-5': message.user_id !== ticket.requester_user_id }">
                    <div class="d-flex justify-content-between gap-2 small text-muted mb-1">
                        <span>{{ message.author?.name ?? 'Our team' }}</span>
                        <span>{{ message.created_at ? new Date(message.created_at).toLocaleString('en-IN') : '' }}</span>
                    </div>
                    <p class="mb-1" style="white-space: pre-wrap;">{{ message.body }}</p>
                    <ul v-if="message.attachments?.length" class="list-unstyled mb-0 small">
                        <li v-for="a in message.attachments" :key="a.id"><i class="bi bi-paperclip me-1"></i><a :href="appUrl(`/account/support/${ticket.id}/attachments/${a.id}`)">{{ a.original_name }}</a></li>
                    </ul>
                </div>
            </div>

            <form v-if="!['resolved', 'closed'].includes(ticket.status)" class="glass-card p-3 p-md-4" @submit.prevent="submit">
                <h5 class="mb-3">Reply</h5>
                <textarea v-model="form.body" class="form-control mb-2" rows="4" required maxlength="10000" placeholder="Write your reply…"></textarea>
                <div v-if="form.errors.body" class="text-danger small mb-2">{{ form.errors.body }}</div>
                <input type="file" multiple class="form-control mb-3" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="onFiles" />
                <button class="btn btn-svtp" :disabled="form.processing">Send Reply</button>
            </form>
            <p v-else class="text-muted small">This ticket is {{ ticket.status }}. Open a new ticket if you need further help.</p>
        </div>
    </AccountLayout>
</template>
