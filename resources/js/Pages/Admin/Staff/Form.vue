<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    member: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
    allPermissions: { type: Object, default: () => ({}) },
});

const isEdit = computed(() => props.member !== null);

const form = useForm({
    name: props.member?.name ?? '',
    email: props.member?.email ?? '',
    password: '',
    password_confirmation: '',
    roles: props.member?.roles ? [...props.member.roles] : [],
});

const effectivePermissions = computed(() => {
    if (isEdit.value && props.member?.effective_permissions) return props.member.effective_permissions;
    return [];
});

function toggleRole(roleName, assignable) {
    if (!assignable) return;
    const index = form.roles.indexOf(roleName);
    if (index >= 0) form.roles.splice(index, 1);
    else form.roles.push(roleName);
}

function submit() {
    if (isEdit.value) form.put(appUrl(`/admin/staff/${props.member.id}`));
    else form.post(appUrl('/admin/staff'));
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="appUrl('/admin/staff')" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Staff</Link>
            <h2 class="mt-2 mb-1">{{ isEdit ? `Manage ${member.name}` : 'New Staff Member' }}</h2>
            <p class="text-muted mb-0">Staff accounts always use the internal admin account type. Only a Super Admin can grant the Super Admin role.</p>
        </div>

        <form class="card p-3 p-md-4" style="max-width: 820px;" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="staff-name" class="form-label">Name*</label>
                    <input id="staff-name" v-model="form.name" class="form-control" required maxlength="255" />
                    <small class="text-danger">{{ form.errors.name }}</small>
                </div>
                <div class="col-md-6">
                    <label for="staff-email" class="form-label">Email*</label>
                    <input id="staff-email" v-model="form.email" type="email" class="form-control" required maxlength="255" />
                    <small class="text-danger">{{ form.errors.email }}</small>
                </div>
                <div class="col-md-6">
                    <label for="staff-password" class="form-label">{{ isEdit ? 'New password (leave blank to keep)' : 'Password*' }}</label>
                    <input id="staff-password" v-model="form.password" type="password" class="form-control" :required="!isEdit" minlength="8" autocomplete="new-password" />
                    <small class="text-danger">{{ form.errors.password }}</small>
                </div>
                <div class="col-md-6">
                    <label for="staff-password-confirm" class="form-label">Confirm password</label>
                    <input id="staff-password-confirm" v-model="form.password_confirmation" type="password" class="form-control" autocomplete="new-password" />
                </div>
            </div>

            <h5 class="mt-4 mb-2">Staff roles</h5>
            <p class="small text-muted">Authorization always uses permission keys — role names are never hardcoded in business logic.</p>
            <div class="row g-2">
                <div v-for="role in roles" :key="role.name" class="col-md-6">
                    <label class="border rounded p-2 d-flex gap-2 align-items-start w-100" :class="{ 'opacity-50': !role.assignable }">
                        <input type="checkbox" class="form-check-input mt-1" :checked="form.roles.includes(role.name)" :disabled="!role.assignable" @change="toggleRole(role.name, role.assignable)" />
                        <span>
                            <span class="d-block fw-semibold">{{ role.name }}</span>
                            <small class="text-muted">{{ role.description }}</small>
                            <small v-if="!role.assignable" class="d-block text-warning">Only a Super Admin can assign this role.</small>
                        </span>
                    </label>
                </div>
            </div>
            <small class="text-danger">{{ form.errors.roles }}</small>

            <div v-if="isEdit && effectivePermissions.length" class="mt-4">
                <h5 class="mb-2">Effective permissions ({{ effectivePermissions.length }})</h5>
                <div class="d-flex flex-wrap gap-1">
                    <span v-for="permission in effectivePermissions" :key="permission" class="badge bg-secondary fw-normal">{{ permission }}</span>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-svtp" :disabled="form.processing">{{ isEdit ? 'Save Changes' : 'Create Staff Member' }}</button>
            </div>
        </form>
    </AdminLayout>
</template>
