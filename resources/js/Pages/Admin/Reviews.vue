<script setup>
import { appUrl } from '../../appUrl';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';

defineProps({ reviews: Object });

function approve(review) {
    router.patch(`${appUrl('/admin/reviews')}/${review.id}/approve`);
}
</script>

<template>
    <AdminLayout>
        <h2>Review Moderation</h2>
        <table class="table">
            <tbody>
                <tr v-for="r in reviews.data" :key="r.id">
                    <td>{{ r.user.name }}</td>
                    <td>{{ r.package.title }}</td>
                    <td>{{ r.rating }}★</td>
                    <td>{{ r.comment }}</td>
                    <td>{{ r.is_approved ? 'Approved' : 'Pending' }}</td>
                    <td><button v-if="!r.is_approved" class="btn btn-sm btn-svtp" @click="approve(r)">Approve</button></td>
                </tr>
            </tbody>
        </table>
    </AdminLayout>
</template>
