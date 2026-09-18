<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    health: Object,
    operations: { type: Object, default: () => ({}) },
    queue_worker_docs: { type: Object, default: () => ({}) },
});

const form = useForm({
    ops_reminders_enabled: props.operations['ops.reminders_enabled'] === '1',
    ops_followup_reminders_enabled: props.operations['ops.followup_reminders_enabled'] === '1',
    ops_quotation_expiry_enabled: props.operations['ops.quotation_expiry_enabled'] === '1',
    ops_quotation_expiry_reminder_days: props.operations['ops.quotation_expiry_reminder_days'] ?? '3',
    ops_payment_reminder_offsets: props.operations['ops.payment_reminder_offsets'] ?? '3,1,0',
    ops_travel_reminder_customer_offsets: props.operations['ops.travel_reminder_customer_offsets'] ?? '3,1',
    ops_travel_reminder_vendor_offsets: props.operations['ops.travel_reminder_vendor_offsets'] ?? '3,1',
    ops_campaigns_scheduled_enabled: props.operations['ops.campaigns_scheduled_enabled'] === '1',
    ops_admin_digest_frequency: props.operations['ops.admin_digest_frequency'] ?? 'off',
    ops_notification_retention_days: props.operations['ops.notification_retention_days'] ?? '180',
    ops_notify_lead_created: props.operations['ops.notify_lead_created'] === '1',
});

function save() {
    form.post(appUrl('/admin/settings/operations'), { preserveScroll: true });
}

function badge(status) {
    return {
        healthy: 'bg-success',
        warning: 'bg-warning text-dark',
        sync: 'bg-info text-dark',
        not_detected: 'bg-secondary',
        not_configured: 'bg-secondary',
    }[status] ?? 'bg-secondary';
}
</script>

