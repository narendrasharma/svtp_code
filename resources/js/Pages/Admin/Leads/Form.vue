<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { appUrl } from '../../../appUrl';

const props = defineProps({
    lead: { type: Object, default: null },
    sources: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    serviceTypes: { type: Array, default: () => [] },
    staff: { type: Array, default: () => [] },
});

const isEdit = computed(() => props.lead !== null);
const endpoint = appUrl('/admin/leads');

const form = useForm({
    source_id: props.lead?.source_id ?? '',
    name: props.lead?.name ?? '',
    phone: props.lead?.phone ?? '',
    email: props.lead?.email ?? '',
    service_type: props.lead?.service_type ?? 'tour',
    product_title: props.lead?.product_title ?? '',
    destination: props.lead?.destination ?? '',
    travel_start_date: props.lead?.travel_start_date ?? '',
    travel_end_date: props.lead?.travel_end_date ?? '',
    adults: props.lead?.adults ?? '',
    children: props.lead?.children ?? '',
    budget: props.lead?.budget ?? '',
    priority: props.lead?.priority ?? 'normal',
    assigned_to: props.lead?.assigned_to ?? '',
    next_follow_up_at: props.lead?.next_follow_up_at ?? '',
    summary: props.lead?.summary ?? '',
});

function submit() {
    if (isEdit.value) form.put(`${endpoint}/${props.lead.id}`);
    else form.post(endpoint);
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="isEdit ? appUrl(`/admin/leads/${lead.id}`) : endpoint" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Leads</Link>
            <h2 class="mt-2 mb-1">{{ isEdit ? `Edit ${lead.reference}` : 'New Lead' }}</h2>
            <p class="text-muted mb-0">A lead needs nothing more than a name and a phone number to enter the pipeline.</p>
        </div>
        <form class="card p-3 p-md-4" style="max-width: 860px;" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-md-4"><label for="lead-source" class="form-label">Source</label><select id="lead-source" v-model="form.source_id" class="form-select"><option value="">—</option><option v-for="s in sources" :key="s.id" :value="s.id">{{ s.name }}</option></select><small class="text-danger">{{ form.errors.source_id }}</small></div>
                <div class="col-md-4"><label for="lead-service" class="form-label">Service</label><select id="lead-service" v-model="form.service_type" class="form-select"><option v-for="t in serviceTypes" :key="t.value" :value="t.value">{{ t.label }}</option></select><small class="text-danger">{{ form.errors.service_type }}</small></div>
                <div class="col-md-4"><label for="lead-priority" class="form-label">Priority</label><select id="lead-priority" v-model="form.priority" class="form-select"><option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option></select><small class="text-danger">{{ form.errors.priority }}</small></div>
                <div class="col-md-6"><label for="lead-name" class="form-label">Name*</label><input id="lead-name" v-model="form.name" class="form-control" required maxlength="255" /><small class="text-danger">{{ form.errors.name }}</small></div>
                <div class="col-md-6"><label for="lead-phone" class="form-label">Phone*</label><input id="lead-phone" v-model="form.phone" class="form-control" required maxlength="30" /><small class="text-danger">{{ form.errors.phone }}</small></div>
                <div class="col-md-6"><label for="lead-email" class="form-label">Email</label><input id="lead-email" v-model="form.email" type="email" class="form-control" maxlength="255" /><small class="text-danger">{{ form.errors.email }}</small></div>
                <div class="col-md-6"><label for="lead-assignee" class="form-label">Assign to</label><select id="lead-assignee" v-model="form.assigned_to" class="form-select"><option value="">Unassigned</option><option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option></select><small class="text-danger">{{ form.errors.assigned_to }}</small></div>
                <div class="col-md-6"><label for="lead-product" class="form-label">Interested product</label><input id="lead-product" v-model="form.product_title" class="form-control" maxlength="255" placeholder="e.g. Mathura Vrindavan 2N/3D" /><small class="text-danger">{{ form.errors.product_title }}</small></div>
                <div class="col-md-6"><label for="lead-destination" class="form-label">Destination</label><input id="lead-destination" v-model="form.destination" class="form-control" maxlength="255" /><small class="text-danger">{{ form.errors.destination }}</small></div>
                <div class="col-md-3"><label for="lead-start" class="form-label">Travel from</label><input id="lead-start" v-model="form.travel_start_date" type="date" class="form-control" /><small class="text-danger">{{ form.errors.travel_start_date }}</small></div>
                <div class="col-md-3"><label for="lead-end" class="form-label">Travel to</label><input id="lead-end" v-model="form.travel_end_date" type="date" class="form-control" /><small class="text-danger">{{ form.errors.travel_end_date }}</small></div>
                <div class="col-md-2"><label for="lead-adults" class="form-label">Adults</label><input id="lead-adults" v-model="form.adults" type="number" min="1" max="100" class="form-control" /><small class="text-danger">{{ form.errors.adults }}</small></div>
                <div class="col-md-2"><label for="lead-children" class="form-label">Children</label><input id="lead-children" v-model="form.children" type="number" min="0" max="100" class="form-control" /><small class="text-danger">{{ form.errors.children }}</small></div>
                <div class="col-md-2"><label for="lead-budget" class="form-label">Budget</label><input id="lead-budget" v-model="form.budget" type="number" step="0.01" min="0" class="form-control" /><small class="text-danger">{{ form.errors.budget }}</small></div>
                <div class="col-md-6"><label for="lead-next" class="form-label">Next follow-up</label><input id="lead-next" v-model="form.next_follow_up_at" type="datetime-local" class="form-control" /><small class="text-danger">{{ form.errors.next_follow_up_at }}</small></div>
                <div class="col-12"><label for="lead-summary" class="form-label">Summary</label><textarea id="lead-summary" v-model="form.summary" class="form-control" rows="3" maxlength="5000"></textarea><small class="text-danger">{{ form.errors.summary }}</small></div>
                <div class="col-12"><button class="btn btn-svtp" :disabled="form.processing">{{ isEdit ? 'Save Changes' : 'Create Lead' }}</button></div>
            </div>
        </form>
    </AdminLayout>
</template>
