<script setup>
import { ref } from 'vue';

defineProps({
    text: { type: String, required: true },
    label: { type: String, default: 'More information' },
});

const open = ref(false);
</script>

<template>
    <span
        class="field-help"
        @mouseenter="open = true"
        @mouseleave="open = false"
        @focusin="open = true"
        @focusout="open = false"
    >
        <button
            type="button"
            class="field-help__button"
            :aria-label="label"
            :aria-expanded="open"
            @click.stop="open = true"
        >
            <i class="bi bi-question-circle" aria-hidden="true"></i>
        </button>
        <span v-if="open" class="field-help__popover" role="tooltip">{{ text }}</span>
    </span>
</template>

<style scoped>
.field-help {
    position: relative;
    display: inline-flex;
    margin-inline-start: .3rem;
    vertical-align: middle;
}

.field-help__button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.2rem;
    height: 1.2rem;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: var(--admin-primary, #f59e0b);
    cursor: help;
}

.field-help__button:hover,
.field-help__button:focus-visible {
    color: var(--admin-primary-hover, #d97706);
    outline: 2px solid var(--admin-focus-ring, rgba(245, 158, 11, .35));
    outline-offset: 2px;
}

.field-help__popover {
    position: absolute;
    z-index: 1080;
    inset-inline-start: 0;
    top: calc(100% + .35rem);
    width: min(280px, calc(100vw - 2rem));
    padding: .65rem .75rem;
    border: 1px solid var(--admin-popover-border, #334155);
    border-radius: .5rem;
    background: var(--admin-popover-bg, #162235);
    color: var(--admin-text, #e2e8f0);
    box-shadow: var(--admin-shadow-md, 0 8px 24px rgba(0, 0, 0, .2));
    font-size: .8rem;
    font-weight: 400;
    line-height: 1.45;
    white-space: normal;
}

:dir(rtl) .field-help__popover { inset-inline-start: auto; inset-inline-end: 0; }
</style>
