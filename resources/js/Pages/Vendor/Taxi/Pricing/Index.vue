<script setup>
import { Link, router } from '@inertiajs/vue3';
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';
defineProps({ rateCards: { type: Object, required: true } });
function toggle(card) { router.patch(appUrl(`/vendor/taxi/pricing/${card.id}/toggle`), {}, { preserveScroll: true }); }
function remove(card) { if (window.confirm(`Remove ${card.name}? Historical booking snapshots will remain.`)) router.delete(appUrl(`/vendor/taxi/pricing/${card.id}`)); }
</script>

<template>
    <VendorLayout>
        <div class="d-flex justify-content-between align-items-center gap-3 mb-4"><div><h2 class="mb-1">Taxi pricing</h2><p class="text-muted mb-0">Your rate cards override platform rates only for your own bookings.</p></div><Link :href="appUrl('/vendor/taxi/pricing/create')" class="btn btn-primary">New rate card</Link></div>
        <div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Trip</th><th>Vehicle</th><th>Currency</th><th>Rules</th><th>Status</th><th></th></tr></thead><tbody><tr v-for="card in rateCards.data" :key="card.id"><td>{{ card.name }}</td><td class="text-capitalize">{{ String(card.trip_type).replaceAll('_', ' ') }}</td><td>{{ card.vehicle_type?.name ?? 'Default' }}</td><td>{{ card.currency }}</td><td>{{ card.rules_count }}<span v-if="card.rental_packages_count"> · {{ card.rental_packages_count }} packages</span></td><td><span class="badge" :class="card.is_active ? 'bg-success' : 'bg-secondary'">{{ card.is_active ? 'Active' : 'Inactive' }}</span></td><td class="text-end"><div class="btn-group btn-group-sm"><Link :href="appUrl(`/vendor/taxi/pricing/${card.id}/edit`)" class="btn btn-outline-secondary">Edit</Link><button class="btn btn-outline-secondary" @click="toggle(card)">{{ card.is_active ? 'Deactivate' : 'Activate' }}</button><button class="btn btn-outline-danger" @click="remove(card)">Remove</button></div></td></tr><tr v-if="!rateCards.data.length"><td colspan="7" class="text-center text-muted py-4">No rate cards yet.</td></tr></tbody></table></div><Pagination :links="rateCards.links" class="p-3" /></div>
    </VendorLayout>
</template>
