<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import PayoutCreate from '../../../../Components/Taxi/PayoutCreate.vue';
defineProps({ driver: Object, drivers: Array, eligible: Array, paymentMethods: Array });
</script>
<template><VendorLayout><PayoutCreate :driver="driver" :drivers="drivers" :eligible="eligible" :payment-methods="paymentMethods" portal="vendor" /></VendorLayout></template>
