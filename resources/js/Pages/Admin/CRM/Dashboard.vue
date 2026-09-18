<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({
    stats: { type: Object, default: () => ({}) },
    hotLeads: { type: Array, default: () => [] },
    overdueFollowUps: { type: Array, default: () => [] },
    recentQuotations: { type: Array, default: () => [] },
});

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <AdminLayout>
        <h2 class="mt-2 mb-1">CRM Overview</h2>
        <p class="text-muted mb-4">Pipeline pulse — counts and queues, not full analytics.</p>

        <div class="row g-3">
            <div class="col-md-3"><Link :href="appUrl('/admin/leads?status=new')" class="card p-3 text-decoration-none d-block"><small class="text-muted">New Leads</small><h3 class="mb-0">{{ stats.new_leads }}</h3></Link></div>
            <div class="col-md-3"><Link :href="appUrl('/admin/leads?filter=hot')" class="card p-3 text-decoration-none d-block"><small class="text-muted">Hot Leads</small><h3 class="mb-0">{{ stats.hot_leads }}</h3></Link></div>
            <div class="col-md-3"><Link :href="appUrl('/admin/leads?filter=unassigned')" class="card p-3 text-decoration-none d-block"><small class="text-muted">Unassigned</small><h3 class="mb-0">{{ stats.unassigned }}</h3></Link></div>
            <div class="col-md-3"><Link :href="appUrl('/admin/follow-ups?filter=due_today')" class="card p-3 text-decoration-none d-block"><small class="text-muted">Due Today</small><h3 class="mb-0">{{ stats.due_today }}</h3></Link></div>
            <div class="col-md-3"><Link :href="appUrl('/admin/follow-ups?filter=overdue')" class="card p-3 text-decoration-none d-block"><small class="text-muted">Overdue</small><h3 class="mb-0">{{ stats.overdue }}</h3></Link></div>
            <div class="col-md-3"><Link :href="appUrl('/admin/quotations?status=sent')" class="card p-3 text-decoration-none d-block"><small class="text-muted">Quotations Sent</small><h3 class="mb-0">{{ stats.quotations_sent }}</h3></Link></div>
            <div class="col-md-3"><Link :href="appUrl('/admin/quotations?status=accepted')" class="card p-3 text-decoration-none d-block"><small class="text-muted">Accepted</small><h3 class="mb-0">{{ stats.quotations_accepted }}</h3></Link></div>
            <div class="col-md-3"><div class="card p-3"><small class="text-muted">Won / Lost</small><h3 class="mb-0">{{ stats.won }} / {{ stats.lost }}</h3></div></div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-4">
                <section class="card p-3 h-100">
                    <h5 class="mb-3">Hot Leads</h5>
                    <ul v-if="hotLeads.length" class="list-unstyled mb-0 small">
                        <li v-for="lead in hotLeads" :key="lead.id" class="border-bottom py-2">
                            <Link :href="appUrl(`/admin/leads/${lead.id}`)">{{ lead.reference }}</Link> · {{ lead.name }}
                            <div class="text-muted">{{ lead.assignee?.name ?? 'Unassigned' }}</div>
                        </li>
                    </ul>
                    <p v-else class="text-muted small mb-0">No hot leads.</p>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="card p-3 h-100">
                    <h5 class="mb-3">Overdue Follow-ups</h5>
                    <ul v-if="overdueFollowUps.length" class="list-unstyled mb-0 small">
                        <li v-for="f in overdueFollowUps" :key="f.id" class="border-bottom py-2">
                            <Link :href="appUrl(`/admin/leads/${f.lead.id}`)">{{ f.lead.reference }}</Link> · {{ formatDateTime(f.due_at) }}
                            <div class="text-muted">{{ f.assignee?.name ?? '—' }}</div>
                        </li>
                    </ul>
                    <p v-else class="text-muted small mb-0">Nothing overdue.</p>
                </section>
            </div>
            <div class="col-lg-4">
                <section class="card p-3 h-100">
                    <h5 class="mb-3">Recent Quotations</h5>
                    <ul v-if="recentQuotations.length" class="list-unstyled mb-0 small">
                        <li v-for="q in recentQuotations" :key="q.id" class="border-bottom py-2">
                            <Link :href="appUrl(`/admin/quotations/${q.id}`)">{{ q.reference }}{{ q.revision_number > 1 ? ` (Rev ${q.revision_number})` : '' }}</Link> · {{ q.status }}
                            <div class="text-muted">{{ q.lead?.name ?? q.customer?.name ?? '' }} · ₹{{ q.total_amount }}</div>
                        </li>
                    </ul>
                    <p v-else class="text-muted small mb-0">No quotations yet.</p>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>
