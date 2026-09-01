<script setup>
import { appUrl } from '../../../appUrl';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({ packages: Object });
</script>

<template>
    <AdminLayout>
        <div class="d-flex justify-content-between align-items-center">
            <h2>Packages</h2>
            <Link :href="appUrl('/admin/packages/create')" class="btn btn-svtp">+ New Package</Link>
        </div>
        <table class="table mt-3">
            <tbody>
                <tr v-for="p in packages.data" :key="p.id">
                    <td>{{ p.title }}</td>
                    <td>{{ p.city.name }}</td>
                    <td>₹{{ p.discounted_price || p.price }}</td>
                    <td>{{ p.is_active ? 'Active' : 'Hidden' }}</td>
                    <td><Link :href="`${appUrl('/admin/packages')}/${p.id}/edit`">Edit</Link></td>
                </tr>
            </tbody>
        </table>
    </AdminLayout>
</template>
