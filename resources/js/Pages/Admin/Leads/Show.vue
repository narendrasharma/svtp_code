<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    lead: Object,
    sources: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    serviceTypes: { type: Array, default: () => [] },
    followUpTypes: { type: Array, default: () => [] },
    followUpStatuses: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({ update: false, assign: false, convert: false }) },
});

const endpoint = appUrl(`/admin/leads/${props.lead.id}`);

const assignForm = useForm({ assigned_to: props.lead.assigned_to ?? '' });
const statusForm = useForm({ status: props.lead.status, lost_reason: props.lead.lost_reason ?? '' });
const noteForm = useForm({ body: '' });
const followUpForm = useForm({ due_at: '', type: 'call', assigned_to: '', note: '' });
const convertForm = useForm({ customer_user_id: '', name: '', phone: '', email: '' });
const customerResults = ref([]);
const customerLoading = ref(false);
let customerSeq = 0;

function submitAssign() {
    assignForm.post(`${endpoint}/assign`, { preserveScroll: true });
}
function submitStatus() {
    statusForm.patch(`${endpoint}/status`, { preserveScroll: true });
}
function submitNote() {
    noteForm.post(`${endpoint}/notes`, { preserveScroll: true, onSuccess: () => noteForm.reset('body') });
}
function submitFollowUp() {
    followUpForm.post(`${endpoint}/follow-ups`, { preserveScroll: true, onSuccess: () => followUpForm.reset() });
}
function completeFollowUp(id) {
    router.patch(appUrl(`/admin/follow-ups/${id}/complete`), {}, { preserveScroll: true });
}
function cancelFollowUp(id) {
    router.patch(appUrl(`/admin/follow-ups/${id}/cancel`), {}, { preserveScroll: true });
}
function submitConvert() {
    convertForm.post(`${endpoint}/convert`, { preserveScroll: true });
}

async function searchCustomers(query) {
    const seq = ++customerSeq;
    customerLoading.value = true;
    try {
        const response = await axios.get(appUrl('/admin/customers/search'), { params: { q: query } });
        if (seq !== customerSeq) return [];
        customerResults.value = response.data.options ?? [];
        return customerResults.value;
    } catch (e) {
        return [];
    } finally {
        if (seq === customerSeq) customerLoading.value = false;
    }
}

