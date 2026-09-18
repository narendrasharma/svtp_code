<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({
    jobs: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    page: { type: Number, default: 1 },
    perPage: { type: Number, default: 15 },
});
</script>

<template>
    <AdminLayout>
        <h2 class="mt-2 mb-1">Failed Jobs</h2>
        <p class="text-muted mb-3">Safe operational metadata only — job payloads are never displayed. Retry re-queues the job; delete forgets the record. Both actions are audit-logged.</p>

        <p class="small text-muted">{{ total }} failed job{{ total === 1 ? '' : 's' }} on record.</p>

        <div class="table-responsive">
            <table class="table align-middle small">
                <thead><tr><th>Failed at</th><th>Job</th><th>Connection / Queue</th><th>Error</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="job in jobs" :key="job.uuid">
                        <td class="text-nowrap">{{ job.failed_at ? new Date(job.failed_at).toLocaleString('en-IN') : '' }}</td>
                        <td><span class="badge bg-light text-dark">{{ job.job_name }}</span><div class="text-muted font-monospace">{{ job.uuid.slice(0, 8) }}…</div></td>
                        <td class="text-muted">{{ job.connection }} / {{ job.queue }}</td>
                        <td class="text-danger">{{ job.exception_summary }}</td>
                        <td class="text-nowrap">
                            <Link :href="appUrl(`/admin/system/failed-jobs/${job.uuid}/retry`)" method="post" as="button" class="btn btn-sm btn-outline-primary me-1">Retry</Link>
                            <Link :href="appUrl(`/admin/system/failed-jobs/${job.uuid}`)" method="delete" as="button" class="btn btn-sm btn-outline-danger" onclick="return confirm('Forget this failed job record?')">Forget</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!jobs.length" class="text-muted">No failed jobs. Healthy queues stay empty here.</p>
    </AdminLayout>
</template>
