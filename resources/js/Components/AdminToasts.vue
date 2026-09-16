<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const AUTO_DISMISS_MS = { success: 4500, info: 4500, warning: 6000, error: 0 };
const MAX_ERROR_MESSAGES = 5;

const TYPE_META = {
    success: { icon: 'bi-check-circle-fill', title: 'Success', role: 'status' },
    error: { icon: 'bi-x-octagon-fill', title: 'Error', role: 'alert' },
    warning: { icon: 'bi-exclamation-triangle-fill', title: 'Warning', role: 'alert' },
    info: { icon: 'bi-info-circle-fill', title: 'Notice', role: 'status' },
};

const page = usePage();
const toasts = ref([]);
let nextId = 1;
// Module-level refs survive AdminLayout remounts between Inertia visits, so a
// flash object that was already toasted is never shown twice, while a fresh
// object from a later visit (even with identical text) still produces a toast.
let lastFlashRef = null;
let lastErrorsRef = null;

function dismiss(id) {
    const index = toasts.value.findIndex(toast => toast.id === id);
    if (index !== -1) {
        clearTimeout(toasts.value[index].timer);
        toasts.value.splice(index, 1);
    }
}

function push(type, message, title) {
    const messages = (Array.isArray(message) ? message : [message]).filter(Boolean);
    if (!messages.length) return;
    const shown = messages.slice(0, MAX_ERROR_MESSAGES);
    if (messages.length > shown.length) shown.push(`…and ${messages.length - shown.length} more`);
    const timeout = AUTO_DISMISS_MS[type] ?? AUTO_DISMISS_MS.info;
    const toast = { id: nextId++, type, title: title ?? TYPE_META[type].title, messages: shown, timeout, timer: null };
    toasts.value.push(toast);
    if (timeout > 0) toast.timer = setTimeout(() => dismiss(toast.id), timeout);
}

function pushFlash(flash) {
    if (!flash || flash === lastFlashRef) return;
    lastFlashRef = flash;
    if (flash.error) push('error', flash.error);
    if (flash.warning) push('warning', flash.warning);
    if (flash.success) push('success', flash.success);
    if (flash.info) push('info', flash.info);
    // Legacy single-string convention used across admin controllers: ->with('flash', '…')
    if (flash.message) push('success', flash.message);
}

function pushErrors(errors) {
    if (!errors || errors === lastErrorsRef) return;
    lastErrorsRef = errors;
    const messages = Object.values(errors).flat().filter(Boolean);
    if (!messages.length) return;
    push('error', messages, 'Please correct the following errors');
}

watch(() => page.props.flash, pushFlash, { immediate: true });
watch(() => page.props.errors, pushErrors, { immediate: true });

onBeforeUnmount(() => {
    for (const toast of toasts.value) clearTimeout(toast.timer);
});
</script>

<template>
    <div class="admin-toasts" aria-label="Notifications">
        <TransitionGroup name="admin-toast" tag="div" class="admin-toasts-stack">
            <div v-for="toast in toasts" :key="toast.id" class="admin-toast" :class="`admin-toast-${toast.type}`" :role="TYPE_META[toast.type].role">
                <i class="bi admin-toast-icon" :class="TYPE_META[toast.type].icon" aria-hidden="true"></i>
                <div class="admin-toast-body">
                    <strong class="admin-toast-title">{{ toast.title }}</strong>
                    <ul v-if="toast.messages.length > 1" class="admin-toast-list">
                        <li v-for="(message, index) in toast.messages" :key="index">{{ message }}</li>
                    </ul>
                    <p v-else class="admin-toast-message">{{ toast.messages[0] }}</p>
                </div>
                <button type="button" class="admin-toast-close" aria-label="Dismiss notification" @click="dismiss(toast.id)"><i class="bi bi-x" aria-hidden="true"></i></button>
                <span v-if="toast.timeout > 0" class="admin-toast-progress" :style="{ animationDuration: `${toast.timeout}ms` }" aria-hidden="true"></span>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.admin-toasts { position: fixed; top: 1rem; right: 1rem; z-index: 2000; width: min(400px, calc(100vw - 2rem)); pointer-events: none; }
.admin-toasts-stack { display: flex; flex-direction: column; gap: .6rem; }
.admin-toast { position: relative; display: flex; align-items: flex-start; gap: .7rem; padding: .8rem .9rem; overflow: hidden; border: 1px solid #334155; border-radius: .75rem; background: #111c2d; box-shadow: 0 12px 32px rgba(0,0,0,.45); pointer-events: auto; }
.admin-toast-icon { font-size: 1.25rem; line-height: 1.25; }
.admin-toast-success { border-left: 3px solid #22c55e; }
.admin-toast-success .admin-toast-icon { color: #22c55e; }
.admin-toast-success .admin-toast-progress { background: #22c55e; }
.admin-toast-error { border-left: 3px solid #ef4444; }
.admin-toast-error .admin-toast-icon { color: #ef4444; }
.admin-toast-warning { border-left: 3px solid #f59e0b; }
.admin-toast-warning .admin-toast-icon { color: #f59e0b; }
.admin-toast-warning .admin-toast-progress { background: #f59e0b; }
.admin-toast-info { border-left: 3px solid #38bdf8; }
.admin-toast-info .admin-toast-icon { color: #38bdf8; }
.admin-toast-info .admin-toast-progress { background: #38bdf8; }
.admin-toast-body { min-width: 0; flex: 1; }
.admin-toast-title { display: block; color: #f8fafc; font-size: .85rem; font-weight: 700; }
.admin-toast-message { margin: .1rem 0 0; color: #cbd5e1; font-size: .82rem; }
.admin-toast-list { margin: .25rem 0 0; padding-left: 1.1rem; color: #cbd5e1; font-size: .82rem; }
.admin-toast-close { display: inline-flex; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; padding: 0; border: 0; border-radius: .45rem; background: transparent; color: #94a3b8; font-size: 1.1rem; }
.admin-toast-close:hover { background: #26344c; color: #fff; }
.admin-toast-close:focus-visible { outline: 2px solid #f59e0b; outline-offset: 1px; }
.admin-toast-progress { position: absolute; left: 0; bottom: 0; width: 100%; height: 2px; transform-origin: left center; animation-name: admin-toast-progress; animation-timing-function: linear; animation-fill-mode: forwards; }
@keyframes admin-toast-progress { from { transform: scaleX(1); } to { transform: scaleX(0); } }
.admin-toast-enter-active { transition: opacity .25s ease, transform .25s ease; }
.admin-toast-leave-active { transition: opacity .2s ease, transform .2s ease; }
.admin-toast-enter-from { opacity: 0; transform: translateX(24px); }
.admin-toast-leave-to { opacity: 0; transform: translateX(24px); }
@media (max-width: 991.98px) {
    .admin-toasts { top: calc(60px + .75rem); right: .75rem; left: .75rem; width: auto; }
}
</style>
