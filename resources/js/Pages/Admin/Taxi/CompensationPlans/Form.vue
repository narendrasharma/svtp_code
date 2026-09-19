<script setup>
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import CompensationPlanForm from '../../../../Components/Taxi/CompensationPlanForm.vue';
defineProps({ plan: Object, vendors: Array, drivers: Array, vehicleTypes: Array, calculationTypes: Array });
</script>
<template><AdminLayout><CompensationPlanForm :plan="plan" :vendors="vendors" :drivers="drivers" :vehicle-types="vehicleTypes" :calculation-types="calculationTypes" portal="admin" /></AdminLayout></template>
