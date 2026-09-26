<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    proposals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    canViewAll: { type: Boolean, default: false },
});

const filters = useForm({
    status: props.filters.status ?? '',
    tool: props.filters.tool ?? '',
    user_id: props.filters.user_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

function apply() {
    filters.get(appUrl('/admin/ai-assistant/actions'), { preserveState: true, preserveScroll: true });
}

function statusLabel(proposal) {
    if (proposal.failure_reason === 'stale') return 'Stale';
    return {
        pending: 'Pending',
        confirmed: 'Confirmed',
        executed: 'Executed',
        rejected: 'Rejected',
        expired: 'Expired',
        failed: 'Failed',
    }[proposal.status] ?? proposal.status;
}
</script>

<template>
    <AdminLayout>
        <div class="container-fluid py-3" style="max-width: 1200px">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div><h1 class="h3 mb-1">Agent action history</h1><p class="text-muted small mb-0">Server-owned proposals and their outcomes. Each confirmed change is audited.</p></div>
                <a :href="appUrl('/admin/ai-assistant')" class="btn btn-sm btn-outline-secondary">Back to Assistant</a>
            </div>
            <form class="card card-body mb-3" @submit.prevent="apply">
                <div class="row g-2 align-items-end">
                    <div class="col-sm-2"><label class="form-label small" for="action-status">Status</label><select id="action-status" v-model="filters.status" class="form-select form-select-sm"><option value="">All</option><option v-for="status in ['pending', 'executed', 'rejected', 'expired', 'failed']" :key="status" :value="status">{{ status }}</option></select></div>
                    <div class="col-sm-3"><label class="form-label small" for="action-tool">Action</label><input id="action-tool" v-model="filters.tool" class="form-control form-control-sm" maxlength="80" placeholder="e.g. set_hotel_featured"></div>
                    <div v-if="canViewAll" class="col-sm-2"><label class="form-label small" for="action-user">User ID</label><input id="action-user" v-model="filters.user_id" class="form-control form-control-sm" type="number" min="1"></div>
                    <div class="col-sm-2"><label class="form-label small" for="action-from">From</label><input id="action-from" v-model="filters.from" class="form-control form-control-sm" type="date"></div>
                    <div class="col-sm-2"><label class="form-label small" for="action-to">To</label><input id="action-to" v-model="filters.to" class="form-control form-control-sm" type="date"></div>
                    <div class="col-sm-1"><button class="btn btn-sm btn-outline-primary w-100" type="submit">Filter</button></div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Target</th><th>Status</th><th>Conversation</th></tr></thead>
                    <tbody>
                        <tr v-for="proposal in proposals.data" :key="proposal.id">
                            <td class="text-nowrap">{{ new Date(proposal.created_at).toLocaleString() }}</td>
                            <td>{{ proposal.user ?? 'Unknown' }}</td>
                            <td>{{ proposal.tool_name.replaceAll('_', ' ') }}</td>
                            <td>{{ proposal.target_label }} ({{ proposal.target_type }} #{{ proposal.target_id }})</td>
                            <td><span class="action-status" :class="'action-status--' + (proposal.failure_reason === 'stale' ? 'stale' : proposal.status)">{{ statusLabel(proposal) }}</span><div v-if="proposal.failure_reason && proposal.failure_reason !== 'stale'" class="small text-muted mt-1">{{ proposal.failure_reason.replaceAll('_', ' ') }}</div></td>
                            <td><a v-if="proposal.conversation_id" :href="appUrl(`/admin/ai-assistant?conversation=${proposal.conversation_id}`)">Open conversation</a></td>
                        </tr>
                        <tr v-if="!proposals.data.length"><td colspan="6" class="text-muted py-3">No Agent actions match these filters.</td></tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="proposals.links" />
        </div>
    </AdminLayout>
</template>

<style scoped>
.action-status { display: inline-block; padding: .2rem .5rem; border: 1px solid var(--admin-border-strong); border-radius: .35rem; background: var(--admin-surface); color: var(--admin-text-muted); font-size: .72rem; font-weight: 700; }
.action-status--pending { color: var(--admin-warning); }
.action-status--executed { color: var(--admin-success); }
.action-status--failed, .action-status--stale, .action-status--expired { color: var(--admin-danger); }
.table td { overflow-wrap: anywhere; }
</style>
