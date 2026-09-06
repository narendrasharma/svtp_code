<script setup>
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Pagination from "@/Components/Pagination.vue";
import {router} from "@inertiajs/vue3";
import {appUrl} from "@/appUrl.js";

defineProps({ enquiries: { type: Object, required: true } });
function removeEnquiry(e) {
    if (window.confirm(`Remove ${e.title}?`)) {
        router.delete(`${appUrl('/admin/enquiries')}/${e.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <h2>Enquiries</h2>
        <div class="table-responsive mt-3">
            <table class="table align-middle">
                <thead>
                    <tr><th>#</th><th>Date</th><th>Type</th><th>Name</th><th>Contact</th><th>Travel Details</th><th>Status</th><th>Delete</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(enquiry,index) in enquiries.data" :key="enquiry.id">
                        <td>{{enquiries.from+index}}</td>
                        <td>{{ new Date(enquiry.created_at).toLocaleDateString('en-IN',{
                            day:'2-digit',
                            month:'short',
                            year:'numeric',
                        }).replace(/ /g,'-') }}
                            </td>
                        <td>{{ enquiry.enquiry_type === 'tour_plan' ? 'Tour Plan' : 'Quick' }}</td>
                        <td>{{ enquiry.full_name }}</td>
                        <td>
                            <a :href="`tel:${enquiry.phone}`">{{ enquiry.phone }}</a>
                            <div v-if="enquiry.email" class="small">{{ enquiry.email }}</div>
                        </td>
                        <td>
                            <template v-if="enquiry.enquiry_type === 'tour_plan'">
                                <div v-if="enquiry.tour_package" class="fw-semibold">{{ enquiry.tour_package.title }}</div>
                                <div>{{ enquiry.pickup_drop }}</div>
                                <small>
                                    {{ new Date(enquiry.arrival_date).toLocaleDateString('en-IN',{
                                    day:'2-digit',
                                    month:'short',
                                    year:'numeric',
                                }).replace(/ /g,'-') }} to
                                    {{ new Date(enquiry.departure_date).toLocaleDateString('en-IN',{
                                    day:'2-digit',
                                    month:'short',
                                    year:'numeric',
                                }).replace(/ /g,'-') }} to

                                </small>
                                <div class="small">{{ enquiry.adults }} adults, {{ enquiry.children || 0 }} children · {{ enquiry.hotel_category }}</div>
                                <div v-if="enquiry.message" class="small text-muted mt-1">{{ enquiry.message }}</div>
                            </template>
                            <span v-else class="text-muted">Callback requested</span>
                        </td>
                        <td><span class="badge bg-primary">{{ enquiry.status }}</span></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" @click="removeEnquiry(enquiry)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!enquiries.data.length">
                        <td colspan="6" class="text-center text-muted py-4">No enquiries yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between">
            <Pagination />
        </div>
    </AdminLayout>
</template>
