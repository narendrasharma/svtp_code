<script setup>
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import PayoutCreate from '../../../../Components/Taxi/PayoutCreate.vue';
defineProps({ driver: Object, drivers: Array, eligible: Array, paymentMethods: Array });
</script>
<template><AdminLayout><PayoutCreate :driver="driver" :drivers="drivers" :eligible="eligible" :payment-methods="paymentMethods" portal="admin" /></AdminLayout></template>
