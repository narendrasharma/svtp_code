<script setup>
import axios from 'axios';
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    campaign: { type: Object, default: null },
    audienceTypes: { type: Array, default: () => [] },
    channels: { type: Array, default: () => [] },
});

const isEdit = computed(() => props.campaign !== null);
const endpoint = appUrl('/admin/campaigns');

const form = useForm({
    name: props.campaign?.name ?? '',
    subject: props.campaign?.subject ?? '',
    content: props.campaign?.content ?? '',
    channel: props.campaign?.channel ?? 'email',
    audience_type: props.campaign?.audience_type ?? 'selected',
    audience_filter: {
        user_ids: props.campaign?.audience_filter?.user_ids ?? [],
        with_bookings: props.campaign?.audience_filter?.with_bookings ?? false,
        verified_vendors: props.campaign?.audience_filter?.verified_vendors ?? false,
    },
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
    const list = form.audience_filter.user_ids;
    const index = list.indexOf(id);

    if (index >= 0) {
        list.splice(index, 1);
    } else if (list.length < 200) {
        list.push(id);
    }
}

function submit() {
    if (isEdit.value) {
        form.put(`${endpoint}/${props.campaign.id}`);
    } else {
        form.post(endpoint);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="isEdit ? appUrl(`/admin/campaigns/${campaign.id}`) : endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Campaigns</Link>
            <h2 class="mt-2 mb-1">{{ isEdit ? `Edit ${campaign.reference}` : 'New Campaign' }}</h2>
            <p class="text-muted mb-0">Drafts only — sending happens from the campaign page with per-recipient tracking.</p>
        </div>

        <form class="card p-3 p-md-4" style="max-width: 820px;" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-6"><label for="campaign-name" class="form-label small">Internal name*</label><input id="campaign-name" v-model="form.name" class="form-control" required maxlength="150" /></div>
                <div class="col-md-6"><label for="campaign-subject" class="form-label small">Subject*</label><input id="campaign-subject" v-model="form.subject" class="form-control" required maxlength="255" /></div>
                <div class="col-12"><label for="campaign-content" class="form-label small">Content*</label><textarea id="campaign-content" v-model="form.content" class="form-control" rows="8" required maxlength="20000"></textarea></div>
                <div class="col-md-6">
                    <label for="campaign-channel" class="form-label small">Channel</label>
                    <select id="campaign-channel" v-model="form.channel" class="form-select">
                        <option v-for="c in channels" :key="c" :value="c">{{ c }}</option>
                    </select>
                    <div class="form-text">SMS/WhatsApp unlock once a provider is configured.</div>
                </div>
                <div class="col-md-6">
                    <label for="campaign-audience" class="form-label small">Audience</label>
                    <select id="campaign-audience" v-model="form.audience_type" class="form-select">
                        <option v-for="a in audienceTypes" :key="a" :value="a">{{ a }}</option>
                    </select>
                </div>
                <div v-if="form.audience_type === 'selected'" class="col-12">
                    <SmartSelect
                        label="Find users to add (max 200)"
                        :fetch-options="searchUsers"
                        placeholder="Search customers or vendors…"
                        :loading="userLoading"
                        @change="(option) => option && toggleUser(option.value)"
                    />
                    <p class="small text-muted mt-1">Selected: {{ form.audience_filter.user_ids.join(', ') || 'none' }}</p>
                </div>
                <div class="col-md-6">
                    <div class="form-check"><input id="campaign-bookings" v-model="form.audience_filter.with_bookings" type="checkbox" class="form-check-input" /><label for="campaign-bookings" class="form-check-label small">Only customers with bookings</label></div>
                </div>
                <div class="col-md-6">
                    <div class="form-check"><input id="campaign-vendors" v-model="form.audience_filter.verified_vendors" type="checkbox" class="form-check-input" /><label for="campaign-vendors" class="form-check-label small">Only verified vendors</label></div>
                </div>
                <div class="col-12"><button class="btn btn-svtp" :disabled="form.processing">{{ isEdit ? 'Save Draft' : 'Create Draft' }}</button></div>
                <div v-if="form.hasErrors" class="col-12 text-danger small"><div v-for="(e, k) in form.errors" :key="k">{{ e }}</div></div>
            </div>
        </form>
    </AdminLayout>
</template>
