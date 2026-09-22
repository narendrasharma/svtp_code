<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Pagination from '../Pagination.vue';
import { appUrl } from '../../appUrl';

const props = defineProps({ reviews: Object, filters: Object, properties: Array, vendors: Array, statuses: Array, basePath: String, admin: Boolean });
const filters = reactive({ search: '', status: '', property_id: '', vendor_profile_id: '', rating: '', from: '', to: '', ...props.filters });
function apply() {
    const params = Object.fromEntries(Object.entries(filters).filter(([key, value]) => key !== 'page' && value !== '' && value !== null));
    router.get(appUrl(props.basePath), params, { preserveState: true });
}
</script>

<template>
    <div class="d-flex flex-column gap-3">
        <div>
            <h1 class="h3 mb-1">Hotel Reviews</h1>
            <p class="text-muted mb-0">{{ admin ? 'Moderate verified stay feedback and official property responses.' : 'Feedback for your properties. Admin handles review moderation.' }}</p>
        </div>
        <form class="card p-3" @submit.prevent="apply">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-4"><label for="hr-search" class="form-label small">Search review or customer</label><input id="hr-search" v-model="filters.search" class="form-control" maxlength="100" /></div>
                <div class="col-sm-6 col-lg-2"><label for="hr-status" class="form-label small">Status</label><select id="hr-status" v-model="filters.status" class="form-select"><option value="">All statuses</option><option v-for="status in statuses" :key="status" :value="status">{{ status }}</option></select></div>
                <div class="col-sm-6 col-lg-3"><label for="hr-property" class="form-label small">Property</label><select id="hr-property" v-model="filters.property_id" class="form-select"><option value="">All properties</option><option v-for="property in properties" :key="property.id" :value="property.id">{{ property.name }}</option></select></div>
                <div v-if="admin" class="col-sm-6 col-lg-3"><label for="hr-vendor" class="form-label small">Vendor</label><select id="hr-vendor" v-model="filters.vendor_profile_id" class="form-select"><option value="">All vendors</option><option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option></select></div>
                <div class="col-sm-4 col-lg-2"><label for="hr-rating" class="form-label small">Rating</label><select id="hr-rating" v-model="filters.rating" class="form-select"><option value="">All ratings</option><option v-for="rating in [5, 4, 3, 2, 1]" :key="rating" :value="rating">{{ rating }} ★</option></select></div>
                <div class="col-sm-4 col-lg-3"><label for="hr-from" class="form-label small">Submitted from</label><input id="hr-from" v-model="filters.from" class="form-control" type="date" /></div>
                <div class="col-sm-4 col-lg-3"><label for="hr-to" class="form-label small">Submitted to</label><input id="hr-to" v-model="filters.to" class="form-control" type="date" :min="filters.from || undefined" /></div>
                <div class="col-lg-4 d-flex align-items-end gap-2"><button class="btn btn-primary">Apply filters</button><Link :href="appUrl(basePath)" class="btn btn-outline-secondary">Reset</Link></div>
            </div>
        </form>
        <div class="card table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Property</th><th>Customer</th><th>Rating</th><th>Status</th><th>Submitted</th><th><span class="visually-hidden">Review actions</span></th></tr></thead>
                <tbody>
                    <tr v-for="review in reviews.data" :key="review.id">
                        <td>{{ review.property.name }}<div v-if="admin && review.vendor" class="small text-muted">{{ review.vendor.business_name }}</div></td>
                        <td>{{ review.customer_name }}<div v-if="review.verified_stay" class="small text-success"><i class="bi bi-patch-check me-1" aria-hidden="true"></i>Verified stay</div></td>
                        <td class="text-nowrap">{{ review.overall_rating }} <span class="text-warning">★</span></td>
                        <td><span class="badge" :class="review.status === 'approved' ? 'bg-success' : review.status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary'">{{ review.status }}</span></td>
                        <td class="small text-nowrap">{{ review.submitted_at?.slice(0, 10) }}</td>
                        <td><Link :href="appUrl(`${basePath}/${review.id}`)" class="btn btn-sm btn-outline-primary">{{ admin ? 'Moderate / view' : 'View / respond' }}</Link></td>
                    </tr>
                    <tr v-if="!reviews.data.length"><td colspan="6" class="text-center text-muted p-4">No reviews match these filters.</td></tr>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mb-0">{{ reviews.total }} reviews · Page {{ reviews.current_page }} of {{ reviews.last_page }}</p>
        <Pagination :links="reviews.links" />
    </div>
</template>
