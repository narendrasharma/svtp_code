<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    quotations: Object,
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/quotations');
const filters = useForm({ search: props.filters.search ?? '', status: props.filters.status ?? '' });

function apply() {
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}
function statusBadge(status) {
    return { draft: 'bg-secondary', sent: 'bg-primary', viewed: 'bg-info text-dark', accepted: 'bg-success', converted: 'bg-success', rejected: 'bg-danger', expired: 'bg-warning text-dark', superseded: 'bg-dark' }[status] ?? 'bg-secondary';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Quotations</h2>
                <p class="text-muted mb-0">Commercial offers — never invoices. One row per reference (newest revision).</p>
            </div>
            <Link :href="appUrl('/admin/quotations/create')" class="btn btn-svtp"><i class="bi bi-plus-lg me-1"></i>New Quotation</Link>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="apply">
            <div class="row g-2 align-items-end">
                <div class="col-md-4"><label for="q-search" class="form-label small">Search</label><input id="q-search" v-model="filters.search" class="form-control form-control-sm" placeholder="Reference or customer" /></div>
                <div class="col-md-3"><label for="q-status" class="form-label small">Status</label><select id="q-status" v-model="filters.status" class="form-select form-select-sm"><option value="">All</option><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select></div>
                <div class="col-md-2"><button class="btn btn-sm btn-svtp w-100">Go</button></div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Reference</th><th>Customer</th><th class="text-end">Total</th><th>Status</th><th>Valid until</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="q in quotations.data" :key="q.id">
                        <td class="text-nowrap">{{ q.reference }}<span v-if="q.revision_number > 1" class="text-muted"> (Rev {{ q.revision_number }})</span></td>
                        <td class="small">{{ q.lead?.name ?? q.customer?.name ?? '—' }}</td>
                        <td class="text-end text-nowrap">₹{{ q.total_amount }}</td>
                        <td><span class="badge" :class="statusBadge(q.status)">{{ q.status }}</span></td>
                        <td class="small text-muted">{{ q.valid_until ?? '—' }}</td>
                        <td class="text-end"><Link :href="appUrl(`/admin/quotations/${q.id}`)" class="btn btn-sm btn-outline-primary">Open</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!quotations.data.length" class="text-muted">No quotations found.</p>
        <Pagination :links="quotations.links" />
    </AdminLayout>
</template>
