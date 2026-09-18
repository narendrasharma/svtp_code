<script setup>
import axios from 'axios';
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    preselected: { type: Array, default: () => [] },
});

const form = useForm({
    user_ids: props.preselected.map((u) => u.value),
    title: '',
    body: '',
    action_url: '',
});

const userLoading = ref(false);
let userSeq = 0;

async function searchUsers(query) {
    const seq = ++userSeq;
    userLoading.value = true;

    try {
        const response = await axios.get(appUrl('/admin/select-options'), { params: { type: 'users', search: query } });

        if (seq !== userSeq) {
            return [];
        }

        return response.data.options ?? [];
    } catch (e) {
        return [];
    } finally {
        if (seq === userSeq) {
            userLoading.value = false;
        }
    }
}

function toggleUser(id) {
    const index = form.user_ids.indexOf(id);

    if (index >= 0) {
        form.user_ids.splice(index, 1);
    } else if (form.user_ids.length < 50) {
        form.user_ids.push(id);
    }
}

function submit() {
    form.post(appUrl('/admin/messages'), {
        preserveScroll: true,
        onSuccess: () => form.reset('title', 'body', 'action_url'),
    });
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <h2 class="mt-2 mb-1">Message Users</h2>
            <p class="text-muted mb-0">One-way announcements — in-app plus email per each recipient's preferences. No reply thread (open a support ticket for discussion).</p>
        </div>

        <form class="card p-3 p-md-4" style="max-width: 760px;" @submit.prevent="submit">
            <SmartSelect
                label="Find users to add"
                :fetch-options="searchUsers"
                placeholder="Search by name or email…"
                :loading="userLoading"
                @change="(option) => option && toggleUser(option.value)"
            />
            <p class="small text-muted mt-2 mb-3">
                Selected ({{ form.user_ids.length }}/50): {{ form.user_ids.join(', ') || 'none' }}
                <button v-if="form.user_ids.length" type="button" class="btn btn-link btn-sm p-0 ms-2" @click="form.user_ids = []">Clear</button>
            </p>
            <div v-if="form.errors.user_ids" class="text-danger small mb-2">{{ form.errors.user_ids }}</div>

            <label for="message-title" class="form-label small">Title*</label>
            <input id="message-title" v-model="form.title" class="form-control mb-2" required maxlength="150" />
            <div v-if="form.errors.title" class="text-danger small mb-2">{{ form.errors.title }}</div>

            <label for="message-body" class="form-label small">Message*</label>
            <textarea id="message-body" v-model="form.body" class="form-control mb-2" rows="5" required maxlength="5000"></textarea>
            <div v-if="form.errors.body" class="text-danger small mb-2">{{ form.errors.body }}</div>

            <label for="message-url" class="form-label small">Action link <span class="text-muted">(optional, must start with /)</span></label>
            <input id="message-url" v-model="form.action_url" class="form-control mb-3" maxlength="500" placeholder="/admin/bookings" />

            <button class="btn btn-svtp" :disabled="form.processing || !form.user_ids.length">Send Message</button>
            <Link :href="appUrl('/admin/communication-logs')" class="btn btn-link btn-sm">View message log</Link>
        </form>
    </AdminLayout>
</template>
