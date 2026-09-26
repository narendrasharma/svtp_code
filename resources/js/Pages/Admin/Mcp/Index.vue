<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    enabled: { type: Boolean, default: false },
    tokens: { type: Array, default: () => [] },
});

const page = usePage();
const newToken = ref(null);
const tokenLabel = ref('');
const createError = ref('');
const creating = ref(false);
const toggleForm = useForm({ enabled: props.enabled });

function saveEnabled() {
    toggleForm.post(appUrl('/admin/mcp-access/toggle'), { preserveScroll: true });
}

async function createToken() {
    if (creating.value) return;
    creating.value = true;
    createError.value = '';
    try {
        const { data } = await axios.post(appUrl('/admin/mcp-access/tokens'), { label: tokenLabel.value });
        newToken.value = data.token;
        tokenLabel.value = '';
        router.reload({ only: ['tokens'], preserveState: true });
    } catch (error) {
        createError.value = error.response?.data?.errors?.label?.[0] ?? 'Could not create the token. Please try again.';
    } finally {
        creating.value = false;
    }
}

function revokeToken(token) {
    if (!window.confirm(`Revoke MCP token “${token.label}”?`)) return;
    router.delete(appUrl(`/admin/mcp-access/tokens/${token.id}`), { preserveScroll: true });
}

function dateTime(value) {
    return value ? new Date(value).toLocaleString() : 'Never';
}
</script>

<template>
    <AdminLayout>
        <div class="container-fluid py-3" style="max-width: 1000px">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h1 class="h3 mb-1">MCP Access</h1>
                    <p class="small text-muted mb-0">External hosts can use your permitted READ tools. Each token runs as its owner.</p>
                </div>
                <a :href="appUrl('/admin/settings')" class="btn btn-sm btn-outline-secondary">Back to Settings</a>
            </div>

            <div v-if="page.props.flash?.message" class="alert alert-success py-2 small" role="status">{{ page.props.flash.message }}</div>
            <div v-if="newToken" class="alert alert-warning" role="status">
                <strong>Copy this token now. It will not be shown again.</strong>
                <div class="d-flex gap-2 mt-2">
                    <input class="form-control font-monospace" :value="newToken" readonly aria-label="New MCP bearer token">
                    <button class="btn btn-outline-secondary" type="button" @click="newToken = null">Hide</button>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>Remote MCP server</strong></div>
                <div class="card-body">
                    <form class="d-flex flex-wrap align-items-center gap-3" @submit.prevent="saveEnabled">
                        <div class="form-check form-switch mb-0">
                            <input id="mcp-enabled" v-model="toggleForm.enabled" class="form-check-input" type="checkbox">
                            <label class="form-check-label" for="mcp-enabled">Enable MCP</label>
                        </div>
                        <button class="btn btn-primary btn-sm" :disabled="toggleForm.processing">{{ toggleForm.processing ? 'Saving…' : 'Save' }}</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0">Connect to <code>/mcp</code> over HTTPS using an Authorization Bearer header. Only allowlisted READ tools are available. Guarded actions are excluded.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Personal access tokens</strong></div>
                <div class="card-body">
                    <form class="row g-2 align-items-end" @submit.prevent="createToken">
                        <div class="col-sm-8">
                            <label class="form-label" for="mcp-label">Token label</label>
                            <input id="mcp-label" v-model="tokenLabel" class="form-control" maxlength="100" placeholder="My MCP host" required>
                            <div v-if="createError" class="text-danger small">{{ createError }}</div>
                        </div>
                        <div class="col-sm-4"><button class="btn btn-primary w-100" :disabled="creating">{{ creating ? 'Creating…' : 'Create token' }}</button></div>
                    </form>
                    <p class="small text-muted mb-0 mt-2">Tokens expire after 90 days. Their secret is stored as a hash.</p>
                </div>
                <div v-if="tokens.length" class="list-group list-group-flush">
                    <div v-for="token in tokens" :key="token.id" class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <strong>{{ token.label }}</strong>
                            <span v-if="token.revoked_at" class="badge text-bg-secondary ms-2">Revoked</span>
                            <span v-else-if="new Date(token.expires_at) <= new Date()" class="badge text-bg-secondary ms-2">Expired</span>
                            <div class="small text-muted">Created {{ dateTime(token.created_at) }} · Last used {{ dateTime(token.last_used_at) }} · Expires {{ dateTime(token.expires_at) }}</div>
                        </div>
                        <button v-if="!token.revoked_at" class="btn btn-sm btn-outline-danger" type="button" :aria-label="`Revoke ${token.label}`" @click="revokeToken(token)">Revoke</button>
                    </div>
                </div>
                <div v-else class="card-body small text-muted">No tokens yet.</div>
            </div>
        </div>
    </AdminLayout>
</template>
