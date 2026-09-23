<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { appUrl } from '../../../appUrl';
import { useLocalization } from '../../../i18n';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import SeoHead from '../../../Components/SeoHead.vue';
import ReviewCard from '../../../Components/Hotel/ReviewCard.vue';

const props = defineProps({
    booking: { type: Object, required: true },
    review: { type: Object, default: null },
    eligibility: { type: Object, required: true },
    categories: { type: Object, default: () => ({}) },
    minimumCommentLength: { type: Number, default: 20 },
    moderationEnabled: { type: Boolean, default: true },
});

const { t } = useLocalization();

const editable = computed(() => props.eligibility.can_review || props.eligibility.can_edit);
const form = useForm({
    overall_rating: props.review?.overall_rating ?? '',
    ...Object.fromEntries(Object.keys(props.categories).map((key) => [key, props.review?.[key] ?? ''])),
    title: props.review?.title ?? '',
    comment: props.review?.comment ?? '',
});

function submit() {
    form.submit(props.review ? 'put' : 'post', appUrl(`/account/hotel-bookings/${props.booking.id}/review`), { preserveScroll: true });
}

function categoryLabel(key, fallback) {
    return t(`common.review_${key.replace('_rating', '')}`, fallback);
}
</script>

<template>
    <PublicLayout main-class="hotel-customer-page hotel-customer-review-page">
        <SeoHead :title="review ? t('common.your_review', 'Your review') : t('common.write_review', 'Write a review')" noindex />

        <div class="hotel-customer-container">
            <Link :href="appUrl(`/account/hotel-bookings/${booking.id}`)" class="public-button public-button--text public-button--sm">
                <i class="bi bi-arrow-left" data-dir-icon="arrow" aria-hidden="true"></i>
                {{ t('common.back_to_booking', 'Back to booking') }}
            </Link>

            <header class="hotel-customer-review-intro">
                <span class="public-eyebrow">{{ t('common.after_your_stay', 'After your stay') }}</span>
                <h1 class="public-heading public-heading--1">{{ review ? t('common.your_review', 'Your review') : t('common.write_review', 'Write a review') }}</h1>
                <p>{{ booking.property_name }} · <span class="hotel-customer-status hotel-customer-status--positive"><span class="hotel-customer-status__dot" aria-hidden="true"></span>{{ t('common.verified_stay', 'Verified stay') }}</span></p>
            </header>

            <div v-if="review" class="hotel-customer-review-status" :class="{ 'hotel-customer-review-status--rejected': review.status === 'rejected' }" role="status">
                <p v-if="review.status === 'pending'">{{ t('common.review_pending_editable', 'Your review was submitted and is pending moderation. You can update it before approval.') }}</p>
                <p v-else-if="review.status === 'approved'">{{ t('common.review_approved_public', 'Your review is approved and visible to guests.') }}</p>
                <p v-else>{{ t('common.review_not_approved', 'This review was not approved.') }}<span v-if="review.rejection_reason"> {{ review.rejection_reason }}</span></p>
            </div>

            <form v-if="editable" class="hotel-customer-panel hotel-customer-review-form" @submit.prevent="submit">
                <div>
                    <span class="public-eyebrow">{{ t('common.share_experience', 'Share your experience') }}</span>
                    <h2 class="public-heading public-heading--3">{{ t('common.rate_your_stay', 'Rate your stay') }}</h2>
                </div>

                <div v-if="form.errors.booking || form.errors.review" class="hotel-customer-alert" role="alert">{{ form.errors.booking || form.errors.review }}</div>

                <fieldset>
                    <legend class="hotel-customer-form__label">{{ t('common.overall_rating', 'Overall rating') }}</legend>
                    <div class="hotel-customer-rating-group" role="radiogroup" :aria-label="t('common.overall_rating', 'Overall rating')">
                        <template v-for="rating in [1, 2, 3, 4, 5]" :key="rating">
                            <input :id="`overall-${rating}`" v-model.number="form.overall_rating" class="hotel-customer-rating-option" type="radio" name="overall_rating" :value="rating" required />
                            <label :for="`overall-${rating}`" class="hotel-customer-rating-label">
                                <span aria-hidden="true">★</span>{{ rating }} / 5
                            </label>
                        </template>
                    </div>
                    <p v-if="form.errors.overall_rating" class="hotel-customer-form__error" role="alert">{{ form.errors.overall_rating }}</p>
                </fieldset>

                <div class="hotel-customer-form__grid">
                    <div v-for="(label, key) in categories" :key="key">
                        <label :for="`hotel-review-${key}`" class="hotel-customer-form__label">
                            {{ categoryLabel(key, label) }}
                            <select :id="`hotel-review-${key}`" v-model.number="form[key]" class="hotel-customer-form__control" required>
                                <option value="" disabled>{{ t('common.select_rating', 'Select a rating') }}</option>
                                <option v-for="rating in [1, 2, 3, 4, 5]" :key="rating" :value="rating">{{ rating }} / 5</option>
                            </select>
                        </label>
                        <p v-if="form.errors[key]" class="hotel-customer-form__error" role="alert">{{ form.errors[key] }}</p>
                    </div>
                </div>

                <label for="hotel-review-title" class="hotel-customer-form__label">
                    {{ t('common.review_title', 'Title') }} <span>({{ t('common.optional', 'optional') }})</span>
                    <input id="hotel-review-title" v-model="form.title" class="hotel-customer-form__control" maxlength="120" />
                </label>
                <p v-if="form.errors.title" class="hotel-customer-form__error" role="alert">{{ form.errors.title }}</p>

                <label for="hotel-review-comment" class="hotel-customer-form__label">
                    {{ t('common.review_comment', 'Your review') }}
                    <textarea id="hotel-review-comment" v-model="form.comment" class="hotel-customer-form__control" rows="7" :minlength="minimumCommentLength" maxlength="2000" required aria-describedby="hotel-review-help"></textarea>
                    <span id="hotel-review-help">{{ minimumCommentLength }}–2,000 {{ t('common.characters', 'characters') }}. {{ t('common.review_privacy_note', 'Please leave out contact details and booking references.') }}</span>
                </label>
                <p v-if="form.errors.comment" class="hotel-customer-form__error" role="alert">{{ form.errors.comment }}</p>

                <p class="hotel-customer-note">{{ moderationEnabled || review ? t('common.review_moderation_note', 'Your review may remain pending until it is approved.') : t('common.review_publish_note', 'Your review will be published after submission.') }}</p>
                <button type="submit" class="public-button public-button--primary" :disabled="form.processing">
                    {{ form.processing ? t('common.saving_review', 'Saving review…') : review ? t('common.update_review', 'Update pending review') : t('common.submit_review', 'Submit review') }}
                </button>
            </form>

            <section v-else-if="review" class="hotel-customer-panel" aria-labelledby="submitted-review-heading">
                <div class="hotel-customer-panel__heading">
                    <div>
                        <span class="public-eyebrow">{{ t('common.review_status', 'Review status') }}</span>
                        <h2 id="submitted-review-heading" class="public-heading public-heading--3">{{ t('common.submitted_review', 'Submitted review') }}</h2>
                    </div>
                    <i class="bi bi-patch-check hotel-customer-panel__icon" aria-hidden="true"></i>
                </div>
                <ReviewCard :review="review" />
            </section>
        </div>
    </PublicLayout>
</template>
