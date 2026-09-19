<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import PricingForm from '../../../../Components/Taxi/PricingForm.vue';
import { appUrl } from '../../../../appUrl';

const props = defineProps({
    rateCard: { type: Object, default: null }, vendors: { type: Array, default: () => [] }, vehicleTypes: { type: Array, default: () => [] },
    tripTypes: { type: Array, default: () => [] }, ruleCodes: { type: Array, default: () => [] }, calculationTypes: { type: Array, default: () => [] },
    defaultCurrency: { type: String, default: 'INR' }, fixedVendor: { type: Boolean, default: true },
});
</script>

<template>
    <VendorLayout>
        <PricingForm v-bind="props" :endpoint="rateCard ? appUrl(`/vendor/taxi/pricing/${rateCard.id}`) : appUrl('/vendor/taxi/pricing')" :back-url="appUrl('/vendor/taxi/pricing')" />
    </VendorLayout>
</template>
