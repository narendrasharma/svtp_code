<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    campaign: Object,
    audienceCount: { type: Number, default: 0 },
    deliveries: Object,
    canSend: { type: Boolean, default: false },
    canSchedule: { type: Boolean, default: false },
    timezone: { type: String, default: 'UTC' },
});

const endpoint = appUrl(`/admin/campaigns/${props.campaign.id}`);

const scheduleForm = useForm({
    scheduled_at: '',
});

// Client-side convenience only — the backend rejects past dates.
const minSchedule = computed(() => {
    const now = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
    return now.toISOString().slice(0, 16);
});

const showScheduler = ref(false);

function send() {
    if (!window.confirm(`Send "${props.campaign.name}" to ~${props.audienceCount} recipients? Opted-out users are skipped automatically.`)) {
        return;
    }

    router.post(`${endpoint}/send`, {}, { preserveScroll: true });
}

function schedule() {
    if (!scheduleForm.scheduled_at) {
        return;
    }

    scheduleForm.post(`${endpoint}/schedule`, { preserveScroll: true });
}

function cancel() {
    if (!window.confirm('Cancel this campaign?')) {
        return;
    }

    router.post(`${endpoint}/cancel`, {}, { preserveScroll: true });
}

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="appUrl('/admin/campaigns')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Campaigns</Link>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                <h2 class="mb-0">{{ campaign.reference }}</h2>
                <span class="badge bg-secondary">{{ campaign.status }}</span>
            </div>
            <p class="text-muted mb-0">{{ campaign.name }} · {{ campaign.channel }} · {{ campaign.audience_type }}</p>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-1">{{ campaign.subject }}</h5>
                    <p class="mb-0" style="white-space: pre-wrap;">{{ campaign.content }}</p>
                </section>

                <section class="card p-3 p-md-4">
                    <h5 class="mb-3">Deliveries</h5>
                    <div class="table-responsive">
                        <table class="table align-middle small mb-0">
                            <thead><tr><th>Recipient</th><th>Status</th><th>Sent</th><th>Error</th></tr></thead>
                            <tbody>
                                <tr v-for="d in deliveries.data" :key="d.id">
                                    <td>{{ d.user?.name }} <span class="text-muted">{{ d.user?.email }}</span></td>
                                    <td><span class="badge" :class="d.status === 'sent' ? 'bg-success' : d.status === 'failed' ? 'bg-danger' : 'bg-secondary'">{{ d.status }}</span></td>
                                    <td class="text-muted">{{ d.sent_at ? new Date(d.sent_at).toLocaleString('en-IN') : '—' }}</td>
                                    <td class="text-danger">{{ d.error || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="!deliveries.data.length" class="text-muted small mb-0">No deliveries yet — press Send Now or Schedule.</p>
                    <Pagination :links="deliveries.links" />
                </section>
            </div>

            <div class="col-lg-4">
                <section class="card p-3 p-md-4">
                    <h5 class="mb-3">Send</h5>
                    <dl class="row mb-3 small">
                        <dt class="col-6 text-muted">Audience</dt><dd class="col-6 text-end">{{ audienceCount }}</dd>
                        <dt class="col-6 text-muted">Created by</dt><dd class="col-6 text-end">{{ campaign.creator?.name ?? '—' }}</dd>
                        <dt class="col-6 text-muted">Sent at</dt><dd class="col-6 text-end">{{ formatDateTime(campaign.sent_at) }}</dd>
                        <dt v-if="campaign.scheduled_at" class="col-6 text-muted">Scheduled for</dt>
                        <dd v-if="campaign.scheduled_at" class="col-6 text-end"><span class="badge bg-info text-dark">{{ formatDateTime(campaign.scheduled_at) }}</span></dd>
                    </dl>
                    <div class="d-grid gap-2">
                        <Link v-if="campaign.status === 'draft'" :href="appUrl(`/admin/campaigns/${campaign.id}/edit`)" class="btn btn-sm btn-outline-primary">Edit Draft</Link>
                        <button v-if="canSend" type="button" class="btn btn-sm btn-svtp" @click="send">Send Now</button>
                        <button v-if="canSchedule && !showScheduler" type="button" class="btn btn-sm btn-outline-secondary" @click="showScheduler = true">Schedule…</button>
                        <button v-if="['draft', 'scheduled', 'sending'].includes(campaign.status)" type="button" class="btn btn-sm btn-outline-danger" @click="cancel">Cancel</button>
                    </div>
                    <form v-if="canSchedule && showScheduler" class="mt-3 border-top pt-3" @submit.prevent="schedule">
                        <label for="campaign-scheduled-at" class="form-label small">Send at ({{ timezone }})</label>
                        <input id="campaign-scheduled-at" v-model="scheduleForm.scheduled_at" type="datetime-local" class="form-control form-control-sm" :min="minSchedule" required />
                        <div class="form-text">Must be in the future. The scheduler dispatches it automatically; cancel anytime before then.</div>
                        <div v-if="scheduleForm.errors.scheduled_at" class="text-danger small mt-1">{{ scheduleForm.errors.scheduled_at }}</div>
                        <button class="btn btn-sm btn-svtp w-100 mt-2" :disabled="scheduleForm.processing">Confirm Schedule</button>
                    </form>
                    <p class="text-muted small mt-2 mb-0">Sends run as a queued job in chunks; without a queue worker they execute inline. Re-sends never duplicate (one row per recipient).</p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
