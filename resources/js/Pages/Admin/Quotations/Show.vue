<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import ShareMenu from '../../../Components/ShareMenu.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    quotation: Object,
    history: { type: Array, default: () => [] },
    tourContext: { type: Object, default: () => ({}) },
    publicUrl: { type: String, default: '' },
    permissions: { type: Object, default: () => ({ update: false, send: false, accept: false, convert: false }) },
});

const endpoint = appUrl(`/admin/quotations/${props.quotation.id}`);
const reviseForm = useForm({});

function act(action, message) {
    if (!window.confirm(message)) return;
    router.post(`${endpoint}/${action}`, {}, { preserveScroll: true });
}
function statusBadge(status) {
    return { draft: 'bg-secondary', sent: 'bg-primary', viewed: 'bg-info text-dark', accepted: 'bg-success', converted: 'bg-success', rejected: 'bg-danger', expired: 'bg-warning text-dark', superseded: 'bg-dark' }[status] ?? 'bg-secondary';
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="appUrl('/admin/quotations')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Quotations</Link>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2">
                <h2 class="mb-0">{{ quotation.reference }}{{ quotation.revision_number > 1 ? ` (Rev ${quotation.revision_number})` : '' }}</h2>
                <span class="badge" :class="statusBadge(quotation.status)">{{ quotation.status }}</span>
            </div>
            <p class="text-muted mb-0 mt-1">Total ₹{{ quotation.total_amount }} · valid until {{ quotation.valid_until ?? '—' }}</p>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Line Items</h5>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 small">
                            <thead><tr><th>Description</th><th>Type</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                                <tr v-for="item in quotation.items" :key="item.id">
                                    <td>{{ item.description }}</td>
                                    <td class="text-muted">{{ item.item_type }}</td>
                                    <td class="text-end">{{ item.quantity }}</td>
                                    <td class="text-end">{{ item.unit_price }}</td>
                                    <td class="text-end">{{ item.total_amount }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <dl class="row mb-0 small mt-3">
                        <dt class="col-sm-6 text-muted">Subtotal</dt><dd class="col-sm-6 text-end">₹{{ quotation.subtotal }}</dd>
                        <dt class="col-sm-6 text-muted">Discount</dt><dd class="col-sm-6 text-end">−₹{{ quotation.discount_amount }}</dd>
                        <dt class="col-sm-6 text-muted">Tax</dt><dd class="col-sm-6 text-end">₹{{ quotation.tax_amount }}</dd>
                        <dt class="col-sm-6">Total</dt><dd class="col-sm-6 text-end fw-bold">₹{{ quotation.total_amount }}</dd>
                    </dl>
                </section>

                <section v-if="quotation.terms || quotation.customer_note" class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Terms &amp; Customer Note</h5>
                    <p v-if="quotation.customer_note" class="small">{{ quotation.customer_note }}</p>
                    <p v-if="quotation.terms" class="small text-muted mb-0">{{ quotation.terms }}</p>
                </section>

                <section class="card p-3 p-md-4">
                    <h5 class="mb-3">Revision History</h5>
                    <ul class="list-unstyled mb-0 small">
                        <li v-for="rev in history" :key="rev.id" class="border-bottom py-2 d-flex justify-content-between align-items-center">
                            <span>Rev {{ rev.revision_number }} · ₹{{ rev.total_amount }} · <span class="badge" :class="statusBadge(rev.status)">{{ rev.status }}</span></span>
                            <Link v-if="rev.id !== quotation.id" :href="appUrl(`/admin/quotations/${rev.id}`)" class="btn btn-sm btn-outline-secondary">View frozen copy</Link>
                            <span v-else class="text-muted">current</span>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Parties</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Lead</dt><dd class="col-7">{{ quotation.lead ? `${quotation.lead.reference} · ${quotation.lead.name}` : '—' }}</dd>
                        <dt class="col-5 text-muted">Customer</dt><dd class="col-7">{{ quotation.customer?.name ?? '—' }}</dd>
                        <dt class="col-5 text-muted">Service</dt><dd class="col-7">{{ quotation.service_type }}</dd>
                        <dt class="col-5 text-muted">Created by</dt><dd class="col-7">{{ quotation.creator?.name ?? '—' }}</dd>
                        <dt v-if="quotation.converted_booking" class="col-5 text-muted">Booking</dt><dd v-if="quotation.converted_booking" class="col-7"><Link :href="appUrl(`/admin/bookings/${quotation.converted_booking.id}`)">{{ quotation.converted_booking.booking_reference_id }}</Link></dd>
                    </dl>
                    <div v-if="quotation.internal_note" class="alert alert-secondary small mt-3 mb-0"><strong>Internal:</strong> {{ quotation.internal_note }}</div>
                </section>

                <section class="card p-3 p-md-4 mb-3">
                    <h5 class="mb-3">Share</h5>
                    <p class="text-muted small">Read-only link — no login needed. Acceptance stays a staff action.</p>
                    <div class="input-group input-group-sm mb-3">
                        <input :value="publicUrl" class="form-control" readonly @focus="$event.target.select()" />
                        <a :href="publicUrl" target="_blank" rel="noopener" class="btn btn-outline-secondary">Open</a>
                    </div>
                    <ShareMenu
                        :endpoint="`/admin/quotations/${quotation.id}/share`"
                        :default-email="quotation.customer?.email ?? ''"
                        :default-phone="quotation.customer?.phone ?? ''"
                        :secure-url="publicUrl"
                        label="Send quotation"
                    />
                </section>

                <section class="card p-3 p-md-4">
                    <h5 class="mb-3">Actions</h5>
                    <div class="d-grid gap-2">
                        <Link v-if="permissions.update" :href="appUrl(`/admin/quotations/${quotation.id}/edit`)" class="btn btn-sm btn-outline-primary">Edit Draft</Link>
                        <button v-if="permissions.send" type="button" class="btn btn-sm btn-outline-primary" @click="act('send', 'Mark this quotation as sent?')">Mark Sent</button>
                        <button v-if="permissions.accept" type="button" class="btn btn-sm btn-success" @click="act('accept', 'Mark this quotation as accepted?')">Mark Accepted</button>
                        <button v-if="permissions.accept" type="button" class="btn btn-sm btn-outline-danger" @click="act('reject', 'Mark this quotation as rejected?')">Mark Rejected</button>
                        <button v-if="permissions.update" type="button" class="btn btn-sm btn-outline-warning" @click="act('expire', 'Mark this quotation as expired?')">Mark Expired</button>
                        <Link v-if="permissions.convert" :href="appUrl(`/admin/quotations/${quotation.id}/convert`)" class="btn btn-sm btn-svtp">Convert to Booking</Link>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Structural changes go through the edit screen (“Save as new revision”) — sent history is never overwritten.</p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
