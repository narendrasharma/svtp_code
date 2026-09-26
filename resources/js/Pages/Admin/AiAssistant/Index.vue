<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import { renderAssistantMarkdown } from './markdown';

const props = defineProps({
    agent: { type: Object, required: true },
    conversations: { type: Array, default: () => [] },
    canManageKnowledge: { type: Boolean, default: false },
});

const conversations = ref([...props.conversations]);
const activeId = ref(null);
const messages = ref([]);
const proposals = ref([]);
const checkedTools = ref([]);
const question = ref('');
const loading = ref(false);
const opening = ref(false);
const actionPendingId = ref(null);
const error = ref('');
const conversation = ref(null);
const workspace = ref(null);
const composer = ref(null);
const workspaceHeight = ref(null);
const sidebarOpen = ref(false);
const conversationEvidenceOpen = ref(false);
const starters = [
    "Show today's booking activity.",
    'Which tours are active in Agra?',
    'Show hotel arrivals tomorrow.',
    'What is our hotel check-in policy?',
];

let footerObserver = null;

function updateWorkspaceHeight() {
    if (!workspace.value || window.innerWidth < 992) {
        workspaceHeight.value = null;
        return;
    }

    const content = workspace.value.closest('.admin-content');
    const footer = document.querySelector('.dashboard-footer');
    const bottomPadding = content ? parseFloat(getComputedStyle(content).paddingBottom) || 0 : 0;
    const footerHeight = footer?.getBoundingClientRect().height ?? 0;
    workspaceHeight.value = Math.max(360, Math.floor(window.innerHeight - workspace.value.getBoundingClientRect().top - bottomPadding - footerHeight));
}

function resizeComposer() {
    if (!composer.value) return;
    composer.value.style.height = 'auto';
    composer.value.style.height = Math.min(composer.value.scrollHeight, 160) + 'px';
}

function scrollToBottom() {
    nextTick(() => conversation.value?.scrollTo({ top: conversation.value.scrollHeight, behavior: 'smooth' }));
}

function newConversation() {
    if (loading.value || opening.value || actionPendingId.value) return;
    activeId.value = null;
    messages.value = [];
    proposals.value = [];
    checkedTools.value = [];
    error.value = '';
    question.value = '';
    sidebarOpen.value = false;
    nextTick(resizeComposer);
}

async function openConversation(item) {
    if (loading.value || opening.value || actionPendingId.value) return;
    error.value = '';
    opening.value = true;
    try {
        const { data } = await axios.get(appUrl('/admin/ai-assistant/conversations/' + item.id));
        activeId.value = item.id;
        messages.value = data.messages;
        proposals.value = data.proposals ?? [];
        checkedTools.value = data.tools_used ?? [];
        sidebarOpen.value = false;
        scrollToBottom();
    } catch (failure) {
        error.value = failure.response?.data?.message ?? 'Could not open this conversation.';
    } finally {
        opening.value = false;
    }
}

onMounted(() => {
    const id = Number(new URLSearchParams(window.location.search).get('conversation'));
    const item = conversations.value.find(candidate => candidate.id === id);
    if (item) openConversation(item);

    nextTick(() => {
        updateWorkspaceHeight();
        resizeComposer();
        const footer = document.querySelector('.dashboard-footer');
        if (footer && 'ResizeObserver' in window) {
            footerObserver = new ResizeObserver(updateWorkspaceHeight);
            footerObserver.observe(footer);
        }
    });
    window.addEventListener('resize', updateWorkspaceHeight);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateWorkspaceHeight);
    footerObserver?.disconnect();
});

async function renameConversation(item) {
    const title = window.prompt('Conversation title', item.title)?.trim();
    if (!title || title === item.title) return;
    try {
        const { data } = await axios.patch(appUrl('/admin/ai-assistant/conversations/' + item.id), { title });
        item.title = data.title;
    } catch (failure) {
        error.value = failure.response?.data?.message ?? 'Could not rename this conversation.';
    }
}

async function deleteConversation(item) {
    if (!window.confirm('Delete “' + item.title + '”?')) return;
    try {
        await axios.delete(appUrl('/admin/ai-assistant/conversations/' + item.id));
        conversations.value = conversations.value.filter(candidate => candidate.id !== item.id);
        if (activeId.value === item.id) newConversation();
    } catch (failure) {
        error.value = failure.response?.data?.message ?? 'Could not delete this conversation.';
    }
}

