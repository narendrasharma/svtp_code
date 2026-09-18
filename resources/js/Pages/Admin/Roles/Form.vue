<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    role: { type: Object, default: null },
    allPermissions: { type: Object, default: () => ({}) },
});

const isEdit = computed(() => props.role !== null);
const isProtected = computed(() => props.role?.is_protected === true);

const form = useForm({
    name: props.role?.name ?? '',
    description: props.role?.description ?? '',
    permissions: props.role?.permissions ? [...props.role.permissions] : [],
});

function toggle(permission) {
    if (isProtected.value) return;
    const index = form.permissions.indexOf(permission);
    if (index >= 0) form.permissions.splice(index, 1);
    else form.permissions.push(permission);
}

function toggleGroup(keys) {
    if (isProtected.value) return;
    const allSelected = keys.every((key) => form.permissions.includes(key));
    if (allSelected) form.permissions = form.permissions.filter((p) => !keys.includes(p));
    else keys.forEach((key) => {
        if (!form.permissions.includes(key)) form.permissions.push(key);
    });
}

function submit() {
    if (isEdit.value) form.put(appUrl(`/admin/roles/${props.role.id}`));
    else form.post(appUrl('/admin/roles'));
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="appUrl('/admin/roles')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Roles</Link>
            <h2 class="mt-2 mb-1">{{ isEdit ? `Edit ${role.name}` : 'New Role' }}</h2>
            <p v-if="isProtected" class="text-warning mb-0">Protected role — name and permissions are locked to the full catalogue.</p>
            <p v-else class="text-muted mb-0">Role names are display labels only; tick the permission keys this role grants.</p>
        </div>

        <form class="card p-3 p-md-4" style="max-width: 900px;" @submit.prevent="submit">
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label for="role-name" class="form-label">Name* <small class="text-muted">(lowercase letters, digits, dashes)</small></label>
                    <input id="role-name" v-model="form.name" class="form-control" required maxlength="60" :disabled="isProtected" />
                    <small class="text-danger">{{ form.errors.name }}</small>
                </div>
                <div class="col-md-7">
                    <label for="role-description" class="form-label">Description</label>
                    <input id="role-description" v-model="form.description" class="form-control" maxlength="255" />
                    <small class="text-danger">{{ form.errors.description }}</small>
                </div>
            </div>

            <div v-for="(group, groupKey) in allPermissions" :key="groupKey" class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">{{ group.label }}</h5>
                    <button v-if="!isProtected" type="button" class="btn btn-link btn-sm p-0 text-decoration-none" @click="toggleGroup(Object.keys(group.permissions))">
                        Toggle group
                    </button>
                </div>
                <div class="row g-2">
                    <div v-for="(description, key) in group.permissions" :key="key" class="col-md-6">
                        <label class="d-flex gap-2 align-items-start">
                            <input type="checkbox" class="form-check-input mt-1" :checked="form.permissions.includes(key)" :disabled="isProtected" @change="toggle(key)" />
                            <span>
                                <code class="small">{{ key }}</code>
                                <small class="d-block text-muted">{{ description }}</small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <button class="btn btn-svtp" :disabled="form.processing || isProtected && false">{{ isEdit ? 'Save Changes' : 'Create Role' }}</button>
        </form>
    </AdminLayout>
</template>
