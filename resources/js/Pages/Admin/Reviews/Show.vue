<script setup>
import { appUrl } from '../../../appUrl';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import StarRating from '../../../Components/StarRating.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({ review: Object });
const reviewerName = props.review.reviewer_name || props.review.user?.name || 'Guest';
const reviewerEmail = props.review.reviewer_email || props.review.user?.email || 'Not provided';

function approve() { router.patch(`${appUrl('/admin/reviews')}/${props.review.id}/approve`); }
function reject() { router.patch(`${appUrl('/admin/reviews')}/${props.review.id}/reject`); }
function destroyReview() { if (window.confirm('Permanently delete this review?')) router.delete(`${appUrl('/admin/reviews')}/${props.review.id}`); }
</script>

<template>
    <AdminLayout>
        <Head title="Review Details" />
        <Link :href="appUrl('/admin/reviews')" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left me-1"></i>Back to reviews</Link>
        <div class="card review-detail p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between gap-3"><div><p class="text-muted small mb-1">Review by</p><h2 class="mb-1">{{ reviewerName }}</h2><p class="text-muted mb-0">{{ reviewerEmail }}</p></div><span class="badge align-self-start" :class="review.is_approved ? 'text-bg-success' : 'text-bg-warning'">{{ review.is_approved ? 'Approved' : 'Pending' }}</span></div>
            <hr>
            <dl class="row mb-4"><dt class="col-sm-3">Tour package</dt><dd class="col-sm-9">{{ review.package?.title }}</dd><dt class="col-sm-3">Rating</dt><dd class="col-sm-9"><StarRating :rating="review.rating" /> <span class="ms-1">{{ review.rating }}/5</span></dd><dt class="col-sm-3">Submitted</dt><dd class="col-sm-9">{{ new Date(review.created_at).toLocaleString() }}</dd><dt v-if="review.booking" class="col-sm-3">Booking</dt><dd v-if="review.booking" class="col-sm-9">{{ review.booking.booking_reference_id }}</dd></dl>
            <h5>Review</h5><p class="review-copy">{{ review.comment }}</p>
            <div class="d-flex flex-wrap gap-2 mt-3"><button v-if="!review.is_approved" class="btn btn-outline-success" @click="approve">Approve</button><button v-else class="btn btn-outline-warning" @click="reject">Unapprove</button><button class="btn btn-outline-danger" @click="destroyReview">Delete</button></div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.review-detail { max-width: 900px; border-radius: 1rem; }.review-copy { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.75; }.badge { padding: .6rem .8rem; }
</style>