async function send(value = question.value) {
    const text = value.trim();
    if (!text || loading.value || opening.value || actionPendingId.value || !props.agent.available) return;
    question.value = '';
    error.value = '';
    loading.value = true;
    messages.value.push({ role: 'user', content: text });
    nextTick(resizeComposer);
    scrollToBottom();

    try {
        const { data } = await axios.post(appUrl('/admin/ai-assistant/message'), {
            question: text,
            conversation_id: activeId.value,
        });
        activeId.value = data.conversation.id;
        const existing = conversations.value.find(item => item.id === activeId.value);
        if (existing) {
            existing.updated_at = data.conversation.updated_at;
            conversations.value = [existing, ...conversations.value.filter(item => item.id !== existing.id)];
        } else {
            conversations.value.unshift(data.conversation);
        }
        messages.value.push({ role: 'assistant', content: data.answer, tools: data.tools_used, sources: data.sources });
        if (data.proposal && !proposals.value.some(proposal => proposal.id === data.proposal.id)) proposals.value.unshift(data.proposal);
        checkedTools.value = [...new Set([...checkedTools.value, ...(data.tools_used ?? [])])];
        scrollToBottom();
    } catch (failure) {
        messages.value.pop();
        question.value = text;
        error.value = failure.response?.data?.message ?? 'The assistant could not answer right now.';
        nextTick(resizeComposer);
    } finally {
        loading.value = false;
    }
}

function onComposerKeydown(event) {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
    event.preventDefault();
    send();
}

function displayValue(value) {
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    return value === null || value === '' ? 'Empty' : String(value);
}

function displayProvider(provider) {
    return { azure: 'Azure Foundry', openai: 'OpenAI', gemini: 'Gemini' }[provider] ?? provider;
}

