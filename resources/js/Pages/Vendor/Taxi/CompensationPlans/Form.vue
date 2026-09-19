<script setup>
import VendorLayout from '../../../../Layouts/VendorLayout.vue';
import CompensationPlanForm from '../../../../Components/Taxi/CompensationPlanForm.vue';
defineProps({ plan: Object, vendors: Array, drivers: Array, vehicleTypes: Array, calculationTypes: Array });
</script>
<template><VendorLayout><CompensationPlanForm :plan="plan" :vendors="vendors" :drivers="drivers" :vehicle-types="vehicleTypes" :calculation-types="calculationTypes" portal="vendor" /></VendorLayout></template>
