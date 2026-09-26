<script setup>
import { ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    documents: { type: Array, default: () => [] },
    embeddingStatus: { type: Object, default: () => ({}) },
});
const page = usePage();
const editingId = ref(null);
const form = useForm({ title: '', content: '', locale: '', visibility: 'internal', status: 'active' });
const importForm = useForm({ source_type: 'page', after_id: null });

function edit(document) {
    editingId.value = document.id;
    form.title = document.title;
    form.content = document.content;
    form.locale = document.locale ?? '';
    form.visibility = document.visibility;
    form.status = document.status;
}

function clear() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function save() {
    const options = { preserveScroll: true, onSuccess: clear };
    if (editingId.value) form.put(appUrl(`/admin/ai-assistant/knowledge/${editingId.value}`), options);
    else form.post(appUrl('/admin/ai-assistant/knowledge'), options);
}

function reindex(document) {
    router.post(appUrl(`/admin/ai-assistant/knowledge/${document.id}/reindex`), {}, { preserveScroll: true });
}

function remove(document) {
    if (!window.confirm(`Delete “${document.title}” and its indexed chunks?`)) return;
    router.delete(appUrl(`/admin/ai-assistant/knowledge/${document.id}`), { preserveScroll: true });
}

function importBatch() {
    importForm.post(appUrl('/admin/ai-assistant/knowledge/import'), { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <div class="container-fluid py-3 knowledge-page" style="max-width: 1100px">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div><h1 class="h3 mb-1">AI Knowledge</h1><p class="small text-muted mb-0">Internal articles and selected public content used by the Assistant.</p></div>
                <a :href="appUrl('/admin/ai-assistant')" class="btn btn-sm btn-outline-secondary">Back to Assistant</a>
            </div>
            <div v-if="page.props.flash?.message" class="knowledge-feedback knowledge-feedback--success" role="status">{{ page.props.flash.message }}</div>
            <div v-if="page.props.errors?.knowledge" class="knowledge-feedback knowledge-feedback--error" role="alert">{{ page.props.errors.knowledge }}</div>
            <div class="knowledge-status small">
                Knowledge index: {{ embeddingStatus.provider === 'gemini' ? 'Gemini' : 'OpenAI' }} · {{ embeddingStatus.model }}.
                <span v-if="!embeddingStatus.configured"> Provider key not configured.</span>
                <span v-else-if="!embeddingStatus.enabled"> Knowledge retrieval is disabled in AI Settings.</span>
                <span v-else-if="embeddingStatus.needs_reindex"> {{ embeddingStatus.needs_reindex }} active document{{ embeddingStatus.needs_reindex === 1 ? '' : 's' }} need reindexing for this provider and model.</span>
                <span v-else> Active documents are indexed for this provider and model.</span>
            </div>
            <div class="row g-3">
                <div class="col-lg-5">
                    <form class="card" @submit.prevent="save">
                        <div class="card-header"><strong>{{ editingId ? 'Edit article' : 'New knowledge article' }}</strong></div>
                        <div class="card-body">
                            <label class="form-label" for="knowledge-title">Title</label>
                            <input id="knowledge-title" v-model="form.title" class="form-control mb-2" maxlength="200" required>
                            <div v-if="form.errors.title" class="text-danger small">{{ form.errors.title }}</div>
                            <label class="form-label" for="knowledge-content">Content</label>
                            <textarea id="knowledge-content" v-model="form.content" class="form-control mb-1" rows="12" maxlength="12000" required></textarea>
                            <div class="small text-muted mb-2">{{ form.content.length }} / 12,000 characters</div>
                            <div v-if="form.errors.content" class="text-danger small">{{ form.errors.content }}</div>
                            <div class="row g-2">
                                <div class="col-sm-6"><label class="form-label" for="knowledge-locale">Locale (optional)</label><input id="knowledge-locale" v-model="form.locale" class="form-control" maxlength="12" placeholder="en"></div>
                                <div class="col-sm-6"><label class="form-label" for="knowledge-visibility">Visibility</label><select id="knowledge-visibility" v-model="form.visibility" class="form-select"><option value="internal">Internal</option><option value="public">Public knowledge</option></select></div>
                                <div v-if="editingId" class="col-12"><label class="form-label" for="knowledge-status">Status</label><select id="knowledge-status" v-model="form.status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                            </div>
                        </div>
                        <div class="card-footer d-flex gap-2"><button class="btn btn-primary" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save and index' }}</button><button v-if="editingId" class="btn btn-outline-secondary" type="button" @click="clear">Cancel</button></div>
                    </form>
                    <form class="card mt-3" @submit.prevent="importBatch">
                        <div class="card-header"><strong>Index existing public content</strong></div>
                        <div class="card-body">
                            <p class="small text-muted">Each request handles up to 10 active records. Continue with the next ID shown after a batch.</p>
                            <select v-model="importForm.source_type" class="form-select mb-2" aria-label="Source type"><option value="page">CMS Pages</option><option value="destination">Destinations</option><option value="place">Places</option></select>
                            <input v-model="importForm.after_id" class="form-control" type="number" min="1" placeholder="Continue after ID (optional)" aria-label="Continue after ID">
                        </div>
                        <div class="card-footer"><button class="btn btn-outline-primary" :disabled="importForm.processing">{{ importForm.processing ? 'Indexing…' : 'Index next batch' }}</button></div>
                    </form>
                </div>
                <div class="col-lg-7">
                    <div class="card"><div class="card-header"><strong>Indexed documents</strong></div>
                        <div class="list-group list-group-flush">
                            <div v-for="document in documents" :key="document.id" class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                                <div><div class="fw-semibold">{{ document.title }}</div><div class="small text-muted">{{ document.source_type }} · {{ document.visibility }} · {{ document.status }} · {{ document.status !== 'active' ? 'Inactive' : document.current_index_chunks_count ? 'Indexed' : document.last_indexed_at ? 'Needs reindex' : 'Not indexed' }}<span v-if="document.last_indexed_at"> · Last indexed {{ new Date(document.last_indexed_at).toLocaleDateString() }}</span></div></div>
                                <div class="d-flex gap-1"><button v-if="document.source_type === 'manual'" class="btn btn-sm btn-outline-secondary" type="button" @click="edit(document)">Edit</button><button class="btn btn-sm btn-outline-secondary" type="button" :disabled="form.processing" @click="reindex(document)">Reindex</button><button class="btn btn-sm btn-outline-danger" type="button" :disabled="form.processing" @click="remove(document)">Delete</button></div>
                            </div>
                            <div v-if="!documents.length" class="p-3 small text-muted">No knowledge documents yet.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.knowledge-status, .knowledge-feedback { margin-bottom: 1rem; padding: .7rem .85rem; border: 1px solid var(--admin-border); border-radius: .55rem; background: var(--admin-surface); color: var(--admin-text); }
.knowledge-feedback { font-size: .82rem; }
.knowledge-feedback--success { border-color: var(--alert-success-border); background: var(--alert-success-bg); color: var(--alert-success-text); }
.knowledge-feedback--error { border-color: var(--alert-danger-border); background: var(--alert-danger-bg); color: var(--alert-danger-text); }
.knowledge-page :deep(.list-group-item) { min-width: 0; overflow-wrap: anywhere; }
</style>
