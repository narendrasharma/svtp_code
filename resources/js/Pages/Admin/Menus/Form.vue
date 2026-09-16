<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    menu: { type: Object, default: null },
    item: { type: Object, default: null },
    // When creating/editing a menu item we also receive the parent menu
    parentMenu: { type: Object, default: null },
    // List of possible parent items (top‑level only)
    menuItems: { type: Array, default: () => [] },
    // CMS pages for the Page dropdown
    pages: { type: Array, default: () => [] },
});

const isItem = !!props.parentMenu; // true for both create & edit of a menu item

const form = useForm({
    // Common fields for both menu and menu item
    name: props.menu?.name ?? '',
    slug: props.menu?.slug ?? '',
    location: props.menu?.location ?? '',
    sort_order: props.menu?.sort_order ?? 0,
    is_active: props.menu?.is_active ?? true,

    // Menu‑item specific fields
    title: props.item?.title ?? '',
    type: props.item?.type ?? 'custom',
    page_id: props.item?.page_id ?? null,
    url: props.item?.url ?? '',
    parent_id: props.item?.parent_id ?? null,
    target: props.item?.target ?? '_self',
});

watch(() => form.name, (val) => {
    if (!props.menu && !props.item) {
        form.slug = val.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
});

function submit() {
    if (isItem) {
        // parentMenu is always defined when we are dealing with a menu item
        const base = `${appUrl('/admin/menus')}/${props.parentMenu.id}/items`;
        const url = props.item ? `${base}/${props.item.id}` : base;
        props.item ? form.put(url) : form.post(url);
    } else {
        const url = props.menu ? `${appUrl('/admin/menus')}/${props.menu.id}` : appUrl('/admin/menus');
        props.menu ? form.put(url) : form.post(url);
    }
}
</script>

<template>
    <AdminLayout>
        <h2 v-if="!isItem">{{ menu ? 'Edit' : 'New' }} Menu</h2>
        <h2 v-else>{{ item ? 'Edit' : 'New' }} Menu Item</h2>

        <form class="mt-3" style="max-width: 640px;" @submit.prevent="submit">
            <!-- Menu fields -->
            <template v-if="!isItem">
                <label class="form-label">Name</label>
                <input v-model="form.name" class="form-control" required />
                <small class="text-danger">{{ form.errors.name }}</small>

                <label for="menu-location" class="form-label mt-3">Display location</label>
                <select id="menu-location" v-model="form.location" class="form-select"><option value="">Not assigned</option><option value="header">Header</option><option value="footer">Footer</option></select>
                <small class="text-danger">{{ form.errors.location }}</small>

                <label class="form-label mt-3">Slug</label>
                <input v-model="form.slug" class="form-control" required />
                <small class="text-danger">{{ form.errors.slug }}</small>

                <label class="form-label mt-3">Display order</label>
                <input v-model.number="form.sort_order" type="number" min="0" class="form-control" />
                <small class="text-danger">{{ form.errors.sort_order }}</small>

                <div class="form-check mt-3">
                    <input id="menu-active" v-model="form.is_active" type="checkbox" class="form-check-input" />
                    <label for="menu-active" class="form-check-label">Active</label>
                </div>
                <small class="text-danger">{{ form.errors.is_active }}</small>
            </template>

            <!-- Menu‑item fields -->
            <template v-else>
                <label class="form-label">Title</label>
                <input v-model="form.title" class="form-control" required />
                <small class="text-danger">{{ form.errors.title }}</small>

                <label class="form-label mt-3">Type</label>
                <select v-model="form.type" class="form-select">
                    <option value="custom">Custom URL</option>
                    <option value="page">Page</option>
                </select>
                <small class="text-danger">{{ form.errors.type }}</small>

                <template v-if="form.type === 'custom'">
                    <label class="form-label mt-3">URL</label>
                    <input v-model="form.url" class="form-control" placeholder="https://example.com" />
                    <small class="text-danger">{{ form.errors.url }}</small>
                </template>

                <template v-else>
                    <label class="form-label mt-3">Page</label>
                    <select v-model="form.page_id" class="form-select">
                        <option :value="null">— Select a page —</option>
                        <option v-for="page in pages" :key="page.id" :value="page.id">
                            {{ page.title }}
                        </option>
                    </select>
                    <small class="text-danger">{{ form.errors.page_id }}</small>
                </template>

                <label class="form-label mt-3">Parent Item (optional)</label>
                <select v-model="form.parent_id" class="form-select">
                    <option :value="null">— No parent —</option>
                    <option v-for="item in menuItems" :key="item.id" :value="item.id">
                        {{ item.title }}
                    </option>
                </select>
                <small class="text-danger">{{ form.errors.parent_id }}</small>

                <label class="form-label mt-3">Target</label>
                <select v-model="form.target" class="form-select">
                    <option value="_self">Same window</option>
                    <option value="_blank">New tab</option>
                </select>
                <small class="text-danger">{{ form.errors.target }}</small>

                <label class="form-label mt-3">Display order</label>
                <input v-model.number="form.sort_order" type="number" min="0" class="form-control" />
                <small class="text-danger">{{ form.errors.sort_order }}</small>

                <div class="form-check mt-3">
                    <input id="item-active" v-model="form.is_active" type="checkbox" class="form-check-input" />
                    <label for="item-active" class="form-check-label">Active</label>
                </div>
                <small class="text-danger">{{ form.errors.is_active }}</small>
            </template>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">
                    Save {{ isItem ? 'Item' : 'Menu' }}
                </button>
                <Link :href="appUrl(isItem ? `/admin/menus/${parentMenu.id}/items` : '/admin/menus')" class="btn btn-outline-secondary">
                    Cancel
                </Link>
            </div>
        </form>
    </AdminLayout>
</template>
