<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({ item: Object, mode: { type: String, default: 'desktop' }, depth: { type: Number, default: 0 } });
const emit = defineEmits(['navigate']);
const open = ref(false);
const page = usePage();
const href = computed(() => new URL(props.item.href, window.location.href));
const internal = computed(() => href.value.origin === window.location.origin && props.item.target === '_self' && !/^[#?]/.test(props.item.href) && !/\.[a-z0-9]+$/i.test(href.value.pathname));
const active = computed(() => href.value.origin === window.location.origin && href.value.pathname === new URL(page.url, window.location.origin).pathname && href.value.search === new URL(page.url, window.location.origin).search);
const linkClass = computed(() => props.mode === 'footer' ? '' : props.mode === 'mobile' ? 'mobile-nav-link' : props.depth === 0 ? 'nav-link-custom' : 'dropdown-item');
const submenuId = computed(() => `cms-${props.mode}-${props.item.id}`);
function navigate() { open.value = false; emit('navigate'); }
</script>

<template>
    <li class="cms-menu-item" :class="[`cms-${mode}`, { 'dropdown': mode === 'desktop' && depth === 0, 'mb-2': mode === 'footer' }]" @keydown.esc.stop="open = false" @focusout="event => { if (!event.currentTarget.contains(event.relatedTarget)) open = false; }">
        <div class="cms-link-row">
            <component :is="internal ? Link : 'a'" :href="item.href" :target="item.target" :rel="item.target === '_blank' ? 'noopener noreferrer' : undefined" :class="[linkClass, { 'is-active': active }]" :aria-current="active ? 'page' : undefined" @click="navigate">{{ item.label }}</component>
            <button v-if="item.children.length && mode !== 'footer'" class="cms-submenu-toggle" type="button" :aria-label="`${open ? 'Close' : 'Open'} ${item.label} submenu`" :aria-expanded="open" :aria-controls="submenuId" @click="open = !open"><i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i></button>
        </div>
        <ul v-if="item.children.length" v-show="mode === 'footer' || open" :id="submenuId" class="list-unstyled cms-submenu" :class="{ 'dropdown-menu shadow border-0 show': mode === 'desktop' && depth === 0 }">
            <PublicMenuItem v-for="child in item.children" :key="child.id" :item="child" :mode="mode" :depth="depth + 1" @navigate="navigate" />
        </ul>
    </li>
</template>

<style scoped>
.cms-menu-item { position: relative; }
.cms-link-row { display: flex; align-items: center; }
.cms-link-row > a { flex: 1; }
.cms-submenu-toggle { flex-shrink: 0; border: 0; background: transparent; color: inherit; padding: 8px; font-size: .75rem; border-radius: 4px; }
.cms-submenu-toggle:focus-visible { outline: 2px solid currentColor; }
.cms-desktop > .cms-link-row > .nav-link-custom { padding-right: 5px; }
.cms-submenu { margin-bottom: 0; }
.cms-submenu.dropdown-menu { top: 100%; left: 0; min-width: 220px; max-width: min(320px, 90vw); }
.cms-submenu:not(.dropdown-menu) { padding-left: 16px; }
.cms-desktop .dropdown-item { white-space: normal; overflow-wrap: anywhere; }
.cms-mobile .cms-link-row { border-bottom: 1px solid rgba(15,23,42,.08); }
.cms-mobile .mobile-nav-link { display: block; padding: .7rem 0; color: var(--color-text, #243b53); text-decoration: none; font-weight: 600; }
.cms-footer > .cms-submenu { margin-top: .5rem; border-left: 1px solid currentColor; }
</style>
