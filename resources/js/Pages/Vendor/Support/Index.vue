<script setup>
import { Link } from '@inertiajs/vue3';
import VendorLayout from '../../../Layouts/VendorLayout.vue';
import Pagination from '../../../Components/Pagination.vue';
import { appUrl } from '../../../appUrl';

defineProps({
    tickets: Object,
});

function statusBadge(status) {
    return { open: 'bg-primary', pending_staff: 'bg-warning text-dark', pending_customer: 'bg-info text-dark', resolved: 'bg-success', closed: 'bg-secondary' }[status] ?? 'bg-secondary';
}
</script>

<template>
    <VendorLayout>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h2 class="mb-1">Support Tickets</h2>
                <p class="text-muted small mb-0">KYC, payouts, tours, bookings — our team replies here.</p>
            </div>
            <Link :href="appUrl('/vendor/support/create')" class="btn btn-svtp btn-sm"><i class="bi bi-plus-lg me-1"></i>New Ticket</Link>
        </div>

        <div v-if="tickets.data.length" class="d-flex flex-column gap-2">
            <Link
                v-for="ticket in tickets.data"
                :key="ticket.id"
                :href="appUrl(`/vendor/support/${ticket.id}`)"
                class="card p-3 text-decoration-none"
            >
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <strong>{{ ticket.reference }}</strong>
                    <span class="badge" :class="statusBadge(ticket.status)">{{ ticket.status }}</span>
                    <span v-if="ticket.category" class="badge bg-light text-dark">{{ ticket.category.name }}</span>
                    <span class="ms-auto small text-muted">{{ ticket.updated_at ? new Date(ticket.updated_at).toLocaleDateString('en-IN') : '' }}</span>
                </div>
                <div class="mt-1">{{ ticket.subject }}</div>
            </Link>
        </div>
        <p v-else class="text-muted">No tickets yet.</p>
        <Pagination :links="tickets.links" />
    </VendorLayout>
</template>
