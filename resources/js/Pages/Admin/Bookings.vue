<script setup>
import { appUrl } from '../../appUrl';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';

defineProps({ bookings: Object });

function updateStatus(booking, status) {
    router.patch(`${appUrl('/admin/bookings')}/${booking.id}/status`, { booking_status: status });
}
</script>

<template>
    <AdminLayout>
        <h2>Bookings</h2>
        <table class="table">
            <tbody>
                <tr v-for="b in bookings.data" :key="b.id">
                    <td>{{ b.booking_reference_id }}</td>
                    <td>{{ b.package.title }}</td>
                    <td>{{ b.user.name }}</td>
                    <td>{{ b.booking_status }}</td>
                    <td>
                        <select @change="updateStatus(b, $event.target.value)" class="form-select form-select-sm">
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>
    </AdminLayout>
</template>
