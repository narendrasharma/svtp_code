<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { appUrl } from '../../appUrl';

const props = defineProps({ text: { type: String, default: '' }, path: String, method: { type: String, default: 'put' } });
const form = useForm({ reply: props.text ?? '' });
watch(() => props.text, value => { form.reply = value ?? ''; });
function submit() { form.submit(props.method, appUrl(props.path), { preserveScroll: true }); }
</script>

<template>
    <form class="card p-3 p-md-4 d-flex flex-column gap-3" @submit.prevent="submit">
        <div>
            <h2 class="h5 mb-1">Official property response</h2>
            <p class="small text-muted mb-0">One response per review. Updating it replaces your previous response.</p>
        </div>
        <div>
            <label for="hotel-review-reply" class="form-label">Your response</label>
            <textarea id="hotel-review-reply" v-model="form.reply" class="form-control" rows="5" minlength="10" maxlength="2000" required aria-describedby="hotel-reply-help hotel-reply-error"></textarea>
            <div id="hotel-reply-help" class="form-text">10–2,000 characters. Plain text only. Visible publicly while the review is approved.</div>
            <div id="hotel-reply-error" class="text-danger small" role="alert">{{ form.errors.reply }}</div>
        </div>
        <button class="btn btn-primary align-self-start" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save response' }}</button>
    </form>
</template>
