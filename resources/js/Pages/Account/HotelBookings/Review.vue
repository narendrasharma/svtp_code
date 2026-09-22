<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';
import ReviewCard from '../../../Components/Hotel/ReviewCard.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({ booking: Object, review: Object, eligibility: Object, categories: Object, minimumCommentLength: Number, moderationEnabled: Boolean });
const editable = computed(() => props.eligibility.can_review || props.eligibility.can_edit);
const form = useForm({
    overall_rating: props.review?.overall_rating ?? '',
    ...Object.fromEntries(Object.keys(props.categories).map(key => [key, props.review?.[key] ?? ''])),
    title: props.review?.title ?? '',
    comment: props.review?.comment ?? '',
});
function submit() {
    form.submit(props.review ? 'put' : 'post', appUrl(`/account/hotel-bookings/${props.booking.id}/review`), { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <SeoHead title="Your Hotel Review" noindex />
        <div class="container py-5" style="max-width: 850px">
            <Link :href="appUrl(`/account/hotel-bookings/${booking.id}`)">← Back to your booking</Link>
            <h1 class="h2 mt-3">{{ review ? 'Your review' : 'Write a review' }}</h1>
            <p class="text-muted">{{ booking.property_name }} · <span class="text-success"><i class="bi bi-patch-check me-1" aria-hidden="true"></i>Verified stay</span></p>
            <div v-if="review" class="alert" :class="review.status === 'approved' ? 'alert-success' : 'alert-info'" role="status">
                <template v-if="review.status === 'pending'">Your review is pending moderation. You can update it before approval.</template>
                <template v-else-if="review.status === 'approved'">Your review is published. Published reviews cannot be edited.</template>
                <template v-else>Your review was not approved.<span v-if="review.rejection_reason"> {{ review.rejection_reason }}</span></template>
            </div>
            <form v-if="editable" class="card p-3 p-md-4 d-flex flex-column gap-4" @submit.prevent="submit">
                <div v-if="form.errors.booking || form.errors.review" class="alert alert-danger mb-0" role="alert">{{ form.errors.booking || form.errors.review }}</div>
                <fieldset>
                    <legend class="h5">Overall rating</legend>
                    <div class="d-flex flex-wrap gap-2">
                        <div v-for="rating in [1, 2, 3, 4, 5]" :key="rating">
                            <input :id="`overall-${rating}`" v-model.number="form.overall_rating" type="radio" name="overall_rating" :value="rating" class="btn-check" required />
                            <label :for="`overall-${rating}`" class="btn btn-outline-primary" :aria-label="`${rating} out of 5 stars`">{{ rating }} ★</label>
                        </div>
                    </div>
                    <p class="small text-muted mt-2 mb-0">1 = poor · 5 = excellent. Your overall score is independent of the categories below.</p>
                    <div v-if="form.errors.overall_rating" class="text-danger small" role="alert">{{ form.errors.overall_rating }}</div>
                </fieldset>
                <div class="row g-3">
                    <div v-for="(label, key) in categories" :key="key" class="col-sm-6">
                        <label :for="`hr-${key}`" class="form-label">{{ label }}</label>
                        <select :id="`hr-${key}`" v-model.number="form[key]" class="form-select" required><option value="" disabled>Select a rating</option><option v-for="rating in [1, 2, 3, 4, 5]" :key="rating" :value="rating">{{ rating }} / 5</option></select>
                        <div v-if="form.errors[key]" class="text-danger small" role="alert">{{ form.errors[key] }}</div>
                    </div>
                </div>
                <div><label for="hr-title" class="form-label">Title (optional)</label><input id="hr-title" v-model="form.title" maxlength="120" class="form-control" /><div v-if="form.errors.title" class="text-danger small" role="alert">{{ form.errors.title }}</div></div>
                <div>
                    <label for="hr-comment" class="form-label">Share your experience</label>
                    <textarea id="hr-comment" v-model="form.comment" rows="6" :minlength="minimumCommentLength" maxlength="2000" required class="form-control" aria-describedby="hr-comment-help"></textarea>
                    <div id="hr-comment-help" class="form-text">{{ minimumCommentLength }}–2,000 characters. Plain text only. Please leave out contact details and booking references.</div>
                    <div v-if="form.errors.comment" class="text-danger small" role="alert">{{ form.errors.comment }}</div>
                </div>
                <p class="small text-muted mb-0">Your public name is shortened for privacy. {{ moderationEnabled || review ? 'Your review will remain pending until Admin approves it.' : 'Your review will be published after submission.' }}</p>
                <button class="btn btn-svtp align-self-start" :disabled="form.processing">{{ form.processing ? 'Saving…' : review ? 'Update pending review' : 'Submit review' }}</button>
            </form>
            <ReviewCard v-else-if="review" :review="review" />
        </div>
    </AppLayout>
</template>
