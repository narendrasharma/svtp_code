<script setup>
import { appUrl } from '../../../appUrl';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import {Link, router} from '@inertiajs/vue3';
import Pagination from '../../../Components/Pagination.vue'
defineProps({ packages: Object });
function removePackage(p) {
    if (window.confirm(`Remove ${p.title}?`)) {
        router.delete(`${appUrl('/admin/packages')}/${p.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center">
            <h2>Packages</h2>
            <Link :href="appUrl('/admin/packages/create')" class="btn btn-svtp">+ New Package</Link>
        </div>
        <div class="table table-responsive mt-3">
            <table class="table align-middle">
            <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>City</th>
                <th>Price</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>

                <tr v-for="(p,index) in packages.data" :key="p.id">
                    <td>{{packages.from+index}}</td>
                    <td>{{ p.title }}</td>
                    <td>{{ p.city.name }}</td>
                    <td>₹{{ p.discounted_price || p.price }}</td>
                    <td>{{ p.is_active ? 'Active' : 'Hidden' }}</td>
                    <td><Link class="btn btn-sm btn-outline-secondary me-2" :href="`${appUrl('/admin/packages')}/${p.id}/edit`">Edit</Link>
                        <button type="button" class="btn btn-sm btn-outline-danger" @click="removePackage(p)">Delete</button>

                    </td>
    </tr>
                <tr v-if="!packages.data.length"><td colspan="5" class="text-center text-muted py-4">No packages added yet.</td></tr>
            </tbody>
            </table>
        </div>


        <div class="d-flex justify-content-between">
            <Pagination :links="packages.links" />
            </div>

    </AdminLayout>
</template>
