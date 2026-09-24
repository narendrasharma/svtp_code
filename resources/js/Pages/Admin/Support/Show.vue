<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    ticket: Object,
    staff: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({ reply: false, assign: false, close: false }) },
});

const endpoint = appUrl(`/admin/support/${props.ticket.id}`);

const replyForm = useForm({ body: '', attachments: [] });
const noteForm = useForm({ body: '' });
const assignForm = useForm({ assigned_to: props.ticket.assigned_to ?? '' });

function onFiles(event) {
    replyForm.attachments = Array.from(event.target.files ?? []).slice(0, 5);
}

function submitReply() {
    replyForm.post(`${endpoint}/replies`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => replyForm.reset('body', 'attachments'),
    });
}

function submitNote() {
    noteForm.post(`${endpoint}/notes`, {
        preserveScroll: true,
        onSuccess: () => noteForm.reset('body'),
    });
}

function submitAssign() {
    assignForm.post(`${endpoint}/assign`, { preserveScroll: true });
}

function setStatus(status, message) {
    if (!window.confirm(message)) {
        return;
    }

    router.patch(`${endpoint}/status`, { status }, { preserveScroll: true });
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="appUrl('/admin/support')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Support Tickets</Link>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                <h2 class="mb-0">{{ ticket.reference }}</h2>
                <span class="badge bg-primary">{{ ticket.status }}</span>
                <span class="badge bg-secondary">{{ ticket.priority }}</span>
                <span v-if="ticket.category" class="badge bg-light text-dark">{{ ticket.category.name }}</span>
            </div>
            <p class="mb-0 mt-1">{{ ticket.subject }}</p>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Conversation</h5>
                    <div class="d-flex flex-column gap-2">
                        <div
                            v-for="message in ticket.messages"
                            :key="message.id"
                            class="border rounded p-3"
                            :class="{ 'border-warning': message.is_internal_note }"
                        >
                            <div class="d-flex justify-content-between gap-2 small text-muted mb-1">
                                <span>
                                    {{ message.author?.name ?? 'System' }}
                                    <span v-if="message.author?.role === 'admin'" class="badge bg-light text-dark ms-1">staff</span>
                                    <span v-if="message.is_internal_note" class="badge bg-warning text-dark ms-1">internal — requester cannot see</span>
                                </span>
                                <span>{{ formatDateTime(message.created_at) }}</span>
                            </div>
                            <p class="mb-1" style="white-space: pre-wrap;">{{ message.body }}</p>
                            <ul v-if="message.attachments?.length" class="list-unstyled mb-0 small">
                                <li v-for="a in message.attachments" :key="a.id">
                                    <i class="bi bi-paperclip me-1"></i><a :href="appUrl(`/admin/support/${ticket.id}/attachments/${a.id}`)">{{ a.original_name }}</a>
                                    <span v-if="a.is_internal" class="badge bg-warning text-dark ms-1">internal</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <form v-if="permissions.reply" class="mt-4" @submit.prevent="submitReply">
                        <h6>Reply to requester</h6>
                        <textarea v-model="replyForm.body" class="form-control mb-2" rows="4" required maxlength="10000"></textarea>
                        <div v-if="replyForm.errors.body" class="text-danger small mb-2">{{ replyForm.errors.body }}</div>
                        <input type="file" multiple class="form-control mb-2" accept=".pdf,.jpg,.jpeg,.png,.webp" @change="onFiles" />
                        <button class="btn btn-sm btn-svtp" :disabled="replyForm.processing">Send Reply</button>
                    </form>

                    <form v-if="permissions.reply" class="mt-4" @submit.prevent="submitNote">
                        <h6>Internal note <small class="text-muted">(requester never sees this)</small></h6>
                        <textarea v-model="noteForm.body" class="form-control mb-2" rows="3" required maxlength="10000"></textarea>
                        <button class="btn btn-sm btn-outline-secondary" :disabled="noteForm.processing">Save Internal Note</button>
                    </form>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Requester</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Name</dt><dd class="col-7">{{ ticket.requester?.name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Type</dt><dd class="col-7">{{ ticket.requester?.role ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Email</dt><dd class="col-7 text-break">{{ ticket.requester?.email ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Phone</dt><dd class="col-7">{{ ticket.requester?.phone ?? '—' }}</dd>
                        <dt v-if="ticket.vendor_profile" class="col-5 text-muted">Business</dt><dd v-if="ticket.vendor_profile" class="col-7">{{ ticket.vendor_profile.business_name }}</dd>
                    </dl>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Linked Context</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Booking</dt>
                        <dd class="col-7">
                            <Link v-if="ticket.booking" :href="appUrl(`/admin/tour/bookings/${ticket.booking.id}`)">{{ ticket.booking.booking_reference_id }}</Link>
                            <span v-else>—</span>
                        </dd>
                        <dt class="col-5 text-muted">Lead</dt>
                        <dd class="col-7">
                            <Link v-if="ticket.lead" :href="appUrl(`/admin/leads/${ticket.lead.id}`)">{{ ticket.lead.reference }}</Link>
                            <span v-else>—</span>
                        </dd>
                        <dt class="col-5 text-muted">Quotation</dt>
                        <dd class="col-7">
                            <Link v-if="ticket.quotation" :href="appUrl(`/admin/quotations/${ticket.quotation.id}`)">{{ ticket.quotation.reference }}</Link>
                            <span v-else>—</span>
                        </dd>
                    </dl>
                </section>

                <form v-if="permissions.assign" class="card p-3 p-md-4 mb-3" @submit.prevent="submitAssign">
                    <h5 class="mb-3">Assignment</h5>
                    <select v-model="assignForm.assigned_to" class="form-select mb-2">
                        <option :value="''">Unassigned</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <button class="btn btn-sm btn-outline-primary w-100" :disabled="assignForm.processing">Save</button>
                </form>

                <section v-if="permissions.close" class="card p-3 p-md-4">
                    <h5 class="mb-3">Workflow</h5>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-success" @click="setStatus('resolved', 'Mark this ticket resolved? The requester will be notified.')">Mark Resolved</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="setStatus('closed', 'Close this ticket?')">Close Ticket</button>
                        <button type="button" class="btn btn-sm btn-outline-warning" @click="setStatus('open', 'Reopen this ticket?')">Reopen</button>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
