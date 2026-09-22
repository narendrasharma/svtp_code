<script setup>
import { Link } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import ReviewCard from '../../../../Components/Hotel/ReviewCard.vue';
import ReviewReplyForm from '../../../../Components/Hotel/ReviewReplyForm.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ review: Object, categories: Object, canReply: Boolean });
</script>

<template>
    <VendorLayout>
        <div class="d-flex flex-column gap-3">
            <Link :href="appUrl('/vendor/hotel/reviews')">← Hotel Reviews</Link>
            <div><h1 class="h3">{{ review.property.name }}</h1><span class="badge bg-secondary">{{ review.status }}</span></div>
            <ReviewCard :review="review" />
            <div class="card p-3"><div class="row g-2"><div v-for="(label, key) in categories" :key="key" class="col-6 col-md-4 small">{{ label }}: <strong>{{ review.category_ratings[key] }} / 5</strong></div></div></div>
            <ReviewReplyForm v-if="canReply" :text="review.vendor_reply?.comment" :path="`/vendor/hotel/reviews/${review.id}/reply`" />
            <p v-else class="small text-muted">Responses are available for approved reviews when property responses are enabled.</p>
        </div>
    </VendorLayout>
</template>
