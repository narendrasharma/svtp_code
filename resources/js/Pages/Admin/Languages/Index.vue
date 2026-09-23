<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    languages: { type: Array, default: () => [] },
});

function setDefault(language) {
    router.patch(appUrl(`/admin/languages/${language.id}/default`), {}, { preserveScroll: true });
}

function toggle(language) {
    router.patch(appUrl(`/admin/languages/${language.id}/toggle`), {}, { preserveScroll: true });
}

function removeLanguage(language) {
    if (window.confirm(`Remove ${language.name}? Stored translations are kept by locale.`)) {
        router.delete(appUrl(`/admin/languages/${language.id}`), { preserveScroll: true });
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center mb-1">
            <div>
                <div class="text-muted small mb-1">Admin / System</div>
                <h2 class="mb-1">Languages</h2>
                <p class="text-muted mb-0">Shared platform localization. Works with Hotels, Tours and Taxi disabled.</p>
            </div>
            <Link :href="appUrl('/admin/languages/create')" class="btn btn-warning"><i class="bi bi-plus-lg me-1"></i>New Language</Link>
        </div>

        <div class="card mt-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Language</th>
                            <th>Code / Locale</th>
                            <th>Status</th>
                            <th>Direction</th>
                            <th>Order</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="language in languages" :key="language.id">
                            <td>
                                <strong>{{ language.native_name }}</strong>
                                <div class="small text-muted">{{ language.name }}</div>
                            </td>
                            <td><code>{{ language.code }} / {{ language.locale }}</code></td>
                            <td>
                                <span v-if="language.is_default" class="badge text-bg-warning me-1">Default</span>
                                <span class="badge" :class="language.is_active ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ language.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="language.is_rtl ? 'text-bg-info' : 'text-bg-light text-dark border'">
                                    {{ language.is_rtl ? 'RTL' : 'LTR' }}
                                </span>
                            </td>
                            <td>{{ language.sort_order }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <Link :href="appUrl(`/admin/languages/${language.id}/edit`)" class="btn btn-outline-secondary">Edit</Link>
                                    <button v-if="!language.is_default" type="button" class="btn btn-outline-primary" @click="setDefault(language)">Set default</button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary"
                                        :disabled="language.is_default && language.is_active"
                                        :title="language.is_default ? 'The default language cannot be deactivated' : 'Toggle active'"
                                        @click="toggle(language)"
                                    >
                                        {{ language.is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-danger"
                                        :disabled="language.is_default"
                                        :title="language.is_default ? 'The default language cannot be deleted' : 'Delete language'"
                                        @click="removeLanguage(language)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!languages.length">
                            <td colspan="6" class="text-center text-muted py-4">No languages configured.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-info mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Inactive languages keep their stored translations but never appear in the customer selector.
            Locale-prefixed public URLs are deferred to the fresh marketplace frontend — existing URLs are unchanged.
        </div>
    </AdminLayout>
</template>
