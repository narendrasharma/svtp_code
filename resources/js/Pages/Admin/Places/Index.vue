<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({ places: Object });

function removePlace(place) {
    if (window.confirm(`Remove ${place.name}?`)) router.delete(`${appUrl('/admin/places')}/${place.id}`);
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center">
            <h2>Places / Attractions</h2>
            <Link :href="appUrl('/admin/places/create')" class="btn btn-svtp">+ New Place</Link>
        </div>
        <div class="table-responsive mt-3">
            <table class="table align-middle">
                <thead><tr><th>Name</th><th>Destination</th><th>Tours</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <tr v-for="place in places.data" :key="place.id">
                        <td><strong>{{ place.name }}</strong><br><small class="text-muted">{{ place.slug }}</small></td>
                        <td>{{ place.destination.name }}</td>
                        <td>{{ place.tour_packages_count }}</td>
                        <td class="text-end">
                            <Link :href="`${appUrl('/admin/places')}/${place.id}/edit`" class="btn btn-sm btn-outline-secondary me-2">Edit</Link>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="removePlace(place)">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!places.data.length"><td colspan="4" class="text-center text-muted py-4">No places added yet.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between">
            <Link v-if="places.prev_page_url" :href="places.prev_page_url" class="btn btn-outline-secondary">Previous</Link><span v-else></span>
            <Link v-if="places.next_page_url" :href="places.next_page_url" class="btn btn-outline-secondary">Next</Link>
        </div>
    </AdminLayout>
</template>
