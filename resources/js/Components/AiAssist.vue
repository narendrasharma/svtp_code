<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    contentType: { type: String, required: true },
    field: { type: String, required: true },
    mode: { type: String, default: 'content' },
    outputFormat: { type: String, default: 'html' },
    source: { type: [String, Array], default: '' },
    context: { type: Object, default: () => ({}) },
    entityId: { type: [Number, String], default: null },
    endpoint: { type: String, required: true },
});

const emit = defineEmits(['apply']);
const page = usePage();
const available = computed(() => page.props.ai?.available === true);
const action = ref(typeof props.source === 'string' && props.source.trim() ? 'improve' : 'generate');
const notes = ref('');
const loading = ref(false);
const error = ref('');
const draft = ref(null);

const actions = [
    { key: 'improve', label: 'Improve writing' },
    { key: 'rewrite', label: 'Rewrite' },
    { key: 'shorten', label: 'Shorten' },
    { key: 'expand', label: 'Expand' },
    { key: 'fix_grammar', label: 'Fix grammar' },
    { key: 'generate', label: 'Generate draft' },
];

async function askAi() {
    if (loading.value || !available.value) return;

    loading.value = true;
    error.value = '';
    draft.value = null;

    const requestContext = { ...props.context };
    if (props.contentType === 'tour' && notes.value.trim()) requestContext.notes = notes.value.trim();
    if (props.mode === 'itinerary') requestContext.existing_itinerary = Array.isArray(props.source) ? props.source : [];

    try {
        const { data } = await axios.post(props.endpoint, {
            content_type: props.contentType,
            entity_id: props.entityId,
            field: props.field,
            action: props.mode === 'content' ? action.value : props.mode,
            output_format: props.outputFormat,
            source: props.mode === 'content' ? props.source : props.mode === 'seo' ? props.source : '',
            context: requestContext,
        });
        draft.value = data.draft;
    } catch (failure) {
        error.value = failure.response?.data?.message
            ?? Object.values(failure.response?.data?.errors ?? {})[0]?.[0]
            ?? 'Could not generate a draft. Please try again.';
    } finally {
        loading.value = false;
    }
}

function applyDraft(insert = false) {
    if (draft.value === null) return;
    const result = insert && props.mode === 'content'
        ? `${props.source || ''}\n${draft.value}`
        : draft.value;
    emit('apply', result);
    draft.value = null;
}
</script>

<template>
    <div class="mt-2 mb-3">
        <div v-if="available" class="d-flex flex-wrap align-items-center gap-2">
            <label v-if="mode === 'content'" class="visually-hidden" :for="`ai-action-${field}`">AI writing action</label>
            <select v-if="mode === 'content'" :id="`ai-action-${field}`" v-model="action" class="form-select form-select-sm w-auto" :disabled="loading">
                <option v-for="item in actions" :key="item.key" :value="item.key">{{ item.label }}</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-primary" :disabled="loading" @click="askAi">
                <i class="bi bi-stars me-1"></i>{{ loading ? 'Generating…' : mode === 'seo' ? 'Draft SEO' : mode === 'itinerary' ? 'Draft itinerary' : 'Ask AI' }}
            </button>
        </div>
        <p v-else class="small text-muted mb-0">AI is disabled or not configured. You can continue editing normally.</p>

        <div v-if="available && contentType === 'tour' && (mode === 'itinerary' || action === 'generate')" class="mt-2">
            <label class="form-label small mb-1">Optional notes for this draft</label>
            <textarea v-model="notes" class="form-control form-control-sm" rows="2" maxlength="1000" placeholder="Stops or points you want covered"></textarea>
        </div>

        <div v-if="error" class="small text-danger mt-2" role="alert">{{ error }}</div>

        <div v-if="draft !== null" class="card mt-2 border-primary-subtle">
            <div class="card-header py-2 small fw-semibold">AI draft preview — apply it only if it fits</div>
            <div class="card-body">
                <template v-if="mode === 'seo'">
                    <label class="form-label small">Title ({{ draft.title?.length ?? 0 }}/70)</label>
                    <input :value="draft.title" class="form-control form-control-sm mb-2" readonly>
                    <label class="form-label small">Description ({{ draft.description?.length ?? 0 }}/170)</label>
                    <textarea :value="draft.description" class="form-control form-control-sm" rows="3" readonly></textarea>
                </template>
                <template v-else-if="mode === 'itinerary'">
                    <div v-for="day in draft.days ?? []" :key="day.day" class="mb-2 small">
                        <strong>Day {{ day.day }}: {{ day.title }}</strong>
                        <ul class="mb-0"><li v-for="point in day.points" :key="point">{{ point }}</li></ul>
                    </div>
                </template>
                <textarea v-else :value="draft" class="form-control form-control-sm" rows="7" readonly></textarea>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-primary" @click="applyDraft(false)">{{ mode === 'seo' ? 'Apply SEO draft' : mode === 'itinerary' ? 'Use itinerary draft' : 'Replace content' }}</button>
                    <button v-if="mode === 'content' && source" type="button" class="btn btn-sm btn-outline-primary" @click="applyDraft(true)">Insert after content</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="draft = null">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</template>
