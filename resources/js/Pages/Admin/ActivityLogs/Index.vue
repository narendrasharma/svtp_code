<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    logs: Object,
    filters: { type: Object, default: () => ({}) },
    modules: { type: Array, default: () => [] },
});

const filters = useForm({
    search: props.filters.search ?? '',
    event: props.filters.event ?? '',
    module: props.filters.module ?? '',
    actor_id: props.filters.actor_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const openId = ref(null);

function apply() {
    filters.get(appUrl('/admin/activity-logs'), { preserveState: true, preserveScroll: true });
}

function toggle(id) {
    openId.value = openId.value === id ? null : id;
}
</script>

<template>
    <AdminLayout>
        <h2 class="mt-2 mb-1">Activity Logs</h2>
        <p class="text-muted mb-3">Append-only audit trail — who changed what, when. Sensitive values are masked at write time. Entries cannot be edited or deleted.</p>

        <form class="card p-3 mb-3" @submit.prevent="apply">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="audit-search" class="form-label small">Search</label>
                    <input id="audit-search" v-model="filters.search" class="form-control form-control-sm" placeholder="Description, event, IP" />
                </div>
                <div class="col-md-2">
                    <label for="audit-event" class="form-label small">Event</label>
                    <input id="audit-event" v-model="filters.event" class="form-control form-control-sm" placeholder="e.g. booking.created" />
                </div>
                <div class="col-md-2">
                    <label for="audit-module" class="form-label small">Module</label>
                    <select id="audit-module" v-model="filters.module" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="audit-from" class="form-label small">From</label>
                    <input id="audit-from" v-model="filters.from" type="date" class="form-control form-control-sm" />
                </div>
                <div class="col-md-2">
                    <label for="audit-to" class="form-label small">To</label>
                    <input id="audit-to" v-model="filters.to" type="date" class="form-control form-control-sm" />
                </div>
                <div class="col-md-1">
                    <button class="btn btn-sm btn-svtp w-100">Go</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle small">
                <thead><tr><th>When</th><th>Actor</th><th>Event</th><th>Module</th><th>Summary</th><th></th></tr></thead>
                <tbody>
                    <template v-for="log in logs.data" :key="log.id">
                        <tr>
                            <td class="text-nowrap">{{ log.created_at ? new Date(log.created_at).toLocaleString('en-IN') : '' }}</td>
                            <td>{{ log.actor?.name ?? 'system' }}<div v-if="log.impersonator" class="text-muted">via {{ log.impersonator.name }}</div></td>
                            <td><span class="badge bg-light text-dark">{{ log.event }}</span></td>
                            <td class="text-muted">{{ log.module }}</td>
                            <td>{{ log.description ?? '—' }}</td>
                            <td>
                                <button
                                    v-if="log.old_values || log.new_values"
                                    class="btn btn-sm btn-link p-0"
                                    @click="toggle(log.id)"
                                >{{ openId === log.id ? 'hide' : 'diff' }}</button>
                            </td>
                        </tr>
                        <tr v-if="openId === log.id">
                            <td colspan="6" class="bg-light">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong class="small">Before</strong>
                                        <pre class="small mb-0">{{ JSON.stringify(log.old_values ?? {}, null, 1) }}</pre>
                                    </div>
                                    <div class="col-md-6">
                                        <strong class="small">After</strong>
                                        <pre class="small mb-0">{{ JSON.stringify(log.new_values ?? {}, null, 1) }}</pre>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p v-if="!logs.data.length" class="text-muted">No activity recorded yet.</p>
        <Pagination :links="logs.links" />
    </AdminLayout>
</template>
