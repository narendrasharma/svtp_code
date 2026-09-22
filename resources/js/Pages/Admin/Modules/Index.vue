<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({
    modules: { type: Array, default: () => [] },
});

function toggle(module) {
    if (!module.available) return;
    router.patch(appUrl(`/admin/modules/${module.key}`), { enabled: !module.enabled }, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <h2 class="mb-1">Platform Modules</h2>
        <p class="text-muted mb-4">Switch platform modules on or off. Disabled modules disappear from navigation and search, and their routes return 404. Shared systems (users, vendors, finance, settings) are never affected.</p>

        <div class="row g-3">
            <div v-for="module in modules" :key="module.key" class="col-md-4">
                <div class="card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <h5 class="mb-1 text-capitalize">{{ module.name }}</h5>
                        <span v-if="!module.available" class="badge bg-secondary">Not installed</span>
                        <span v-else-if="module.enabled" class="badge bg-success">Enabled</span>
                        <span v-else class="badge bg-warning text-dark">Disabled</span>
                    </div>
                    <p class="text-muted small mb-3">{{ module.description }}</p>
                    <div v-if="!module.available" class="alert alert-secondary small mb-0">
                        Planned future module — not installed yet. It is registered here so navigation, search and permissions already know about it.
                    </div>
                    <button
                        v-else
                        type="button"
                        class="btn btn-sm"
                        :class="module.enabled ? 'btn-outline-warning' : 'btn-svtp'"
                        @click="toggle(module)"
                    >
                        {{ module.enabled ? 'Disable' : 'Enable' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
