<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

defineProps({
    series: { type: Array, default: () => [] },
    resetCycles: { type: Array, default: () => [] },
});

function formFor(row) {
    return useForm({
        prefix: row.prefix,
        separator: row.separator,
        include_year: !!row.include_year,
        include_month: !!row.include_month,
        padding: row.padding,
        start_number: row.start_number,
        next_number: row.next_number,
        reset_cycle: row.reset_cycle,
    });
}

const forms = new Map();
function getForm(row) {
    if (!forms.has(row.entity)) forms.set(row.entity, formFor(row));
    return forms.get(row.entity);
}

function save(row) {
    getForm(row).put(appUrl(`/admin/number-series/${row.entity}`), { preserveScroll: true });
}
</script>

<template>
    <AdminLayout>
        <h2 class="mb-1">Number Series</h2>
        <p class="text-muted mb-4">Human-readable references (bookings, invoices, refunds, ...). Changes apply to <strong>future</strong> references only — issued references are never renamed. Counters can only move forward.</p>

        <div class="row g-3">
            <div v-for="row in series" :key="row.entity" class="col-lg-6">
                <form class="card p-3 h-100" @submit.prevent="save(row)">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                        <h5 class="mb-0">{{ row.display_name }}</h5>
                        <span class="badge bg-secondary">{{ row.entity }}</span>
                    </div>
                    <p class="small text-muted mb-2">{{ row.description }}</p>
                    <p class="mb-3">Next preview: <code>{{ row.preview }}</code></p>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label small">Prefix</label>
                            <input v-model="getForm(row).prefix" class="form-control form-control-sm" maxlength="10" required />
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Separator</label>
                            <select v-model="getForm(row).separator" class="form-select form-select-sm">
                                <option value="-">- (dash)</option>
                                <option value="/">/ (slash)</option>
                                <option value="_">_ (underscore)</option>
                                <option value="">(none)</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Padding</label>
                            <input v-model.number="getForm(row).padding" type="number" min="3" max="10" class="form-control form-control-sm" required />
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Start number</label>
                            <input v-model.number="getForm(row).start_number" type="number" min="1" class="form-control form-control-sm" required />
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Next number</label>
                            <input v-model.number="getForm(row).next_number" type="number" min="1" class="form-control form-control-sm" required />
                            <small class="text-danger">{{ getForm(row).errors.next_number }}</small>
                        </div>
                        <div class="col-4">
                            <label class="form-label small">Reset cycle</label>
                            <select v-model="getForm(row).reset_cycle" class="form-select form-select-sm">
                                <option v-for="cycle in resetCycles" :key="cycle" :value="cycle">{{ cycle }}</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <div class="form-check mt-2">
                                <input :id="`year-${row.entity}`" v-model="getForm(row).include_year" type="checkbox" class="form-check-input" />
                                <label :for="`year-${row.entity}`" class="form-check-label small">Include year (YYYY)</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check mt-2">
                                <input :id="`month-${row.entity}`" v-model="getForm(row).include_month" type="checkbox" class="form-check-input" />
                                <label :for="`month-${row.entity}`" class="form-check-label small">Include month (MM)</label>
                            </div>
                        </div>
                    </div>
                    <small class="text-danger">{{ getForm(row).errors.prefix }}</small>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-svtp" :disabled="getForm(row).processing">Save Series</button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