const pendingFollowUps = computed(() => (props.lead.follow_ups ?? []).filter((f) => f.status === 'pending'));
const pastFollowUps = computed(() => (props.lead.follow_ups ?? []).filter((f) => f.status !== 'pending'));

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('en-IN') : '—';
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="appUrl('/admin/leads')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Leads</Link>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                <h2 class="mb-0">{{ lead.reference }}</h2>
                <span class="badge bg-primary">{{ lead.status }}</span>
                <span class="badge bg-secondary">{{ lead.priority }}</span>
                <span class="badge bg-light text-dark">{{ lead.service_type }}</span>
            </div>
            <p class="text-muted mb-0 mt-1">{{ lead.name }} · {{ lead.phone }}<span v-if="lead.email"> · {{ lead.email }}</span></p>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card p-3 p-md-4 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Lead Details</h5>
                        <Link v-if="permissions.update" :href="`${endpoint}/edit`" class="btn btn-sm btn-outline-primary">Edit</Link>
                    </div>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Source</dt><dd class="col-sm-8">{{ lead.source?.name ?? '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Interest</dt><dd class="col-sm-8">{{ lead.product_title || lead.destination || '—' }}</dd>
                        <dt class="col-sm-4 text-muted">Travel</dt><dd class="col-sm-8">{{ formatDate(lead.travel_start_date) }} → {{ formatDate(lead.travel_end_date) }}<span v-if="lead.adults"> · {{ lead.adults }} adult(s)</span><span v-if="lead.children">, {{ lead.children }} child(ren)</span></dd>
                        <dt v-if="lead.budget" class="col-sm-4 text-muted">Budget</dt><dd v-if="lead.budget" class="col-sm-8">₹{{ lead.budget }}</dd>
                        <dt class="col-sm-4 text-muted">Assignee</dt><dd class="col-sm-8">{{ lead.assignee?.name ?? 'Unassigned' }}</dd>
                        <dt class="col-sm-4 text-muted">Customer</dt><dd class="col-sm-8">{{ lead.customer?.name ?? 'Not linked' }}</dd>
                        <dt v-if="lead.enquiry" class="col-sm-4 text-muted">From enquiry</dt><dd v-if="lead.enquiry" class="col-sm-8">#{{ lead.enquiry.id }} ({{ lead.enquiry.enquiry_type }})</dd>
                        <dt v-if="lead.converted_booking" class="col-sm-4 text-muted">Booking</dt><dd v-if="lead.converted_booking" class="col-sm-8"><Link :href="appUrl(`/admin/bookings/${lead.converted_booking.id}`)">{{ lead.converted_booking.booking_reference_id }}</Link></dd>
                        <dt v-if="lead.lost_reason" class="col-sm-4 text-muted">Lost reason</dt><dd v-if="lead.lost_reason" class="col-sm-8">{{ lead.lost_reason }}</dd>
                    </dl>
                    <p v-if="lead.summary" class="small mt-3 mb-0"><span class="text-muted">Summary:</span> {{ lead.summary }}</p>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Follow-ups</h5>
                    <div v-if="pendingFollowUps.length" class="table-responsive mb-3">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>Due</th><th>Type</th><th>Assignee</th><th>Note</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="f in pendingFollowUps" :key="f.id">
                                    <td class="text-nowrap">{{ formatDateTime(f.due_at) }}</td>
                                    <td>{{ f.type }}</td>
                                    <td>{{ f.assignee?.name ?? '—' }}</td>
                                    <td>{{ f.note || '—' }}</td>
                                    <td class="text-end text-nowrap">
                                        <button v-if="permissions.update" type="button" class="btn btn-sm btn-success me-1" @click="completeFollowUp(f.id)">Done</button>
                                        <button v-if="permissions.update" type="button" class="btn btn-sm btn-outline-secondary" @click="cancelFollowUp(f.id)">Cancel</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-muted small">No pending follow-ups.</p>
                    <div v-if="pastFollowUps.length" class="mb-3">
                        <h6 class="small text-muted">History</h6>
                        <ul class="list-unstyled mb-0 small">
                            <li v-for="f in pastFollowUps" :key="f.id" class="border-bottom py-1">{{ formatDateTime(f.due_at) }} · {{ f.type }} · {{ f.status }}{{ f.note ? ` — ${f.note}` : '' }}</li>
                        </ul>
                    </div>
                    <form v-if="permissions.update" @submit.prevent="submitFollowUp" class="row g-2 align-items-end">
                        <div class="col-md-4"><label for="fu-due" class="form-label small">Due at*</label><input id="fu-due" v-model="followUpForm.due_at" type="datetime-local" class="form-control form-control-sm" required /></div>
                        <div class="col-md-2"><label for="fu-type" class="form-label small">Type</label><select id="fu-type" v-model="followUpForm.type" class="form-select form-select-sm"><option v-for="t in followUpTypes" :key="t.value" :value="t.value">{{ t.label }}</option></select></div>
                        <div class="col-md-3"><label for="fu-assignee" class="form-label small">Assignee</label><select id="fu-assignee" v-model="followUpForm.assigned_to" class="form-select form-select-sm"><option value="">Default</option><option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option></select></div>
                        <div class="col-md-3"><button class="btn btn-sm btn-svtp w-100" :disabled="followUpForm.processing">Schedule</button></div>
                        <div class="col-12"><label for="fu-note" class="form-label small">Note</label><input id="fu-note" v-model="followUpForm.note" class="form-control form-control-sm" maxlength="2000" /></div>
                    </form>
                    <div v-if="followUpForm.hasErrors" class="text-danger small mt-2"><div v-for="(e, k) in followUpForm.errors" :key="k">{{ e }}</div></div>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Quotations</h5>
                    <div v-if="lead.quotations?.length" class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>Reference</th><th class="text-end">Total</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="q in lead.quotations" :key="q.id">
                                    <td>{{ q.reference }}{{ q.revision_number > 1 ? ` (Rev ${q.revision_number})` : '' }}</td>
                                    <td class="text-end">{{ q.total_amount }}</td>
                                    <td>{{ q.status }}</td>
                                    <td class="text-end"><Link :href="appUrl(`/admin/quotations/${q.id}`)" class="btn btn-sm btn-outline-primary">Open</Link></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-muted small mb-0">No quotations yet.</p>
                </section>

                <section class="card p-3 p-md-4">
                    <h5 class="mb-3">Timeline</h5>
                    <ul v-if="lead.timeline?.length" class="list-unstyled mb-0 small">
                        <li v-for="entry in lead.timeline" :key="entry.id" class="border-bottom py-2">
                            <div><strong>{{ entry.event }}</strong><span v-if="entry.metadata?.to"> → {{ entry.metadata.to_name ?? entry.metadata.to }}</span><span v-if="entry.metadata?.reference"> · {{ entry.metadata.reference }}</span></div>
                            <div v-if="entry.metadata?.note" class="text-muted">{{ entry.metadata.note }}</div>
                            <div class="text-muted">{{ entry.actor?.name || 'System' }} · {{ formatDateTime(entry.created_at) }}</div>
                        </li>
                    </ul>
                    <p v-else class="text-muted small mb-0">No timeline entries.</p>
                    <form v-if="permissions.update" @submit.prevent="submitNote" class="row g-2 align-items-end mt-3">
                        <div class="col-md-9"><label for="lead-note" class="form-label small">Add note</label><input id="lead-note" v-model="noteForm.body" class="form-control form-control-sm" maxlength="2000" required /></div>
                        <div class="col-md-3"><button class="btn btn-sm btn-outline-secondary w-100" :disabled="noteForm.processing">Add Note</button></div>
                    </form>
                </section>
            </div>

            <div class="col-lg-4">
                <form v-if="permissions.assign" class="card p-3 p-md-4 mb-3" @submit.prevent="submitAssign">
                    <h5 class="mb-3">Assignment</h5>
                    <label for="assign-to" class="form-label small">Assign to</label>
                    <select id="assign-to" v-model="assignForm.assigned_to" class="form-select mb-2">
                        <option value="">Unassigned</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <button class="btn btn-sm btn-outline-primary w-100" :disabled="assignForm.processing">Save Assignment</button>
                </form>

                <form v-if="permissions.update" class="card p-3 p-md-4 mb-3" @submit.prevent="submitStatus">
                    <h5 class="mb-3">Pipeline Status</h5>
                    <label for="lead-status" class="form-label small">Status</label>
                    <select id="lead-status" v-model="statusForm.status" class="form-select mb-2">
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <div v-if="statusForm.status === 'lost'">
                        <label for="lost-reason" class="form-label small">Lost reason*</label>
                        <input id="lost-reason" v-model="statusForm.lost_reason" class="form-control mb-2" maxlength="255" required />
                    </div>
                    <div v-if="statusForm.hasErrors" class="text-danger small mb-2"><div v-for="(e, k) in statusForm.errors" :key="k">{{ e }}</div></div>
                    <button class="btn btn-sm btn-svtp w-100" :disabled="statusForm.processing">Save Status</button>
                </form>

                <form v-if="permissions.convert && !lead.customer_user_id" class="card p-3 p-md-4" @submit.prevent="submitConvert">
                    <h5 class="mb-3">Convert to Customer</h5>
                    <p class="text-muted small">Search existing customers first — duplicates by email are refused.</p>
                    <SmartSelect v-model="convertForm.customer_user_id" label="Existing customer" :fetch-options="searchCustomers" placeholder="Type name, phone or email..." search-placeholder="Type to search customers..." :loading="customerLoading" />
                    <div class="text-center text-muted small my-2">— or quick-create —</div>
                    <label for="conv-name" class="form-label small">Name</label>
                    <input id="conv-name" v-model="convertForm.name" class="form-control form-control-sm mb-2" maxlength="255" />
                    <label for="conv-phone" class="form-label small">Phone</label>
                    <input id="conv-phone" v-model="convertForm.phone" class="form-control form-control-sm mb-2" maxlength="30" />
                    <label for="conv-email" class="form-label small">Email (optional)</label>
                    <input id="conv-email" v-model="convertForm.email" type="email" class="form-control form-control-sm mb-2" maxlength="255" />
                    <div v-if="convertForm.hasErrors" class="text-danger small mb-2"><div v-for="(e, k) in convertForm.errors" :key="k">{{ e }}</div></div>
                    <button class="btn btn-sm btn-svtp w-100" :disabled="convertForm.processing">Convert</button>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
