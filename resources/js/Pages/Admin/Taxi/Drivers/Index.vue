<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import Pagination from '../../../../Components/Pagination.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    drivers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    vendors: { type: Array, default: () => [] },
    availabilityStatuses: { type: Array, default: () => [] },
});

const endpoint = appUrl('/admin/taxi/drivers');

const form = useForm({
    search: props.filters.search ?? '',
    vendor_id: props.filters.vendor_id ?? '',
    availability_status: props.filters.availability_status ?? '',
});

const hasFilters = computed(() => Object.values(form.data()).some(value => value !== '' && value !== null));

function applyFilters() {
    form.get(endpoint, { preserveState: true, preserveScroll: true });
}

function clearFilters() {
    router.get(endpoint, {}, { preserveScroll: true });
}

function availabilityBadge(status) {
    const map = {
        available: 'bg-success',
        offline: 'bg-secondary',
        break: 'bg-warning text-dark',
        on_leave: 'bg-info text-dark',
    };
    return map[status] ?? 'bg-secondary';
}
</script>

<template>
    <AdminLayout>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="mt-2 mb-1">Taxi drivers</h2>
                <p class="text-muted mb-0">Vendor-managed drivers linked to taxi bookings.</p>
            </div>
            <Link :href="appUrl('/admin/taxi/drivers/create')" class="btn btn-svtp"><i class="bi bi-plus-lg me-2"></i>New driver</Link>
        </div>

        <form class="card p-3 mb-3" @submit.prevent="applyFilters">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Search</label>
                    <input v-model="form.search" type="text" class="form-control form-control-sm" placeholder="Reference, name or phone" />
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Vendor</label>
                    <select v-model="form.vendor_id" class="form-select form-select-sm">
                        <option value="">All vendors</option>
                        <option v-for="vendor in vendors" :key="vendor.id" :value="vendor.id">{{ vendor.business_name }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Availability</label>
                    <select v-model="form.availability_status" class="form-select form-select-sm">
                        <option value="">Any availability</option>
                        <option v-for="status in availabilityStatuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-svtp" :disabled="form.processing">Apply filters</button>
                    <button v-if="hasFilters" type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">Clear</button>
                    <span class="ms-auto text-muted small align-self-center">{{ drivers.total }} driver(s)</span>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Reference</th><th>Name</th><th>Vendor</th><th>Phone</th><th>Availability</th><th>Employment</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        <tr v-for="driver in drivers.data" :key="driver.id">
                            <td><Link :href="appUrl(`/admin/taxi/drivers/${driver.id}`)" class="fw-semibold text-decoration-none">{{ driver.reference }}</Link></td>
                            <td>{{ driver.first_name }} {{ driver.last_name }}</td>
                            <td>{{ driver.vendor_profile?.business_name ?? '—' }}</td>
                            <td>{{ driver.phone }}</td>
                            <td><span class="badge text-uppercase" :class="availabilityBadge(driver.availability_status)">{{ driver.availability_status }}</span></td>
                            <td class="text-capitalize">{{ driver.employment_status }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <Link :href="appUrl(`/admin/taxi/drivers/${driver.id}`)" class="btn btn-outline-light">View</Link>
                                    <Link :href="appUrl(`/admin/taxi/drivers/${driver.id}/edit`)" class="btn btn-outline-light">Edit</Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!drivers.data.length"><td colspan="7" class="text-center text-muted py-4">No drivers match these filters.</td></tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="drivers.links" class="p-3" />
        </div>
    </AdminLayout>
</template>

<style scoped>
.card { border-radius: 1rem; border: 1px solid rgba(148, 163, 184, .12); background: #101827; color: #e2e8f0; }
.form-control, .form-select { background: rgba(15, 23, 42, .65); border-color: rgba(148, 163, 184, .25); color: #f8fafc; }
.form-control:focus, .form-select:focus { border-color: #f59e0b; box-shadow: 0 0 0 .2rem rgba(245, 158, 11, .18); }
.table { --bs-table-bg: transparent; color: inherit; }
.btn.btn-outline-light { color: #f8fafc; border-color: rgba(148, 163, 184, .35); }
</style>
