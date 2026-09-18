<script setup>
import axios from 'axios';
import { ref } from 'vue';
import { appUrl } from '../appUrl';

/**
 * Reusable Send/Share menu for documents (quotation, invoice, receipt).
 * Posts to a share endpoint: email sends branded mail, whatsapp returns
 * a wa.me URL, and every action is logged. Nothing is ever auto-sent.
 */
const props = defineProps({
    endpoint: { type: String, required: true },
    defaultEmail: { type: String, default: '' },
    defaultPhone: { type: String, default: '' },
    secureUrl: { type: String, default: '' },
    label: { type: String, default: 'Share' },
});

const channel = ref('email');
const email = ref(props.defaultEmail);
const phone = ref(props.defaultPhone);
const busy = ref(false);
const result = ref(null);
const error = ref(null);

async function submit() {
    busy.value = true;
    result.value = null;
    error.value = null;

    try {
        const response = await axios.post(appUrl(props.endpoint), {
            channel: channel.value,
            to_email: email.value || undefined,
            to_phone: phone.value || undefined,
        });

        result.value = response.data;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Sharing failed. Please try again.';
    } finally {
        busy.value = false;
    }
}

function copyLink() {
    if (props.secureUrl && navigator.clipboard) {
        navigator.clipboard.writeText(props.secureUrl);
        result.value = { copied: true };
    }
}
</script>

<template>
    <div class="share-menu">
        <h5 class="mb-3">{{ label }}</h5>
        <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Channel">
            <button type="button" class="btn" :class="channel === 'email' ? 'btn-svtp' : 'btn-outline-secondary'" @click="channel = 'email'">Email</button>
            <button type="button" class="btn" :class="channel === 'whatsapp' ? 'btn-svtp' : 'btn-outline-secondary'" @click="channel = 'whatsapp'">WhatsApp</button>
        </div>
        <div v-if="channel === 'email'" class="mb-2">
            <label class="form-label small">To email</label>
            <input v-model="email" type="email" class="form-control form-control-sm" maxlength="255" placeholder="customer@example.com" />
        </div>
        <div v-else class="mb-2">
            <label class="form-label small">To phone</label>
            <input v-model="phone" type="tel" class="form-control form-control-sm" maxlength="30" placeholder="+91…" />
            <div class="form-text">Opens a prefilled wa.me chat — nothing is sent automatically.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-svtp" :disabled="busy" @click="submit">
                {{ channel === 'email' ? 'Send Email' : 'Get WhatsApp Link' }}
            </button>
            <button v-if="secureUrl" type="button" class="btn btn-sm btn-outline-secondary" @click="copyLink">Copy secure link</button>
        </div>
        <div v-if="result?.sent" class="alert alert-success small mt-2 mb-0">Email sent and logged.</div>
        <div v-if="result?.copied" class="alert alert-success small mt-2 mb-0">Secure link copied.</div>
        <a v-if="result?.whatsapp_url" :href="result.whatsapp_url" target="_blank" rel="noopener" class="btn btn-sm btn-success mt-2">Open WhatsApp chat</a>
        <div v-if="error" class="alert alert-danger small mt-2 mb-0">{{ error }}</div>
    </div>
</template>