<template>
    <AdminLayout>
        <h2 class="mt-2 mb-1">System Health</h2>
        <p class="text-muted mb-3">Live operational signals — heartbeats, queues, storage. No secrets are shown on this page, ever.</p>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card p-3 h-100">
                    <h6>Scheduler</h6>
                    <span class="badge" :class="badge(health.scheduler.status)">{{ health.scheduler.status.replace('_', ' ') }}</span>
                    <p class="small text-muted mt-2 mb-1">{{ health.scheduler.detail }}</p>
                    <p v-if="health.scheduler.last_run_at" class="small mb-0">Last run: {{ new Date(health.scheduler.last_run_at).toLocaleString('en-IN') }}</p>
                    <code class="small d-block mt-2">{{ health.scheduler.cron }}</code>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 h-100">
                    <h6>Queue worker</h6>
                    <span class="badge" :class="badge(health.queue.status)">{{ String(health.queue.status).replace('_', ' ') }}</span>
                    <p class="small text-muted mt-2 mb-1">{{ health.queue.detail }}</p>
                    <p class="small mb-0">Driver: <strong>{{ health.drivers.queue }}</strong> · Pending: <strong>{{ health.queue.pending ?? 'n/a' }}</strong> · Failed: <strong>{{ health.queue.failed }}</strong></p>
                    <p v-if="health.queue.last_failure_at" class="small mb-0">Last failure: {{ new Date(health.queue.last_failure_at).toLocaleString('en-IN') }}</p>
                    <a :href="appUrl('/admin/system/failed-jobs')" class="small">View failed jobs →</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 h-100">
                    <h6>Environment</h6>
                    <dl class="row small mb-0">
                        <dt class="col-5">App</dt><dd class="col-7">{{ health.app.name }} ({{ health.app.version }})</dd>
                        <dt class="col-5">Laravel / PHP</dt><dd class="col-7">{{ health.app.laravel }} / {{ health.app.php }}</dd>
                        <dt class="col-5">Env / Debug</dt><dd class="col-7">{{ health.app.env }} / {{ health.app.debug ? 'on' : 'off' }}</dd>
                        <dt class="col-5">Timezone</dt><dd class="col-7">{{ health.app.timezone }}</dd>
                        <dt class="col-5">Database</dt><dd class="col-7">{{ health.drivers.database }}</dd>
                        <dt class="col-5">Cache</dt><dd class="col-7">{{ health.drivers.cache }}</dd>
                        <dt class="col-5">Mail</dt><dd class="col-7">{{ health.drivers.mail }}</dd>
                        <dt class="col-5">Storage</dt><dd class="col-7">{{ health.storage.storage_writable ? 'writable' : 'NOT writable' }} · public link {{ health.storage.public_link ? 'ok' : 'missing' }}</dd>
                    </dl>
                    <p v-if="health.mail_note" class="small text-muted mt-2 mb-0">{{ health.mail_note }}</p>
                </div>
            </div>
        </div>

        <div class="card p-3 mb-4">
            <h6>Queue worker setup</h6>
            <p class="small text-muted">The database queue is the default — no Redis required. In production, keep one worker alive with Supervisor or systemd:</p>
            <code class="small d-block">{{ queue_worker_docs.command }}</code>
            <code class="small d-block mt-1">{{ queue_worker_docs.cron }}</code>
            <p class="small text-muted mt-2 mb-0">{{ queue_worker_docs.note }}</p>
        </div>

        <div class="card p-3 mb-4">
            <h6>Operations settings</h6>
            <p class="small text-muted">Master switches for scheduler-driven reminders, campaigns, digests and retention. Kept deliberately small.</p>
            <form @submit.prevent="save">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check mb-2"><input id="ops-rem" v-model="form.ops_reminders_enabled" type="checkbox" class="form-check-input" /><label for="ops-rem" class="form-check-label small">Reminders master switch</label></div>
                        <div class="form-check mb-2"><input id="ops-fol" v-model="form.ops_followup_reminders_enabled" type="checkbox" class="form-check-input" /><label for="ops-fol" class="form-check-label small">Follow-up reminders</label></div>
                        <div class="form-check mb-2"><input id="ops-quot" v-model="form.ops_quotation_expiry_enabled" type="checkbox" class="form-check-input" /><label for="ops-quot" class="form-check-label small">Quotation expiry automation</label></div>
                        <div class="form-check mb-2"><input id="ops-camp" v-model="form.ops_campaigns_scheduled_enabled" type="checkbox" class="form-check-input" /><label for="ops-camp" class="form-check-label small">Scheduled campaign dispatch</label></div>
                        <div class="form-check mb-2"><input id="ops-lead" v-model="form.ops_notify_lead_created" type="checkbox" class="form-check-input" /><label for="ops-lead" class="form-check-label small">Admin alert on manual lead creation</label></div>
                    </div>
                    <div class="col-md-4">
                        <label for="ops-qdays" class="form-label small">Quotation expiring-soon window (days)</label>
                        <input id="ops-qdays" v-model="form.ops_quotation_expiry_reminder_days" type="number" min="1" max="30" class="form-control form-control-sm mb-2" />
                        <label for="ops-pay" class="form-label small">Payment reminder offsets (days before due)</label>
                        <input id="ops-pay" v-model="form.ops_payment_reminder_offsets" class="form-control form-control-sm mb-2" placeholder="3,1,0 — empty disables auto-send" />
                        <label for="ops-tc" class="form-label small">Travel reminder offsets — customer</label>
                        <input id="ops-tc" v-model="form.ops_travel_reminder_customer_offsets" class="form-control form-control-sm mb-2" placeholder="3,1" />
                        <label for="ops-tv" class="form-label small">Travel reminder offsets — vendor</label>
                        <input id="ops-tv" v-model="form.ops_travel_reminder_vendor_offsets" class="form-control form-control-sm mb-2" placeholder="3,1" />
                    </div>
                    <div class="col-md-4">
                        <label for="ops-digest" class="form-label small">Admin digest frequency</label>
                        <select id="ops-digest" v-model="form.ops_admin_digest_frequency" class="form-select form-select-sm mb-2">
                            <option value="off">Off</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly (Mondays)</option>
                        </select>
                        <label for="ops-ret" class="form-label small">Read-notification retention (days)</label>
                        <input id="ops-ret" v-model="form.ops_notification_retention_days" type="number" min="30" max="730" class="form-control form-control-sm mb-2" />
                        <p class="small text-muted">Financial records, audit logs, bookings, support and message history are never auto-deleted.</p>
                    </div>
                </div>
                <button class="btn btn-sm btn-svtp mt-2" :disabled="form.processing">Save operations settings</button>
            </form>
        </div>
    </AdminLayout>
</template>
