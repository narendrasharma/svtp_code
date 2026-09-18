<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    placeholders: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/templates');
const createForm = useForm({
    key: '', name: '', email_subject: '', email_body: '',
    sms_body: '', whatsapp_body: '', in_app_title: '', in_app_body: '',
    is_active: true,
});
const editingId = ref(null);
const editForm = useForm({
    key: '', name: '', email_subject: '', email_body: '',
    sms_body: '', whatsapp_body: '', in_app_title: '', in_app_body: '',
    is_active: true,
});

function create() {
    createForm.post(endpoint, { preserveScroll: true, onSuccess: () => createForm.reset() });
}

function startEdit(template) {
    editingId.value = template.id;
    Object.assign(editForm, {
        key: template.key, name: template.name,
        email_subject: template.email_subject ?? '', email_body: template.email_body ?? '',
        sms_body: template.sms_body ?? '', whatsapp_body: template.whatsapp_body ?? '',
        in_app_title: template.in_app_title ?? '', in_app_body: template.in_app_body ?? '',
        is_active: !!template.is_active,
    });
}

function saveEdit(id) {
    editForm.put(`${endpoint}/${id}`, { preserveScroll: true, onSuccess: () => { editingId.value = null; } });
}
</script>

<template>
    <AdminLayout>
        <h2 class="mt-2 mb-1">Message Templates</h2>
        <p class="text-muted mb-3">
            Reusable copy for every channel. Only these placeholders are allowed:
            <code v-for="p in placeholders" :key="p" class="me-1">{{ '{' + '{' + p + '}' + '}' }}</code>
            Anything else is rejected at save and can never execute code.
        </p>

        <form class="card p-3 mb-4" @submit.prevent="create">
            <h5 class="mb-3">New template</h5>
            <div class="row g-2">
                <div class="col-md-3"><label for="tpl-key" class="form-label small">Key* <span class="text-muted">(letters, digits, _)</span></label><input id="tpl-key" v-model="createForm.key" class="form-control form-control-sm" required maxlength="60" /></div>
                <div class="col-md-5"><label for="tpl-name" class="form-label small">Name*</label><input id="tpl-name" v-model="createForm.name" class="form-control form-control-sm" required maxlength="150" /></div>
                <div class="col-md-4"><div class="form-check mt-4"><input id="tpl-active" v-model="createForm.is_active" type="checkbox" class="form-check-input" /><label for="tpl-active" class="form-check-label small">Active</label></div></div>
                <div class="col-md-6"><label for="tpl-subject" class="form-label small">Email subject</label><input id="tpl-subject" v-model="createForm.email_subject" class="form-control form-control-sm" maxlength="255" /></div>
                <div class="col-md-6"><label for="tpl-inapp-title" class="form-label small">In-app title</label><input id="tpl-inapp-title" v-model="createForm.in_app_title" class="form-control form-control-sm" maxlength="255" /></div>
                <div class="col-md-6"><label for="tpl-email" class="form-label small">Email body</label><textarea id="tpl-email" v-model="createForm.email_body" class="form-control form-control-sm" rows="4" maxlength="10000"></textarea></div>
                <div class="col-md-6"><label for="tpl-inapp" class="form-label small">In-app body</label><textarea id="tpl-inapp" v-model="createForm.in_app_body" class="form-control form-control-sm" rows="4" maxlength="5000"></textarea></div>
                <div class="col-md-6"><label for="tpl-wa" class="form-label small">WhatsApp body</label><textarea id="tpl-wa" v-model="createForm.whatsapp_body" class="form-control form-control-sm" rows="3" maxlength="4000"></textarea></div>
                <div class="col-md-6"><label for="tpl-sms" class="form-label small">SMS body <span class="text-muted">(max 500)</span></label><textarea id="tpl-sms" v-model="createForm.sms_body" class="form-control form-control-sm" rows="3" maxlength="500"></textarea></div>
                <div class="col-12"><button class="btn btn-sm btn-svtp" :disabled="createForm.processing">Create Template</button></div>
            </div>
            <div v-if="createForm.hasErrors" class="text-danger small mt-2"><div v-for="(e, k) in createForm.errors" :key="k">{{ e }}</div></div>
        </form>

        <div v-for="template in templates" :key="template.id" class="card p-3 mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div>
                    <code>{{ template.key }}</code>
                    <strong class="ms-2">{{ template.name }}</strong>
                    <span class="badge ms-2" :class="template.is_active ? 'bg-success' : 'bg-secondary'">{{ template.is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                <button v-if="editingId !== template.id" type="button" class="btn btn-sm btn-outline-primary" @click="startEdit(template)">Edit</button>
            </div>
            <div v-if="editingId === template.id" class="row g-2">
                <div class="col-md-6"><label class="form-label small">Name</label><input v-model="editForm.name" class="form-control form-control-sm" maxlength="150" /></div>
                <div class="col-md-3"><label class="form-label small">Email subject</label><input v-model="editForm.email_subject" class="form-control form-control-sm" maxlength="255" /></div>
                <div class="col-md-3"><div class="form-check mt-4"><input v-model="editForm.is_active" type="checkbox" class="form-check-input" /><label class="form-check-label small">Active</label></div></div>
                <div class="col-md-6"><label class="form-label small">Email body</label><textarea v-model="editForm.email_body" class="form-control form-control-sm" rows="4" maxlength="10000"></textarea></div>
                <div class="col-md-6"><label class="form-label small">In-app body</label><textarea v-model="editForm.in_app_body" class="form-control form-control-sm" rows="4" maxlength="5000"></textarea></div>
                <div class="col-md-6"><label class="form-label small">WhatsApp body</label><textarea v-model="editForm.whatsapp_body" class="form-control form-control-sm" rows="3" maxlength="4000"></textarea></div>
                <div class="col-md-6"><label class="form-label small">SMS body</label><textarea v-model="editForm.sms_body" class="form-control form-control-sm" rows="3" maxlength="500"></textarea></div>
                <div class="col-12">
                    <button type="button" class="btn btn-sm btn-svtp me-1" @click="saveEdit(template.id)">Save</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="editingId = null">Cancel</button>
                </div>
                <div v-if="editForm.hasErrors" class="col-12 text-danger small"><div v-for="(e, k) in editForm.errors" :key="k">{{ e }}</div></div>
            </div>
            <dl v-else class="row mb-0 small">
                <dt class="col-md-2 text-muted">Email</dt><dd class="col-md-10 text-truncate">{{ template.email_subject || '—' }}</dd>
                <dt class="col-md-2 text-muted">SMS</dt><dd class="col-md-10 text-truncate">{{ template.sms_body || '—' }}</dd>
            </dl>
        </div>
    </AdminLayout>
</template>
