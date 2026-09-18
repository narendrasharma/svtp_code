<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { appUrl } from '../../../appUrl';
import axios from 'axios';

const props = defineProps({
    quotation: Object,
    tourContext: { type: Object, default: () => ({}) },
    tours: { type: Array, default: () => [] },
    customer: { type: Object, default: null },
});

const endpoint = appUrl(`/admin/quotations/${props.quotation.id}/convert`);

const form = useForm({
    package_id: props.tourContext.package_id ?? '',
    travel_date: props.tourContext.travel_date ?? '',
    adults: props.tourContext.adults ?? 1,
    children: props.tourContext.children ?? 0,
    price_basis: 'current',
    price_reason: '',
    customer_user_id: props.quotation.customer_user_id ?? '',
});

const tourOptions = computed(() => props.tours.map((t) => ({ value: t.id, label: t.title, meta: `₹${t.discounted_price > 0 ? t.discounted_price : t.price}` })));

async function searchCustomers(query) {
    try {
        const response = await axios.get(appUrl('/admin/customers/search'), { params: { q: query } });
        return response.data.options ?? [];
    } catch (e) {
        return [];
    }
}

function submit() {
    if (form.price_basis === 'quoted' && !form.price_reason) {
        alert('Record why the quoted price is honored when the live price moved.');
        return;
    }
    form.post(endpoint);
}
</script>

<template>
    <AdminLayout>
        <div class="mb-4">
            <Link :href="appUrl(`/admin/quotations/${quotation.id}`)" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>{{ quotation.reference }}</Link>
            <h2 class="mt-2 mb-1">Convert to Booking</h2>
            <p class="text-muted mb-0">Accepted total <strong>₹{{ quotation.total_amount }}</strong>. Availability is rechecked and totals recomputed live — any drift needs an explicit choice below.</p>
        </div>

        <form class="card p-3 p-md-4" style="max-width: 760px;" @submit.prevent="submit">
            <div class="row g-3">
                <div class="col-12">
                    <SmartSelect v-model="form.package_id" label="Tour Package" :options="tourOptions" placeholder="Search tour packages..." :error="form.errors.package_id" required />
                    <small class="text-danger">{{ form.errors.package_id }}</small>
                </div>
                <div class="col-md-4"><label for="conv-travel" class="form-label">Travel Date*</label><input id="conv-travel" v-model="form.travel_date" type="date" class="form-control" required /><small class="text-danger">{{ form.errors.travel_date }}</small></div>
                <div class="col-md-4"><label for="conv-adults" class="form-label">Adults*</label><input id="conv-adults" v-model.number="form.adults" type="number" min="1" max="100" class="form-control" required /></div>
                <div class="col-md-4"><label for="conv-children" class="form-label">Children</label><input id="conv-children" v-model.number="form.children" type="number" min="0" max="100" class="form-control" /></div>
                <div class="col-12">
                    <SmartSelect v-model="form.customer_user_id" label="Customer account" :fetch-options="searchCustomers" placeholder="Keep quotation customer or search..." />
                    <small class="text-muted">Defaults to the quotation's linked customer/lead details.</small>
                </div>
                <div class="col-md-6">
                    <label for="price-basis" class="form-label">If the live price moved…</label>
                    <select id="price-basis" v-model="form.price_basis" class="form-select">
                        <option value="current">Recalculate at current price</option>
                        <option value="quoted">Honor accepted quoted price</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="price-reason" class="form-label">Reason (required when honoring quote)</label>
                    <input id="price-reason" v-model="form.price_reason" class="form-control" maxlength="500" placeholder="e.g. Manager-approved goodwill" />
                    <small class="text-danger">{{ form.errors.price_reason }}</small>
                </div>
                <div class="col-12"><button class="btn btn-svtp" :disabled="form.processing">Create Booking</button></div>
                <div v-if="form.hasErrors" class="col-12 text-danger small"><div v-for="(e, k) in form.errors" :key="k">{{ e }}</div></div>
            </div>
        </form>
    </AdminLayout>
</template>