function activityTime(value) {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toDateString() === new Date().toDateString()
        ? date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
        : date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function evidenceSummary(message) {
    const tools = message.tools?.length ?? 0;
    const sources = message.sources?.length ?? 0;
    return [tools ? tools + ' tool' + (tools === 1 ? '' : 's') : '', sources ? sources + ' knowledge source' + (sources === 1 ? '' : 's') : ''].filter(Boolean).join(' · ');
}

function proposalStatus(proposal) {
    if (proposal.failure_reason === 'stale') return 'Stale';
    return {
        pending: 'Pending confirmation',
        confirmed: 'Confirmed',
        executed: 'Executed',
        rejected: 'Rejected',
        expired: 'Expired',
        failed: 'Failed',
    }[proposal.status] ?? proposal.status;
}

async function decide(proposal, decision) {
    if (actionPendingId.value || proposal.status !== 'pending') return;
    actionPendingId.value = proposal.id;
    error.value = '';
    try {
        const { data } = await axios.post(appUrl('/admin/ai-assistant/actions/' + proposal.id + '/' + decision));
        Object.assign(proposal, data.proposal);
        messages.value.push({ role: 'assistant', content: decision === 'confirm'
            ? 'Confirmed by user. Action completed: ' + proposal.summary
            : 'Action proposal rejected. No change was made.' });
        scrollToBottom();
    } catch (failure) {
        const safeError = failure.response?.data?.message ?? 'The action could not be completed.';
        const current = conversations.value.find(item => item.id === activeId.value);
        if (current) {
            actionPendingId.value = null;
            await openConversation(current);
        }
        error.value = safeError;
    } finally {
        actionPendingId.value = null;
    }
}
</script>

<template>
    <AdminLayout>
        <div class="assistant-page">
            <header class="assistant-page-header">
                <div>
                    <h1 class="h3 mb-1">AI Assistant</h1>
                    <p class="text-muted small mb-0">Explore live marketplace data and knowledge. Review each proposed change before confirming it.</p>
                </div>
                <div class="assistant-header-controls">
                    <a v-if="canManageKnowledge" :href="appUrl('/admin/ai-assistant/knowledge')" class="btn btn-sm btn-outline-secondary">Knowledge</a>
                    <a :href="appUrl('/admin/ai-assistant/actions')" class="btn btn-sm btn-outline-secondary">Action history</a>
                    <span class="assistant-provider" aria-label="AI provider and model">{{ displayProvider(agent.provider) }} · {{ agent.model || 'No model' }}</span>
                </div>
            </header>

            <div v-if="!agent.available" class="assistant-notice" role="status">The assistant is unavailable. Enable AI and configure a tool-capable provider in AI Settings.</div>

            <button type="button" class="btn btn-sm btn-outline-secondary assistant-mobile-toggle" :aria-expanded="sidebarOpen" aria-controls="assistant-conversations" @click="sidebarOpen = !sidebarOpen">
                <i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Conversations
            </button>

            <div ref="workspace" class="assistant-workspace" :style="workspaceHeight ? { height: workspaceHeight + 'px' } : null">
                <aside id="assistant-conversations" class="assistant-sidebar" :class="{ 'is-open': sidebarOpen }" aria-label="Conversations">
                    <div class="assistant-sidebar-header">
                        <strong>Conversations</strong>
                        <button class="btn btn-sm btn-primary" type="button" :disabled="loading || opening || !!actionPendingId" @click="newConversation">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>New
                        </button>
                    </div>
                    <nav class="assistant-conversation-list" aria-label="Saved conversations">
                        <div v-for="item in conversations" :key="item.id" class="assistant-conversation-item" :class="{ 'is-active': activeId === item.id }">
                            <button type="button" class="assistant-conversation-open" :aria-current="activeId === item.id ? 'page' : undefined" :disabled="loading || opening || !!actionPendingId" @click="openConversation(item)">
                                <span class="assistant-conversation-title">{{ item.title }}</span>
                                <time v-if="item.updated_at" class="assistant-conversation-time" :datetime="item.updated_at">{{ activityTime(item.updated_at) }}</time>
                            </button>
                            <div class="assistant-conversation-actions">
                                <button type="button" class="assistant-icon-button" :aria-label="'Rename ' + item.title" :disabled="loading || opening || !!actionPendingId" @click="renameConversation(item)"><i class="bi bi-pencil" aria-hidden="true"></i></button>
                                <button type="button" class="assistant-icon-button assistant-icon-button--danger" :aria-label="'Delete ' + item.title" :disabled="loading || opening || !!actionPendingId" @click="deleteConversation(item)"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                            </div>
                        </div>
                        <p v-if="!conversations.length" class="assistant-sidebar-empty">No conversations yet.</p>
                    </nav>
                    <div v-if="checkedTools.length" class="assistant-sidebar-evidence">
                        <button type="button" class="assistant-evidence-toggle" :aria-expanded="conversationEvidenceOpen" aria-controls="conversation-evidence" @click="conversationEvidenceOpen = !conversationEvidenceOpen">
                            <span>Data checked in conversation</span><span>{{ checkedTools.length }} tools <i class="bi" :class="conversationEvidenceOpen ? 'bi-chevron-down' : 'bi-chevron-right'" aria-hidden="true"></i></span>
                        </button>
                        <div v-show="conversationEvidenceOpen" id="conversation-evidence" class="assistant-evidence-items">
                            <div v-for="tool in checkedTools" :key="tool"><small>Tool</small><span>{{ tool }}</span></div>
                        </div>
                    </div>
                </aside>

                <section class="assistant-chat" aria-label="Current conversation">
                    <div class="assistant-chat-header">
                        <div class="assistant-chat-heading">{{ conversations.find(item => item.id === activeId)?.title || 'New conversation' }}</div>
                        <div class="assistant-chat-subtitle">{{ agent.actions_enabled ? 'Guarded changes require your confirmation' : 'Read-only workspace' }}</div>
                    </div>

                    <div ref="conversation" class="assistant-messages" role="log" aria-live="polite" aria-relevant="additions">
                        <div v-if="!messages.length && !opening" class="assistant-empty">
                            <span class="assistant-empty-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
                            <h2 class="h5 mb-2">What would you like to explore?</h2>
                            <p class="text-muted small mb-3">Ask about live operations, tours, hotels, or indexed policies.</p>
                            <div class="assistant-starters">
                                <button v-for="starter in starters" :key="starter" type="button" class="assistant-starter" :disabled="!agent.available || loading || opening" @click="send(starter)">
                                    <span>{{ starter }}</span><i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div v-for="(message, index) in messages" :key="message.id ?? index" class="assistant-message" :class="message.role === 'user' ? 'assistant-message--user' : 'assistant-message--assistant'">
                            <div class="assistant-message-label">{{ message.role === 'user' ? 'You' : 'Triparo Assistant' }}</div>
                            <div v-if="message.role === 'user'" class="assistant-message-content">{{ message.content }}</div>
                            <div v-else class="assistant-message-content assistant-markdown" v-html="renderAssistantMarkdown(message.content)"></div>
                            <div v-if="message.role !== 'user' && (message.tools?.length || message.sources?.length)" class="assistant-evidence">
                                <button type="button" class="assistant-evidence-toggle" :aria-expanded="!!message.evidenceOpen" :aria-controls="'assistant-evidence-' + index" @click="message.evidenceOpen = !message.evidenceOpen">
                                    <span>Sources / data checked</span>
                                    <span>{{ evidenceSummary(message) }} <i class="bi" :class="message.evidenceOpen ? 'bi-chevron-down' : 'bi-chevron-right'" aria-hidden="true"></i></span>
                                </button>
                                <div v-show="message.evidenceOpen" :id="'assistant-evidence-' + index" class="assistant-evidence-items">
                                    <div v-for="tool in message.tools ?? []" :key="tool"><small>Tool</small><span>{{ tool }}</span></div>
                                    <div v-for="source in message.sources ?? []" :key="source.document_id"><small>Knowledge</small><span>{{ source.title }}</span></div>
                                </div>
                            </div>
                        </div>

                        <div v-for="proposal in proposals" :key="proposal.id" class="assistant-proposal" :class="'assistant-proposal--' + (proposal.failure_reason === 'stale' ? 'stale' : proposal.status)">
                            <div class="assistant-proposal-header">
                                <div><span class="assistant-proposal-eyebrow">Proposed change</span><div class="assistant-proposal-title">{{ proposal.summary }}</div></div>
                                <span class="assistant-proposal-status">{{ proposalStatus(proposal) }}</span>
                            </div>
                            <dl class="assistant-proposal-details">
                                <div><dt>Target</dt><dd>{{ proposal.target_label }} <small>({{ proposal.target_type }} #{{ proposal.target_id }})</small></dd></div>
                                <div><dt>Field</dt><dd>{{ proposal.field_label }}</dd></div>
                                <div><dt>Current</dt><dd>{{ displayValue(proposal.before) }}</dd></div>
                                <div><dt>Proposed</dt><dd class="assistant-proposal-next">{{ displayValue(proposal.proposed) }}</dd></div>
                            </dl>
                            <p v-if="proposal.status === 'pending'" class="assistant-proposal-note">No change has been made. Expires {{ new Date(proposal.expires_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) }}.</p>
                            <p v-else-if="proposal.status === 'executed'" class="assistant-proposal-note">Confirmed and applied.</p>
                            <p v-else-if="proposal.status === 'expired'" class="assistant-proposal-note">Expired. Request a fresh proposal.</p>
                            <p v-else-if="proposal.status === 'rejected'" class="assistant-proposal-note">Rejected. No change was applied.</p>
                            <p v-else-if="proposal.failure_reason === 'stale'" class="assistant-proposal-note">The target changed. Request a fresh proposal.</p>
                            <p v-else-if="proposal.failure_reason === 'denied'" class="assistant-proposal-note">Permission changed. No change was applied.</p>
                            <p v-else-if="proposal.failure_reason === 'missing'" class="assistant-proposal-note">The target no longer exists.</p>
                            <p v-else-if="proposal.failure_reason === 'disabled'" class="assistant-proposal-note">Agent actions are disabled.</p>
                            <p v-else-if="proposal.status === 'failed'" class="assistant-proposal-note">Execution failed. No change was applied.</p>
                            <div v-if="proposal.status === 'pending'" class="assistant-proposal-actions">
                                <button type="button" class="btn btn-sm btn-primary" :disabled="!!actionPendingId || !agent.actions_enabled || loading" @click="decide(proposal, 'confirm')">{{ actionPendingId === proposal.id ? 'Applying…' : 'Confirm change' }}</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="!!actionPendingId || loading" @click="decide(proposal, 'reject')">Reject</button>
                            </div>
                        </div>

                        <div v-if="opening" class="assistant-working" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Loading conversation…</div>
                        <div v-else-if="loading" class="assistant-working" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Triparo Assistant is working…</div>
                    </div>

                    <div class="assistant-composer">
                        <div v-if="error" class="assistant-inline-error" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>{{ error }}</span></div>
                        <form class="assistant-composer-form" @submit.prevent="send()">
                            <textarea ref="composer" v-model="question" class="form-control assistant-composer-input" rows="1" maxlength="2000" :disabled="!agent.available || loading || opening || !!actionPendingId" placeholder="Ask a question about Triparo" aria-label="Message Triparo Assistant" @input="resizeComposer" @keydown="onComposerKeydown"></textarea>
                            <button class="btn btn-primary assistant-send" type="submit" :disabled="!agent.available || loading || opening || !!actionPendingId || !question.trim()">{{ loading ? 'Working…' : 'Send' }}</button>
                        </form>
                        <div class="assistant-composer-hint">Enter to send · Shift+Enter for a new line. {{ agent.actions_enabled ? 'Changes require a separate confirmation.' : 'Agent actions are off.' }}</div>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.assistant-page { width: 100%; min-width: 0; }
.assistant-page-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem 1.5rem; margin-bottom: 1rem; }
.assistant-header-controls { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: .5rem; }
.assistant-provider { padding: .35rem .6rem; color: var(--admin-text-muted); font-size: .78rem; border-left: 1px solid var(--admin-border); overflow-wrap: anywhere; }
.assistant-notice { margin-bottom: .75rem; padding: .65rem .85rem; border: 1px solid var(--alert-warning-border); border-radius: .55rem; background: var(--alert-warning-bg); color: var(--alert-warning-text); font-size: .85rem; }
.assistant-mobile-toggle { display: none; margin-bottom: .75rem; }
.assistant-workspace { display: grid; grid-template-columns: minmax(230px, 26%) minmax(0, 1fr); gap: .85rem; min-height: 360px; height: min(70vh, 760px); }
.assistant-sidebar, .assistant-chat { display: flex; flex-direction: column; min-width: 0; min-height: 0; border: 1px solid var(--admin-border); border-radius: .7rem; background: var(--admin-surface); box-shadow: var(--admin-shadow-sm); overflow: hidden; }
.assistant-sidebar-header, .assistant-chat-header { flex: none; display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .85rem 1rem; border-bottom: 1px solid var(--admin-border); background: var(--admin-surface-sunken); }
.assistant-sidebar-header strong { font-size: .9rem; }
.assistant-conversation-list { flex: 1; min-height: 0; overflow-y: auto; padding: .45rem; }
.assistant-conversation-item { display: flex; align-items: flex-start; gap: .15rem; margin-bottom: .2rem; border: 1px solid transparent; border-radius: .5rem; }
.assistant-conversation-item:hover, .assistant-conversation-item:focus-within { background: var(--admin-surface-sunken); }
.assistant-conversation-item.is-active { border-color: var(--admin-border-strong); background: var(--admin-surface-sunken); }
.assistant-conversation-open { flex: 1; min-width: 0; padding: .65rem .45rem .65rem .7rem; border: 0; background: transparent; color: var(--admin-text); text-align: left; }
.assistant-conversation-open:hover { color: var(--admin-link); }
.assistant-conversation-title { display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; -webkit-line-clamp: 2; line-clamp: 2; font-size: .85rem; font-weight: 600; line-height: 1.35; overflow-wrap: anywhere; }
.assistant-conversation-time { display: block; margin-top: .25rem; color: var(--admin-text-muted); font-size: .72rem; }
.assistant-conversation-actions { display: flex; flex: none; gap: .05rem; padding: .5rem .35rem .25rem 0; opacity: .7; }
.assistant-conversation-item:hover .assistant-conversation-actions, .assistant-conversation-item:focus-within .assistant-conversation-actions, .assistant-conversation-item.is-active .assistant-conversation-actions { opacity: 1; }
.assistant-icon-button { width: 1.7rem; height: 1.7rem; border: 0; border-radius: .35rem; background: transparent; color: var(--admin-text-muted); font-size: .8rem; }
.assistant-icon-button:hover { background: var(--admin-surface); color: var(--admin-link); }
.assistant-icon-button--danger:hover { color: var(--admin-danger); }
.assistant-sidebar-empty { padding: .85rem .6rem; color: var(--admin-text-muted); font-size: .85rem; }
.assistant-sidebar-evidence { flex: none; border-top: 1px solid var(--admin-border); padding: .45rem .75rem; }
.assistant-chat-header { display: block; }
.assistant-chat-heading { color: var(--admin-text); font-size: .9rem; font-weight: 700; line-height: 1.35; overflow-wrap: anywhere; }
.assistant-chat-subtitle { margin-top: .15rem; color: var(--admin-text-muted); font-size: .75rem; }
.assistant-messages { flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 1.2rem; scroll-behavior: smooth; }
.assistant-empty { max-width: 690px; margin: auto; padding: 2rem .5rem; text-align: center; }
.assistant-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; margin-bottom: .9rem; border: 1px solid var(--admin-border); border-radius: .6rem; background: var(--admin-surface-sunken); color: var(--admin-primary); }
.assistant-starters { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; text-align: left; }
.assistant-starter { display: flex; align-items: center; justify-content: space-between; gap: .65rem; min-width: 0; padding: .85rem .9rem; border: 1px solid var(--admin-border); border-radius: .55rem; background: var(--admin-surface); color: var(--admin-text); font-size: .83rem; text-align: left; }
.assistant-starter:hover:not(:disabled), .assistant-starter:focus-visible { border-color: var(--admin-border-strong); background: var(--admin-surface-sunken); }
.assistant-starter i { flex: none; color: var(--admin-text-muted); }
.assistant-message { display: flex; flex-direction: column; align-items: flex-start; margin-bottom: 1rem; min-width: 0; }
.assistant-message--user { align-items: flex-end; }
.assistant-message-label { margin: 0 0 .3rem .15rem; color: var(--admin-text-muted); font-size: .72rem; font-weight: 700; }
.assistant-message-content { width: fit-content; max-width: min(100%, 68ch); padding: .85rem 1rem; border: 1px solid var(--admin-border); border-radius: .65rem; background: var(--admin-surface-elevated); color: var(--admin-text); font-size: .88rem; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; }
.assistant-message--user .assistant-message-content { max-width: min(78%, 52ch); border-color: var(--admin-primary); background: var(--admin-primary); color: var(--admin-primary-contrast); line-height: 1.5; }
.assistant-markdown { white-space: normal; }
.assistant-markdown :deep(p) { margin: 0 0 .7rem; }
.assistant-markdown :deep(p:last-child), .assistant-markdown :deep(ul:last-child), .assistant-markdown :deep(ol:last-child), .assistant-markdown :deep(pre:last-child) { margin-bottom: 0; }
.assistant-markdown :deep(h1), .assistant-markdown :deep(h2), .assistant-markdown :deep(h3), .assistant-markdown :deep(h4) { margin: .9rem 0 .45rem; font-size: 1rem; font-weight: 700; line-height: 1.35; }
.assistant-markdown :deep(h1:first-child), .assistant-markdown :deep(h2:first-child), .assistant-markdown :deep(h3:first-child) { margin-top: 0; }
.assistant-markdown :deep(ul), .assistant-markdown :deep(ol) { margin: 0 0 .7rem; padding-left: 1.4rem; }
.assistant-markdown :deep(li) { margin-bottom: .2rem; }
.assistant-markdown :deep(a) { color: var(--admin-link); text-decoration: underline; text-underline-offset: 2px; }
.assistant-markdown :deep(code) { padding: .1rem .25rem; border-radius: .2rem; background: var(--admin-surface-sunken); font-size: .82em; }
.assistant-markdown :deep(pre) { max-width: 100%; margin: 0 0 .7rem; padding: .7rem .8rem; overflow-x: auto; border: 1px solid var(--admin-border); border-radius: .4rem; background: var(--admin-surface-sunken); }
.assistant-markdown :deep(pre code) { padding: 0; background: transparent; }
.assistant-evidence { width: min(100%, 68ch); margin-top: .25rem; }
.assistant-evidence-toggle { display: flex; align-items: center; justify-content: space-between; gap: .75rem; width: 100%; padding: .35rem .45rem; border: 0; border-radius: .35rem; background: transparent; color: var(--admin-text-muted); font-size: .73rem; text-align: left; }
.assistant-evidence-toggle:hover { background: var(--admin-surface-sunken); color: var(--admin-text); }
.assistant-evidence-toggle > span:last-child { white-space: nowrap; }
.assistant-evidence-items { display: grid; gap: .4rem; padding: .35rem .5rem .55rem; }
.assistant-evidence-items > div { display: grid; gap: .1rem; min-width: 0; padding: .35rem .55rem; border-left: 2px solid var(--admin-border-strong); color: var(--admin-text); font-size: .77rem; overflow-wrap: anywhere; }
.assistant-evidence-items small { color: var(--admin-text-muted); font-size: .68rem; font-weight: 700; }
.assistant-proposal { max-width: 640px; margin: .3rem 0 1.1rem; border: 1px solid var(--admin-border-strong); border-left: 3px solid var(--admin-warning); border-radius: .55rem; background: var(--admin-surface-elevated); overflow: hidden; }
.assistant-proposal--executed { border-left-color: var(--admin-success); }
.assistant-proposal--rejected, .assistant-proposal--expired, .assistant-proposal--failed, .assistant-proposal--stale { border-left-color: var(--admin-text-muted); }
.assistant-proposal-header { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; padding: .85rem 1rem; border-bottom: 1px solid var(--admin-border); }
.assistant-proposal-eyebrow { display: block; margin-bottom: .2rem; color: var(--admin-warning); font-size: .7rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
.assistant-proposal-title { color: var(--admin-text); font-size: .86rem; font-weight: 600; line-height: 1.4; overflow-wrap: anywhere; }
.assistant-proposal-status { flex: none; padding: .2rem .45rem; border: 1px solid var(--admin-border-strong); border-radius: .3rem; color: var(--admin-text-muted); font-size: .7rem; font-weight: 700; }
.assistant-proposal--pending .assistant-proposal-status { color: var(--admin-warning); }
.assistant-proposal--executed .assistant-proposal-status { color: var(--admin-success); }
.assistant-proposal-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem 1rem; margin: 0; padding: .85rem 1rem .35rem; }
.assistant-proposal-details > div { min-width: 0; }
.assistant-proposal-details dt { margin-bottom: .15rem; color: var(--admin-text-muted); font-size: .7rem; font-weight: 700; }
.assistant-proposal-details dd { margin: 0; color: var(--admin-text); font-size: .82rem; line-height: 1.4; white-space: pre-wrap; overflow-wrap: anywhere; }
.assistant-proposal-details dd small { color: var(--admin-text-muted); }
.assistant-proposal-next { font-weight: 700; }
.assistant-proposal-note { margin: .2rem 1rem .75rem; color: var(--admin-text-muted); font-size: .75rem; }
.assistant-proposal-actions { display: flex; flex-wrap: wrap; gap: .5rem; padding: .75rem 1rem; border-top: 1px solid var(--admin-border); background: var(--admin-surface-sunken); }
.assistant-working { display: flex; align-items: center; gap: .55rem; width: fit-content; padding: .55rem .7rem; color: var(--admin-text-muted); font-size: .8rem; }
.assistant-working .spinner-border { width: .8rem; height: .8rem; border-width: .12rem; }
.assistant-composer { flex: none; padding: .85rem 1rem .75rem; border-top: 1px solid var(--admin-border); background: var(--admin-surface-sunken); }
.assistant-inline-error { display: flex; align-items: flex-start; gap: .5rem; margin-bottom: .65rem; padding: .5rem .65rem; border: 1px solid var(--alert-danger-border); border-radius: .4rem; background: var(--alert-danger-bg); color: var(--alert-danger-text); font-size: .78rem; line-height: 1.4; }
.assistant-composer-form { display: flex; align-items: flex-end; gap: .55rem; }
.assistant-composer-input { flex: 1; min-width: 0; min-height: 2.5rem; max-height: 160px; resize: none; overflow-y: auto; border-color: var(--admin-input-border); background: var(--admin-input-bg); color: var(--admin-text); }
.assistant-send { flex: none; min-height: 2.5rem; }
.assistant-composer-hint { margin-top: .45rem; color: var(--admin-text-muted); font-size: .7rem; }
@media (max-width: 991.98px) {
    .assistant-mobile-toggle { display: inline-flex; }
    .assistant-workspace { display: block; min-height: 0; height: auto !important; }
    .assistant-sidebar { display: none; max-height: 38vh; margin-bottom: .75rem; }
    .assistant-sidebar.is-open { display: flex; }
    .assistant-chat { height: min(72dvh, 700px); min-height: 380px; }
}
@media (max-width: 575.98px) {
    .assistant-header-controls { justify-content: flex-start; }
    .assistant-provider { flex-basis: 100%; padding-left: 0; border-left: 0; }
    .assistant-messages { padding: .9rem .75rem; }
    .assistant-starters { grid-template-columns: 1fr; }
    .assistant-message--user .assistant-message-content { max-width: 90%; }
    .assistant-proposal-details { grid-template-columns: 1fr; }
    .assistant-proposal-status { white-space: normal; }
    .assistant-composer { padding: .75rem; }
}
</style>
