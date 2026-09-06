<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';
import Pagination from "@/Components/Pagination.vue";

defineProps({ destinations: Object });

function removeDestination(destination) {
    if (window.confirm(`Remove ${destination.name} and its places?`)) {
        router.delete(`${appUrl('/admin/destinations')}/${destination.id}`);
    }
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center">
            <h2>Destinations</h2>
            <Link :href="appUrl('/admin/destinations/create')" class="btn btn-svtp">+ New Destination</Link>
        </div>
        <div class="table-responsive mt-3">
            <table class="table align-middle">
                <thead><tr><th>#</th><th>Name</th><th>City</th><th>Places</th><th>Tours</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <tr v-for="(destination,index) in destinations.data" :key="destination.id">

                        <td>{{destinations.from+index}}</td>
                        <td><strong>{{ destination.name }}</strong><br><small class="text-muted">{{ destination.slug }}</small></td>
                        <td>{{ destination.city?.name || '—' }}</td>
                        <td>{{ destination.places_count }}</td>
                        <td>{{ destination.tour_packages_count }}</td>
                        <td class="text-end">
                            <Link :href="`${appUrl('/admin/destinations')}/${destination.id}/edit`" class="btn btn-sm btn-outline-secondary me-2">Edit</Link>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removeDestination(destination)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!destinations.data.length"><td colspan="5" class="text-center text-muted py-4">No destinations added yet.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between">
            <Pagination :links="destinations.links" />
        </div>
    </AdminLayout>
</template>
