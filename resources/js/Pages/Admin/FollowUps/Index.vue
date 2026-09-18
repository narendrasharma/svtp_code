<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    followUps: Object,
    filter: { type: String, default: 'due_today' },
    counts: { type: Object, default: () => ({}) },
});

const tabs = [
    { value: 'due_today', label: 'Due today', count: props.counts.due_today },
    { value: 'overdue', label: 'Overdue', count: props.counts.overdue },
    { value: 'upcoming', label: 'Upcoming', count: props.counts.upcoming },
    { value: 'mine', label: 'Mine', count: props.counts.mine },
    { value: 'completed', label: 'Completed', count: null },
];

function setFilter(value) {
    router.get(appUrl('/admin/follow-ups'), { filter: value }, { preserveScroll: true });
}
function complete(id) {
    router.patch(appUrl(`/admin/follow-ups/${id}/complete`), {}, { preserveScroll: true });
}
function cancel(id) {
    router.patch(appUrl(`/admin/follow-ups/${id}/cancel`), {}, { preserveScroll: true });
}
function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('en-IN') : '—';
}
</script>

<template>
    <AdminLayout>
        <h2 class="mt-2 mb-1">Follow-ups</h2>
        <p class="text-muted mb-3">The callback queue — no calendar, just what needs attention.</p>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                class="btn btn-sm"
                :class="filter === tab.value ? 'btn-svtp' : 'btn-outline-secondary'"
                @click="setFilter(tab.value)"
            >{{ tab.label }}<span v-if="tab.count !== null && tab.count !== undefined"> ({{ tab.count }})</span></button>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Due</th><th>Lead</th><th>Type</th><th>Assignee</th><th>Note</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="f in followUps.data" :key="f.id">
                        <td class="text-nowrap">{{ formatDateTime(f.due_at) }}</td>
                        <td><Link :href="appUrl(`/admin/leads/${f.lead.id}`)">{{ f.lead.reference }}</Link><div class="small text-muted">{{ f.lead.name }}</div></td>
                        <td>{{ f.type }}</td>
                        <td class="small">{{ f.assignee?.name ?? '—' }}</td>
                        <td class="small">{{ f.note || '—' }}</td>
                        <td class="text-end text-nowrap">
                            <button v-if="f.status === 'pending'" type="button" class="btn btn-sm btn-success me-1" @click="complete(f.id)">Done</button>
                            <button v-if="f.status === 'pending'" type="button" class="btn btn-sm btn-outline-secondary" @click="cancel(f.id)">Cancel</button>
                            <span v-else class="badge bg-secondary">{{ f.status }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!followUps.data.length" class="text-muted">Nothing here.</p>
        <Pagination :links="followUps.links" />
    </AdminLayout>
</template>
