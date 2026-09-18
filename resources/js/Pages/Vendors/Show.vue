<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import SeoHead from '../../Components/SeoHead.vue';
import PackageCard from '../../Components/PackageCard.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({
    vendor: { type: Object, required: true },
    tours: { type: Object, required: true },
    reviews: { type: Array, default: () => [] },
    seo: { type: Object, default: () => ({}) },
});
</script>

<template>
    <AppLayout>
        <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" :image="seo.image" />
        <div class="container py-5">
            <div class="glass-card p-4 p-md-5 mb-4">
                <div class="d-flex flex-wrap gap-4 align-items-center">
                    <img v-if="vendor.logo" :src="vendor.logo" :alt="vendor.business_name" class="rounded" style="width: 96px; height: 96px; object-fit: cover;" />
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h1 class="section-title mb-0">{{ vendor.business_name }}</h1>
                            <span v-if="vendor.is_verified" class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Verified Vendor</span>
                        </div>
                        <p class="text-muted mb-1 small">
                            <span v-if="vendor.city">{{ vendor.city }}<span v-if="vendor.state">, {{ vendor.state }}</span> · </span>
                            <span v-if="vendor.joined_at">On SVTP since {{ vendor.joined_at }}</span>
                        </p>
                        <p v-if="vendor.average_rating" class="mb-0 small">
                            <i class="bi bi-star-fill text-warning"></i> {{ vendor.average_rating }} ({{ vendor.reviews_count }} review{{ vendor.reviews_count === 1 ? '' : 's' }}) · {{ vendor.tours_count }} tour{{ vendor.tours_count === 1 ? '' : 's' }}
                        </p>
                        <p v-else class="mb-0 small text-muted">{{ vendor.tours_count }} tour{{ vendor.tours_count === 1 ? '' : 's' }}</p>
                    </div>
                </div>
                <p v-if="vendor.description" class="mt-3 mb-0">{{ vendor.description }}</p>
                <div class="d-flex flex-wrap gap-3 mt-3 small">
                    <a v-if="vendor.website" :href="vendor.website" target="_blank" rel="noopener" class="text-decoration-none"><i class="bi bi-globe me-1"></i>Website</a>
                    <span v-if="vendor.public_phone" class="text-muted"><i class="bi bi-telephone me-1"></i>{{ vendor.public_phone }}</span>
                    <span v-if="vendor.public_email" class="text-muted"><i class="bi bi-envelope me-1"></i>{{ vendor.public_email }}</span>
                    <a v-for="(url, key) in vendor.social_links ?? {}" :key="key" :href="url" target="_blank" rel="noopener" class="text-decoration-none text-capitalize"><i class="bi bi-link-45deg me-1"></i>{{ key }}</a>
                </div>
            </div>

            <h2 class="h4 mb-3">Tours by {{ vendor.business_name }}</h2>
            <div v-if="tours.data?.length" class="row g-4">
                <div v-for="pkg in tours.data" :key="pkg.id" class="col-md-6 col-lg-4">
                    <PackageCard :pkg="pkg" />
                </div>
            </div>
            <p v-else class="text-muted">No public tours right now.</p>
            <div v-if="tours.links?.length > 3" class="d-flex gap-1 flex-wrap mt-4">
                <Link v-for="link in tours.links" :key="link.label" :href="link.url ?? '#'" class="btn btn-sm" :class="link.active ? 'btn-svtp' : 'btn-outline-svtp'" v-html="link.label" />
            </div>

            <h2 v-if="reviews?.length" class="h4 mt-5 mb-3">Recent traveller reviews</h2>
            <div v-if="reviews?.length" class="row g-3">
                <div v-for="review in reviews" :key="review.id" class="col-md-6">
                    <div class="glass-card p-3 h-100">
                        <div class="d-flex align-items-center gap-2 small">
                            <strong>{{ review.reviewer_name }}</strong>
                            <span class="text-warning">{{ '★'.repeat(review.rating) }}</span>
                            <span v-if="review.is_verified_booking" class="badge bg-success">Verified booking</span>
                        </div>
                        <p class="small mt-2 mb-1">{{ review.comment }}</p>
                        <Link v-if="review.tour?.slug" :href="appUrl(`/packages/${review.tour.slug}`)" class="small text-decoration-none">{{ review.tour.title }}</Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
