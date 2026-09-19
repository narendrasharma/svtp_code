<script setup>
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PricingForm from '../../../../Components/Taxi/PricingForm.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    rateCard: { type: Object, default: null }, vendors: { type: Array, default: () => [] }, vehicleTypes: { type: Array, default: () => [] },
    tripTypes: { type: Array, default: () => [] }, ruleCodes: { type: Array, default: () => [] }, calculationTypes: { type: Array, default: () => [] },
    defaultCurrency: { type: String, default: 'INR' }, fixedVendor: { type: Boolean, default: false },
});
</script>

<template>
    <AdminLayout>
        <PricingForm v-bind="props" :endpoint="rateCard ? appUrl(`/admin/taxi/pricing/${rateCard.id}`) : appUrl('/admin/taxi/pricing')" :back-url="appUrl('/admin/taxi/pricing')" />
    </AdminLayout>
</template>
