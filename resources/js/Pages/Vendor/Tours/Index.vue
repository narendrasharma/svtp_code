<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import { appUrl } from '../../../appUrl';
import Pagination from '../../../Components/Pagination.vue';

const props = defineProps({
    tours: { type: Object, required: true },
    filters: { type: Object, required: true },
    moderationStatuses: { type: Array, default: () => [] },
});

const localFilters = ref({
    search: props.filters.search ?? '',
    moderation_status: props.filters.moderation_status ?? '',
    is_active: props.filters.is_active ?? '',
    per_page: props.filters.per_page ?? 10,
});

function applyFilters() {
    const query = {};
    if (localFilters.value.search) query.search = localFilters.value.search;
    if (localFilters.value.moderation_status) query.moderation_status = localFilters.value.moderation_status;
    if (localFilters.value.is_active !== '') query.is_active = localFilters.value.is_active;
    if (localFilters.value.per_page) query.per_page = localFilters.value.per_page;
    router.get(appUrl('/vendor/tours'), query, { preserveState: true, replace: true });
}
function resetFilters() {
    localFilters.value = { search: '', moderation_status: '', is_active: '', per_page: 10 };
    applyFilters();
}
function del(tour) {
    if (!confirm(`Delete draft "${tour.title}"?`)) return;
    router.delete(appUrl(`/vendor/tours/${tour.id}`));
}
function submitForReview(tour) {
    if (!confirm(`Submit "${tour.title}" for review?`)) return;
    router.post(appUrl(`/vendor/tours/${tour.id}/submit`));
}
function moderationBadge(status) {
    return {
        draft: 'bg-secondary',
        pending_review: 'bg-warning text-dark',
        approved: 'bg-success',
        changes_requested: 'bg-info text-dark',
        rejected: 'bg-danger',
    }[status] ?? 'bg-secondary';
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-1">My Tours</h2>
                <p class="text-muted small mb-0">Manage your tour packages. Drafts are private until approved.</p>
            </div>
            <Link :href="appUrl('/vendor/tours/create')" class="btn btn-svtp">+ New Tour</Link>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <input type="text" class="form-control form-control-sm" placeholder="Search title..." v-model="localFilters.search" @keyup.enter="applyFilters" style="max-width: 240px;" />
            <select class="form-select form-select-sm" v-model="localFilters.moderation_status" @change="applyFilters" style="width: 160px;">
                <option value="">All moderation</option>
                <option v-for="s in moderationStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select class="form-select form-select-sm" v-model="localFilters.is_active" @change="applyFilters" style="width: 130px;">
                <option value="">All visibility</option>
                <option value="1">Active</option>
                <option value="0">Hidden</option>
            </select>
            <select class="form-select form-select-sm" v-model.number="localFilters.per_page" @change="applyFilters" style="width: 90px;">
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" @click="applyFilters">Filter</button>
            <button class="btn btn-sm btn-outline-secondary" @click="resetFilters">Reset</button>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Tour</th>
                        <th>Destination</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Moderation</th>
                        <th>Public</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tour in tours.data" :key="tour.id">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img v-if="tour.cover_image" :src="tour.cover_image" alt="" style="width: 44px; height: 44px; object-fit: cover; border-radius: 8px;" />
                                <span v-else class="d-inline-flex align-items-center justify-content-center" style="width: 44px; height: 44px; border-radius: 8px; background: #1e293b; color: #94a3b8;"><i class="bi bi-image"></i></span>
                                <strong>{{ tour.title }}</strong>
                            </div>
                        </td>
                        <td class="small">{{ tour.city?.name ?? '—' }}</td>
                        <td class="small">{{ tour.duration_days }}D / {{ tour.duration_nights }}N</td>
                        <td class="small">₹{{ tour.discounted_price || tour.price }}</td>
                        <td><span class="badge" :class="moderationBadge(tour.moderation_status)">{{ tour.moderation_status }}</span></td>
                        <td><span class="badge" :class="tour.is_active && tour.moderation_status === 'approved' ? 'bg-success' : 'bg-secondary'">{{ tour.is_active && tour.moderation_status === 'approved' ? 'Visible' : 'Hidden' }}</span></td>
                        <td class="small text-muted">{{ new Date(tour.updated_at).toLocaleDateString() }}</td>
                        <td class="text-end text-nowrap">
                            <Link :href="appUrl(`/vendor/tours/${tour.id}/edit`)" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-pencil"></i></Link>
                            <button v-if="['draft','changes_requested','rejected'].includes(tour.moderation_status)" type="button" class="btn btn-sm btn-outline-success me-1" @click="submitForReview(tour)">Submit</button>
                            <button v-if="tour.moderation_status === 'draft'" type="button" class="btn btn-sm btn-outline-danger" @click="del(tour)"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                    <tr v-if="!tours.data.length">
                        <td colspan="8" class="text-center text-muted py-4">No tours found. Create your first tour.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="tours.links" />
    </VendorLayout>
</template>
