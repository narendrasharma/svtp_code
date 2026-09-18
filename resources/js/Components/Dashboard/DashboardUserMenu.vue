<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';

const props = defineProps({
    profileUrl: { type: String, default: null },
    showViewWebsite: { type: Boolean, default: true },
});

const page = usePage();
const isOpen = ref(false);
const menuRef = ref(null);

const user = computed(() => page.props.auth?.user ?? null);
const userName = computed(() => user.value?.name ?? 'User');
const userRole = computed(() => {
    const raw = user.value?.role ?? '';
    if (raw === 'admin') return 'Administrator';
    if (raw === 'vendor') return 'Vendor';
    if (raw === 'customer') return 'Customer';
    return raw ? raw.charAt(0).toUpperCase() + raw.slice(1) : '';
});
const initials = computed(() => {
    const name = userName.value.trim();
    if (!name) return 'U';
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase();
});

function toggle() {
    isOpen.value = !isOpen.value;
}
function close() {
    isOpen.value = false;
}
function onClickOutside(e) {
    if (menuRef.value && !menuRef.value.contains(e.target)) close();
}
onMounted(() => document.addEventListener('click', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside));

function logout() {
    router.post(appUrl('/admin/logout'));
}
</script>

<template>
    <div ref="menuRef" class="dashboard-user-menu" :class="{ 'is-open': isOpen }">
        <button
            type="button"
            class="dashboard-user-trigger"
            :aria-expanded="isOpen"
            aria-haspopup="menu"
            @click.stop="toggle"
        >
            <span class="dashboard-user-avatar" aria-hidden="true">{{ initials }}</span>
            <span class="dashboard-user-meta">
                <span class="dashboard-user-name">{{ userName }}</span>
                <span class="dashboard-user-role">{{ userRole }}</span>
            </span>
            <i class="bi bi-chevron-down dashboard-user-chevron" :class="{ 'is-rotated': isOpen }"></i>
        </button>

        <div v-if="isOpen" class="dashboard-user-dropdown" role="menu">
            <div class="dashboard-user-dropdown-header">
                <strong>{{ userName }}</strong>
                <span>{{ user?.email }}</span>
            </div>
            <Link
                v-if="profileUrl"
                :href="profileUrl"
                class="dashboard-user-item"
                role="menuitem"
                @click="close"
            >
                <i class="bi bi-person-gear"></i> Profile
            </Link>
            <Link
                v-if="showViewWebsite"
                :href="appUrl('/')"
                class="dashboard-user-item"
                role="menuitem"
                @click="close"
            >
                <i class="bi bi-box-arrow-up-right"></i> View Website
            </Link>
            <div class="dashboard-user-separator"></div>
            <button
                type="button"
                class="dashboard-user-item dashboard-user-logout"
                role="menuitem"
                @click="logout(); close()"
            >
                <i class="bi bi-box-arrow-right"></i> Logout
            </button>
        </div>
    </div>
</template>

<style scoped>
.dashboard-user-menu {
    position: relative;
}
.dashboard-user-trigger {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.35rem 0.6rem 0.35rem 0.35rem;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 999px;
    background: rgba(255,255,255,0.06);
    color: #e5e7eb;
    cursor: pointer;
    transition: background 0.2s ease, border-color 0.2s ease;
}
.dashboard-user-trigger:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.14);
}
.dashboard-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #f59e0b, #ec4899);
    color: #fff;
    font-weight: 700;
    font-size: 0.8rem;
    flex: 0 0 32px;
}
.dashboard-user-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    line-height: 1.1;
    text-align: left;
    min-width: 0;
}
.dashboard-user-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: #f8fafc;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
}
.dashboard-user-role {
    font-size: 0.68rem;
    color: #94a3b8;
    font-weight: 500;
}
.dashboard-user-chevron {
    font-size: 0.7rem;
    color: #94a3b8;
    transition: transform 0.2s ease;
}
.dashboard-user-chevron.is-rotated {
    transform: rotate(180deg);
}
.dashboard-user-dropdown {
    position: absolute;
    top: calc(100% + 0.5rem);
    right: 0;
    min-width: 220px;
    background: #101827;
    border: 1px solid #263247;
    border-radius: 12px;
    box-shadow: 0 16px 40px rgba(0,0,0,0.4);
    padding: 0.5rem;
    z-index: 1050;
}
.dashboard-user-dropdown-header {
    padding: 0.6rem 0.75rem 0.75rem;
    border-bottom: 1px solid #263247;
    margin-bottom: 0.5rem;
}
.dashboard-user-dropdown-header strong {
    display: block;
    font-size: 0.82rem;
    color: #f8fafc;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dashboard-user-dropdown-header span {
    font-size: 0.72rem;
    color: #94a3b8;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
}
.dashboard-user-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: #cbd5e1;
    font-size: 0.82rem;
    text-align: left;
    text-decoration: none;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.dashboard-user-item:hover {
    background: #1e293b;
    color: #f8fafc;
}
.dashboard-user-item i {
    width: 1rem;
    text-align: center;
    color: #94a3b8;
}
.dashboard-user-item:hover i {
    color: #fbbf24;
}
.dashboard-user-separator {
    height: 1px;
    background: #263247;
    margin: 0.5rem 0;
}
.dashboard-user-logout {
    color: #fca5a5;
}
.dashboard-user-logout:hover {
    background: rgba(220,38,38,0.12);
    color: #fca5a5;
}
@media (max-width: 575px) {
    .dashboard-user-meta {
        display: none;
    }
    .dashboard-user-trigger {
        padding: 0.25rem;
    }
}
</style>
