<script setup>
import { appUrl } from '../../appUrl';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import StarRating from '../../Components/StarRating.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({ reviews: Object, packages: Array, filters: Object, counts: Object });
const filterForm = useForm({
    search: props.filters.search ?? '',
    package_id: props.filters.package_id ?? '',
    rating: props.filters.rating ?? '',
    status: props.filters.status ?? 'pending',
});

function applyFilters() {
    filterForm.get(appUrl('/admin/reviews'), { preserveState: true, replace: true });
}

function setStatus(status) {
    filterForm.status = status;
    applyFilters();
}

function approve(review) {
    router.patch(`${appUrl('/admin/reviews')}/${review.id}/approve`, {}, { preserveScroll: true });
}

function reject(review) {
    router.patch(`${appUrl('/admin/reviews')}/${review.id}/reject`, {}, { preserveScroll: true });
}

function destroyReview(review) {
    if (window.confirm('Permanently delete this review?')) {
        router.delete(`${appUrl('/admin/reviews')}/${review.id}`);
    }
}

function reviewerName(review) {
    return review.reviewer_name || review.user?.name || 'Guest';
}
</script>

<template>
    <AdminLayout>
        <Head title="Review Moderation" />
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div><h2 class="mb-1">Review Moderation</h2><p class="text-muted mb-0">Approve genuine feedback and remove inappropriate submissions.</p></div>
            <div class="d-flex gap-2"><span class="badge text-bg-warning">{{ counts.pending }} pending</span><span class="badge text-bg-success">{{ counts.approved }} approved</span></div>
        </div>

        <div class="review-tabs mt-4" role="tablist" aria-label="Review status">
            <button v-for="status in ['pending', 'approved', 'all']" :key="status" type="button" class="btn btn-sm" :class="filterForm.status === status ? 'btn-svtp' : 'btn-outline-secondary'" @click="setStatus(status)">{{ status[0].toUpperCase() + status.slice(1) }}</button>
        </div>

        <form class="card p-3 mt-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-5"><label class="form-label small">Search</label><input v-model="filterForm.search" class="form-control" placeholder="Reviewer, email, package, or review text"></div>
                <div class="col-12 col-md-5 col-lg-3"><label class="form-label small">Package</label><select v-model="filterForm.package_id" class="form-select"><option value="">All packages</option><option v-for="tour in packages" :key="tour.id" :value="tour.id">{{ tour.title }}</option></select></div>
                <div class="col-7 col-md-4 col-lg-2"><label class="form-label small">Rating</label><select v-model="filterForm.rating" class="form-select"><option value="">All ratings</option><option v-for="rating in 5" :key="rating" :value="rating">{{ rating }} star{{ rating === 1 ? '' : 's' }}</option></select></div>
                <div class="col-5 col-md-3 col-lg-2"><button class="btn btn-svtp w-100" :disabled="filterForm.processing">Filter</button></div>
            </div>
        </form>

        <div class="table-responsive mt-3">
            <table class="table align-middle">
                <thead><tr><th>Reviewer</th><th>Package</th><th>Rating</th><th>Review</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <tr v-for="review in reviews.data" :key="review.id">
                        <td><strong>{{ reviewerName(review) }}</strong><small class="d-block text-muted">{{ review.reviewer_email || review.user?.email || 'No email' }}</small></td>
                        <td>{{ review.package?.title }}</td><td><StarRating :rating="review.rating" /></td><td class="review-excerpt">{{ review.comment }}</td>
                        <td><span class="badge" :class="review.is_approved ? 'text-bg-success' : 'text-bg-warning'">{{ review.is_approved ? 'Approved' : 'Pending' }}</span></td>
                        <td><div class="d-flex flex-wrap justify-content-end gap-1"><Link :href="`${appUrl('/admin/reviews')}/${review.id}`" class="btn btn-sm btn-outline-info">View</Link><button v-if="!review.is_approved" type="button" class="btn btn-sm btn-outline-success" @click="approve(review)">Approve</button><button v-else type="button" class="btn btn-sm btn-outline-warning" @click="reject(review)">Unapprove</button><button type="button" class="btn btn-sm btn-outline-danger" @click="destroyReview(review)">Delete</button></div></td>
                    </tr>
                    <tr v-if="!reviews.data.length"><td colspan="6" class="py-5 text-center text-muted">No reviews match these filters.</td></tr>
                </tbody>
            </table>
        </div>

        <nav v-if="reviews.links?.length > 3" class="d-flex flex-wrap gap-1 mt-3" aria-label="Review result pages"><template v-for="link in reviews.links" :key="link.label"><Link v-if="link.url" :href="link.url" class="btn btn-sm" :class="link.active ? 'btn-svtp' : 'btn-outline-secondary'" preserve-state v-html="link.label" /><span v-else class="btn btn-sm btn-outline-secondary disabled" v-html="link.label"></span></template></nav>
    </AdminLayout>
</template>

<style scoped>
.review-tabs { display: flex; flex-wrap: wrap; gap: .5rem; }.review-excerpt { min-width: 220px; max-width: 360px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.badge { padding: .55rem .7rem; }
</style>
