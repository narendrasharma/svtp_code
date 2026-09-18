<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    applications: Object,
    filters: { type: Object, default: () => ({}) },
    statuses: Array,
    kycStatuses: Array,
});

const endpoint = appUrl('/admin/vendor-applications');
const filters = useForm({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    kyc_status: props.filters.kyc_status ?? '',
});

const hasActiveFilters = computed(() => Object.values(filters.data()).some(v => v !== '' && v !== null));

function applyFilters() {
    filters.get(endpoint, { preserveState: true, preserveScroll: true });
}
function clearFilters() {
    router.get(endpoint);
}
function statusBadge(status) {
    return {
        pending: 'bg-warning text-dark',
        approved: 'bg-success',
        rejected: 'bg-danger',
        resubmission_requested: 'bg-info text-dark',
    }[status] ?? 'bg-secondary';
}
function kycBadge(status) {
    return {
        not_started: 'bg-secondary',
        pending: 'bg-warning text-dark',
        under_review: 'bg-info text-dark',
        verified: 'bg-success',
        needs_resubmission: 'bg-info text-dark',
        rejected: 'bg-danger',
    }[status] ?? 'bg-secondary';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Vendor Applications</h2>
                <p class="text-muted mb-0">Review business applications and KYC verification.</p>
            </div>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-4"><label class="form-label small">Search</label><input v-model="filters.search" class="form-control form-control-sm" placeholder="Business, applicant name, email" /></div>
                <div class="col-md-3"><label class="form-label small">Application Status</label><select v-model="filters.status" class="form-select form-select-sm"><option value="">All</option><option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option></select></div>
                <div class="col-md-3"><label class="form-label small">KYC Status</label><select v-model="filters.kyc_status" class="form-select form-select-sm"><option value="">All</option><option v-for="k in kycStatuses" :key="k.value" :value="k.value">{{ k.label }}</option></select></div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-sm btn-svtp w-100" :disabled="filters.processing">Apply</button>
                    <button v-if="hasActiveFilters" type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                </div>
            </div>
        </form>

        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Applicant</th><th>Business</th><th>Entity</th><th>KYC</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
            <tbody>
                <tr v-for="app in applications.data" :key="app.id">
                    <td><strong>{{ app.user?.name }}</strong><div class="small text-muted">{{ app.user?.email }}</div></td>
                    <td>{{ app.business_name }}<div class="small text-muted">{{ app.city }}, {{ app.country_code }}</div></td>
                    <td><span class="small">{{ app.entity_type }}</span></td>
                    <td>
                        <span class="badge" :class="kycBadge(app.kyc_status)">{{ app.kyc_status }}</span>
                        <div v-if="app.kyc_summary" class="small text-muted">{{ app.kyc_summary.verified }}/{{ app.kyc_summary.required }} verified</div>
                    </td>
                    <td><span class="badge" :class="statusBadge(app.status)">{{ app.status }}</span></td>
                    <td class="small text-muted">{{ new Date(app.created_at).toLocaleDateString() }}</td>
                    <td><Link :href="`${endpoint}/${app.id}`" class="btn btn-sm btn-outline-primary">Review</Link></td>
                </tr>
                <tr v-if="!applications.data.length"><td colspan="7" class="text-center text-muted py-4">No applications found.</td></tr>
            </tbody>
        </table></div>
        <Pagination :links="applications.links" />
    </AdminLayout>
</template>
