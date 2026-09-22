<script setup>
import { watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import ReviewCard from '../../../../Components/Hotel/ReviewCard.vue';
import ReviewReplyForm from '../../../../Components/Hotel/ReviewReplyForm.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({ review: Object, categories: Object, canModerate: Boolean, canReply: Boolean, activity: Array });
const form = useForm({ action: '', reason: props.review.rejection_reason ?? '' });
const removeForm = useForm({});
watch(() => props.review.rejection_reason, value => { form.reason = value ?? ''; });
function moderate(action) {
    form.action = action;
    form.patch(appUrl(`/admin/hotel/reviews/${props.review.id}/moderate`), { preserveScroll: true });
}
function removeReply() { removeForm.delete(appUrl(`/admin/hotel/reviews/${props.review.id}/reply`), { preserveScroll: true }); }
</script>

<template>
    <AdminLayout>
        <div class="d-flex flex-column gap-3">
            <Link :href="appUrl('/admin/hotel/reviews')">← Hotel Reviews</Link>
            <div><h1 class="h3">{{ review.property.name }}</h1><span class="badge bg-secondary">{{ review.status }}</span><span class="small text-muted ms-2">Submitted {{ review.submitted_at?.slice(0, 10) }}</span></div>
            <ReviewCard :review="review" />
            <div class="card p-3"><div class="row g-2"><div v-for="(label, key) in categories" :key="key" class="col-6 col-md-4 small">{{ label }}: <strong>{{ review.category_ratings[key] }} / 5</strong></div></div></div>
            <form v-if="canModerate" class="card p-3 p-md-4 d-flex flex-column gap-3" @submit.prevent="moderate('reject')">
                <h2 class="h5 mb-0">Moderation</h2>
                <p class="small text-muted mb-0">Rejecting a published review immediately removes it and its response from the public page and rating totals.</p>
                <div><label for="hr-reason" class="form-label">Reason for rejection (optional)</label><textarea id="hr-reason" v-model="form.reason" class="form-control" rows="2" maxlength="500"></textarea><div v-for="(error, key) in form.errors" :key="key" class="text-danger small" role="alert">{{ error }}</div></div>
                <div class="d-flex flex-wrap gap-2"><button v-if="review.status !== 'approved'" type="button" class="btn btn-success" :disabled="form.processing" @click="moderate('approve')">Approve review</button><button class="btn btn-outline-danger" :disabled="form.processing">{{ review.status === 'rejected' ? 'Update rejection reason' : 'Reject review' }}</button></div>
            </form>
            <ReviewReplyForm v-if="canModerate && review.reply_text !== null" :text="review.reply_text" :path="`/admin/hotel/reviews/${review.id}/reply`" />
            <button v-if="canModerate && review.reply_text !== null" type="button" class="btn btn-outline-danger align-self-start" :disabled="removeForm.processing" @click="removeReply">Remove property response</button>
            <ReviewReplyForm v-if="canReply && review.status === 'approved' && review.reply_text === null" :path="`/admin/hotel/reviews/${review.id}/reply`" method="post" />
            <div class="card p-3 p-md-4"><h2 class="h5">Review activity</h2><p class="small text-muted">Most recent 30 events. Full history remains in Activity Logs.</p><div v-for="entry in activity" :key="entry.id" class="border-bottom py-2 small"><strong>{{ entry.description }}</strong><div class="text-muted">{{ entry.created_at }}</div><p v-if="entry.new_values?.rejection_reason" class="mb-0">Reason: {{ entry.new_values.rejection_reason }}</p></div><p v-if="!activity.length" class="text-muted mb-0">No recorded activity yet.</p></div>
        </div>
    </AdminLayout>
</template>
