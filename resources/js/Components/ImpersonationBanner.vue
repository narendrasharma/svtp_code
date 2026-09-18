<script setup>
import { usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { appUrl } from '../appUrl';

const page = usePage();
const impersonation = computed(() => page.props.impersonation);

function stopImpersonation() {
    router.post(appUrl('/impersonation/stop'));
}
</script>

<template>
    <div v-if="impersonation" class="impersonation-banner">
        <div class="container d-flex flex-wrap align-items-center justify-content-between gap-2 py-2">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning text-dark"><i class="bi bi-eye me-1"></i>Impersonating</span>
                <span class="fw-semibold">You are viewing as <strong>{{ impersonation.impersonated_name }}</strong> ({{ impersonation.impersonated_role }})</span>
                <span class="text-muted small d-none d-md-inline">— support session started {{ new Date(impersonation.started_at).toLocaleTimeString() }}</span>
            </div>
            <button type="button" class="btn btn-sm btn-dark" @click="stopImpersonation">
                <i class="bi bi-box-arrow-left me-1"></i>Return to Admin
            </button>
        </div>
    </div>
</template>

<style scoped>
.impersonation-banner {
    position: sticky;
    top: 0;
    z-index: 1055;
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
    color: #1a1a1a;
    border-bottom: 2px solid #92400e;
    font-size: 0.92rem;
}
.impersonation-banner strong {
    color: #7c2d12;
}
</style>
