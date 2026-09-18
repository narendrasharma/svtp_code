<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

defineProps({
    campaigns: Object,
});

function statusBadge(status) {
    return { draft: 'bg-secondary', scheduled: 'bg-info text-dark', sending: 'bg-primary', completed: 'bg-success', cancelled: 'bg-dark', failed: 'bg-danger' }[status] ?? 'bg-secondary';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Campaigns</h2>
                <p class="text-muted mb-0">Promotional bulk mail — separate from transactional notifications. Draft + Send Now only.</p>
            </div>
            <Link :href="appUrl('/admin/campaigns/create')" class="btn btn-svtp"><i class="bi bi-plus-lg me-1"></i>New Campaign</Link>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Reference</th><th>Name</th><th>Channel</th><th>Status</th><th class="text-end">Sent</th><th class="text-end">Failed</th><th class="text-end">Skipped</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="campaign in campaigns.data" :key="campaign.id">
                        <td class="text-nowrap">{{ campaign.reference }}</td>
                        <td>{{ campaign.name }}</td>
                        <td><span class="badge bg-light text-dark">{{ campaign.channel }}</span></td>
                        <td><span class="badge" :class="statusBadge(campaign.status)">{{ campaign.status }}</span></td>
                        <td class="text-end">{{ campaign.sent_count }}</td>
                        <td class="text-end">{{ campaign.failed_count }}</td>
                        <td class="text-end">{{ campaign.skipped_count }}</td>
                        <td class="text-end"><Link :href="appUrl(`/admin/campaigns/${campaign.id}`)" class="btn btn-sm btn-outline-primary">Open</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!campaigns.data.length" class="text-muted">No campaigns yet.</p>
        <Pagination :links="campaigns.links" />
    </AdminLayout>
</template>
